<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShipmentFailed;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Shipping\AwbLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AwbPrintController extends Controller
{
    public function __construct(private AwbLabel $labels) {}

    public function show(Request $request, Order $order): HttpResponse
    {
        return $this->render($request, collect([$order]), 'awb-'.$order->id.'.pdf');
    }

    /**
     * Bulk print. Ids arrive in the body, not the query string as the source
     * had them — `?id=1,2,3` was interpolated straight into SQL, and the whole
     * batch was marked printed before a single label had rendered.
     */
    public function bulk(Request $request): HttpResponse
    {
        $data = $request->validate([
            'orders' => ['required', 'array', 'min:1', 'max:200'],
            'orders.*' => ['integer'],
        ]);

        $ids = array_values(array_unique($data['orders']));

        $orders = Order::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        return $this->render($request, $orders, 'awb-batch-'.now()->format('Ymd-His').'.pdf');
    }

    /** @param  Collection<int, Order>  $orders */
    private function render(Request $request, $orders, string $filename): HttpResponse
    {
        try {
            $pdf = $this->labels->pdf($orders, (int) $request->user('admin')->getKey());
        } catch (ShipmentFailed $e) {
            return new Response($e->getMessage(), 422, ['Content-Type' => 'text/plain']);
        }

        return $pdf->stream($filename);
    }
}
