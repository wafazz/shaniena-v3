<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Themes;
use App\Services\StoreSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingController extends Controller
{
    /**
     * Keys the admin screen owns. The table is free-form key/value, so an
     * allowlist keeps a typo from creating a silent orphan setting.
     */
    private const KEYS = [
        'store_name' => ['label' => 'Store name', 'type' => 'text'],
        'store_email' => ['label' => 'Contact email', 'type' => 'email'],
        'store_phone' => ['label' => 'Contact phone', 'type' => 'text'],
        'store_address' => ['label' => 'Address', 'type' => 'textarea'],
        'facebook_url' => ['label' => 'Facebook', 'type' => 'url'],
        'instagram_url' => ['label' => 'Instagram', 'type' => 'url'],
        'whatsapp_number' => ['label' => 'WhatsApp number', 'type' => 'text'],
        'cod_enabled' => ['label' => 'Cash on delivery', 'type' => 'toggle'],
        'senangpay_enabled' => ['label' => 'SenangPay', 'type' => 'toggle'],
        'bayarcash_enabled' => ['label' => 'Bayarcash', 'type' => 'toggle'],
        'billplz_enabled' => ['label' => 'Billplz', 'type' => 'toggle'],
        'stripe_enabled' => ['label' => 'Stripe', 'type' => 'toggle'],
        'low_stock_threshold' => ['label' => 'Low stock warning below', 'type' => 'number'],
        // Rendered as a picker of its own, not as a text field.
        Themes::SETTING => ['label' => 'Storefront theme', 'type' => 'theme'],
    ];

    public function edit(StoreSettings $settings): Response
    {
        $current = $settings->all();

        return Inertia::render('Admin/Settings/Store', [
            'fields' => collect(self::KEYS)->map(fn (array $meta, string $key) => $meta + [
                'key' => $key,
                'value' => $current[$key] ?? match ($meta['type']) {
                    'toggle' => '0',
                    'theme' => Themes::DEFAULT,
                    default => '',
                },
            ])->values()->all(),
            'themes' => Themes::choices(),
        ]);
    }

    public function update(Request $request, StoreSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:5000'],
            // The theme names a Vite entry and a stylesheet, so it is an
            // allowlist rather than free text.
            'settings.'.Themes::SETTING => ['nullable', Rule::in(Themes::keys())],
        ])['settings'];

        $settings->setMany(array_intersect_key($data, self::KEYS));

        return back()->with('success', 'Store settings saved.');
    }
}
