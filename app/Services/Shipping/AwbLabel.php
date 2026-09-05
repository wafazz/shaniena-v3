<?php

namespace App\Services\Shipping;

use App\Exceptions\ShipmentFailed;
use App\Models\AwbPrint;
use App\Models\ImageSetting;
use App\Models\JtCode;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder as QrBuilder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Types\TypeCode128;

/**
 * Renders courier air waybills as one PDF.
 *
 * QR and barcode are built in memory. The source wrote every QR to a PNG under
 * `temp/` and never cleaned up, so the directory grew a file per parcel ever
 * printed — and the codes were world-readable over the web root.
 */
class AwbLabel
{
    /** dompdf can only reach data: URIs safely; nothing is fetched over HTTP. */
    public function __construct(private Couriers $couriers) {}

    /**
     * @param  Collection<int, Order>  $orders
     */
    public function pdf(Collection $orders, int $actorId): \Barryvdh\DomPDF\PDF
    {
        $printable = $orders->filter(fn (Order $o) => filled($o->awb_number))->values();

        if ($printable->isEmpty()) {
            throw new ShipmentFailed('None of those orders have an AWB to print yet.');
        }

        $labels = $printable->map(fn (Order $order) => [
            'order' => $order,
            'lines' => $order->lines()->with('product')->get(),
            'qr' => $this->qr($order->awb_number),
            'barcode' => $this->barcode($order->awb_number),
            'courierLogo' => $this->courierLogo($order->courier_service),
            'reference' => JtCode::forOrder((int) $order->id)?->jt_code,
        ]);

        $paper = config('shipping.label.paper') === 'thermal'
            ? config('shipping.label.thermal')
            : config('shipping.label.paper', 'a5');

        $pdf = Pdf::loadView('pdf.awb', [
            'labels' => $labels,
            'shipper' => config('shipping.shipper'),
            'storeLogo' => $this->storeLogo(),
        ])->setPaper($paper);

        // Recorded only once the labels actually rendered.
        $this->recordPrints($printable, $actorId);

        return $pdf;
    }

    /** @param  Collection<int, Order>  $orders */
    private function recordPrints(Collection $orders, int $actorId): void
    {
        DB::transaction(function () use ($orders, $actorId) {
            foreach ($orders as $order) {
                AwbPrint::create(['order_id' => (string) $order->id, 'printed_by' => $actorId]);
            }

            Order::query()->whereIn('id', $orders->pluck('id'))->update(['printed_awb' => 1]);
        });
    }

    /** CODE 128, the symbology the couriers' scanners expect. */
    public function barcode(string $code): string
    {
        $png = (new PngRenderer)
            ->render((new TypeCode128)->getBarcode($code), 600, 120);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** High error correction, as the source used — labels get scuffed. */
    public function qr(string $data): string
    {
        return (new QrBuilder)->build(
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 240,
            margin: 4,
        )->getDataUri();
    }

    private function courierLogo(?string $courier): ?string
    {
        $gateway = $this->couriers->for($courier);

        if (! $gateway) {
            return null;
        }

        // public/images/couriers/{slug}.png, optional — the label falls back to
        // the courier's name in type when the file is not there.
        $slug = str($gateway->name())->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->value();

        return $this->embed(public_path("images/couriers/{$slug}.png"));
    }

    private function storeLogo(): ?string
    {
        $logo = ImageSetting::query()->logos()->where('sorting', 1)->value('image_path');

        if (blank($logo)) {
            return null;
        }

        return $this->embed(Storage::disk('public')->path($logo));
    }

    /** dompdf is given bytes, never a path or URL it could be steered to fetch. */
    private function embed(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };

        return $mime ? 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path)) : null;
    }
}
