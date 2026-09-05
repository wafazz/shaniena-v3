<?php

namespace App\Services\Shipping;

use App\Models\JtSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a parcel's latest scan from J&T.
 *
 * Two things the source did are not reproduced: the signing key was hardcoded
 * in the cron script, and TLS peer verification was switched off, which made
 * every tracking call interceptable.
 */
class JtTracking
{
    public function isConfigured(): bool
    {
        return filled($this->key());
    }

    /** The most recent scan status for an AWB, or null if J&T has nothing. */
    public function latestStatus(string $awb): ?string
    {
        $json = json_encode([
            'queryType' => 1,
            'language' => 2,
            'queryCodes' => [$awb],
        ], JSON_UNESCAPED_UNICODE);

        // base64 of the HEX digest, as the working integration signs it.
        $response = Http::asForm()->timeout(20)->post(config('shop.tracking.jt_url'), [
            'logistics_interface' => $json,
            'data_digest' => base64_encode(md5($json.$this->key())),
            'msg_type' => 'TRACK',
            'eccompanyid' => config('shop.tracking.jt_company_id'),
        ]);

        if ($response->failed()) {
            Log::warning('J&T tracking call failed.', ['awb' => $awb, 'status' => $response->status()]);

            return null;
        }

        // The source indexed straight into this and fatal'd on any other shape.
        $status = $response->json('responseitems.data.0.details.0.scanstatus');

        return filled($status) ? (string) $status : null;
    }

    private function key(): ?string
    {
        return config('shop.tracking.jt_key') ?: JtSetting::current()?->credentials()['key'];
    }
}
