<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\BayarcashSetting;
use App\Models\SenangPaySetting;
use App\Models\StripeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gateway credentials.
 *
 * Secrets are never sent to the browser — the screen shows only whether each
 * one is set, plus a masked tail. The source rendered live secret keys into
 * `value="..."` on the settings page, so anyone who could open it (or read the
 * HTML from a cached page) had the production keys.
 *
 * A blank field on save means "leave this one alone", so an operator changing
 * the mode does not have to retype every secret.
 */
class PaymentSettingController extends Controller
{
    public function edit(): Response
    {
        $senangpay = SenangPaySetting::current();
        $bayarcash = BayarcashSetting::current();
        $stripe = StripeSetting::current();

        return Inertia::render('Admin/Settings/Payments', [
            'senangpay' => [
                'type' => $senangpay?->type ?? SenangPaySetting::MODE_SANDBOX,
                'merchant_id' => $senangpay?->merchant_id,
                'pro_merchant_id' => $senangpay?->pro_merchant_id,
                'secret_key' => $this->mask($senangpay?->secret_key),
                'pro_secret_key' => $this->mask($senangpay?->pro_secret_key),
            ],
            'bayarcash' => [
                'type' => $bayarcash?->type ?? BayarcashSetting::MODE_SANDBOX,
                'sandbox_api_token' => $this->mask($bayarcash?->sandbox_api_token),
                'sandbox_secret_key' => $this->mask($bayarcash?->sandbox_secret_key),
                'sandbox_portal_key' => $this->mask($bayarcash?->sandbox_portal_key),
                'api_token' => $this->mask($bayarcash?->api_token),
                'secret_key' => $this->mask($bayarcash?->secret_key),
                'portal_key' => $this->mask($bayarcash?->portal_key),
            ],
            'stripe' => [
                'publish_key' => $stripe?->publish_key,
                'secret_key' => $this->mask($stripe?->secret_key),
                'webhook_secret' => $this->mask($stripe?->webhook_secret),
            ],
        ]);
    }

    public function updateSenangPay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([SenangPaySetting::MODE_SANDBOX, SenangPaySetting::MODE_PRODUCTION])],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'pro_merchant_id' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'pro_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $row = SenangPaySetting::current() ?? new SenangPaySetting;
        $row->fill($this->withoutBlanks($data, ['secret_key', 'pro_secret_key']));
        $row->save();

        $this->log($request, 'SenangPay');

        return back()->with('success', 'SenangPay settings saved.');
    }

    public function updateBayarcash(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([BayarcashSetting::MODE_SANDBOX, BayarcashSetting::MODE_PRODUCTION])],
            'sandbox_api_token' => ['nullable', 'string'],
            'sandbox_secret_key' => ['nullable', 'string'],
            'sandbox_portal_key' => ['nullable', 'string'],
            'api_token' => ['nullable', 'string'],
            'secret_key' => ['nullable', 'string'],
            'portal_key' => ['nullable', 'string'],
        ]);

        $row = BayarcashSetting::current() ?? new BayarcashSetting;
        $row->fill($this->withoutBlanks($data, [
            'sandbox_api_token', 'sandbox_secret_key', 'sandbox_portal_key',
            'api_token', 'secret_key', 'portal_key',
        ]));
        $row->save();

        $this->log($request, 'Bayarcash');

        return back()->with('success', 'Bayarcash settings saved.');
    }

    public function updateStripe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'publish_key' => ['required', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $row = StripeSetting::current() ?? new StripeSetting;
        $row->fill($this->withoutBlanks($data, ['secret_key', 'webhook_secret']));
        $row->save();

        $this->log($request, 'Stripe');

        return back()->with('success', 'Stripe settings saved.');
    }

    /**
     * Drop the secret fields the operator left blank so an unchanged secret is
     * never overwritten with an empty string.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $secrets
     * @return array<string, mixed>
     */
    private function withoutBlanks(array $data, array $secrets): array
    {
        foreach ($secrets as $key) {
            if (blank($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_repeat('•', 8).substr($value, -4);
    }

    private function log(Request $request, string $gateway): void
    {
        Activity::record(
            (int) $request->user('admin')->getKey(),
            "Updated {$gateway} payment settings",
            'payment_settings',
            'settings_activity',
        );
    }
}
