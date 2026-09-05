<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\DhlSetting;
use App\Models\JtSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Courier API credentials.
 *
 * As with payments, secrets are masked out on the way to the browser and a
 * blank field means "keep what is stored".
 *
 * NinjaVan and PosLaju have no settings screen: `ninjavan_setting` and
 * `poslaju_setting` have no DDL anywhere in the source (see the plan's Schema
 * Gap), so there is nothing to read or write yet.
 */
class CourierSettingController extends Controller
{
    public function dhl(): Response
    {
        $row = DhlSetting::current();

        return Inertia::render('Admin/Settings/Dhl', [
            'settings' => [
                'production_sandbox' => (int) ($row?->production_sandbox ?? DhlSetting::MODE_SANDBOX),
                'clientid' => $row?->clientid,
                'clientid_test' => $row?->clientid_test,
                'url' => $row?->url,
                'url_test' => $row?->url_test,
                'password' => $this->mask($row?->password),
                'password_test' => $this->mask($row?->password_test),
            ],
        ]);
    }

    public function updateDhl(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // DHL encodes 1 = production, 2 = sandbox — the opposite of J&T.
            'production_sandbox' => ['required', 'integer', 'in:'.DhlSetting::MODE_PRODUCTION.','.DhlSetting::MODE_SANDBOX],
            'clientid' => ['nullable', 'string', 'max:255'],
            'clientid_test' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'url_test' => ['nullable', 'url', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'password_test' => ['nullable', 'string', 'max:255'],
        ]);

        $row = DhlSetting::current() ?? new DhlSetting(['id' => 1]);
        $row->fill($this->withoutBlanks($data, ['password', 'password_test']) + ['format' => $row->format ?? 'json', 'format_test' => $row->format_test ?? 'json']);
        $row->save();

        $this->log($request, 'DHL');

        return back()->with('success', 'DHL settings saved.');
    }

    public function jt(): Response
    {
        $row = JtSetting::current();

        return Inertia::render('Admin/Settings/JtExpress', [
            'settings' => [
                'production_sandbox' => (int) ($row?->production_sandbox ?? JtSetting::MODE_SANDBOX),
                'url_sandbox' => $row?->url_sandbox,
                'username_sanbox' => $row?->username_sanbox,
                'cuscode_sandbox' => $row?->cuscode_sandbox,
                'url_production' => $row?->url_production,
                'username_production' => $row?->username_production,
                'cuscode_production' => $row?->cuscode_production,
                'password_sandbox' => $this->mask($row?->password_sandbox),
                'key_sandbox' => $this->mask($row?->key_sandbox),
                'password_production' => $this->mask($row?->password_production),
                'key_production' => $this->mask($row?->key_production),
            ],
        ]);
    }

    public function updateJt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // J&T encodes 0 = sandbox, 1 = production.
            'production_sandbox' => ['required', 'integer', 'in:'.JtSetting::MODE_SANDBOX.','.JtSetting::MODE_PRODUCTION],
            'url_sandbox' => ['nullable', 'url', 'max:255'],
            'username_sanbox' => ['nullable', 'string', 'max:50'],
            'cuscode_sandbox' => ['nullable', 'string', 'max:50'],
            'url_production' => ['nullable', 'url', 'max:255'],
            'username_production' => ['nullable', 'string', 'max:50'],
            'cuscode_production' => ['nullable', 'string', 'max:50'],
            'password_sandbox' => ['nullable', 'string', 'max:50'],
            'key_sandbox' => ['nullable', 'string', 'max:100'],
            'password_production' => ['nullable', 'string'],
            'key_production' => ['nullable', 'string', 'max:100'],
        ]);

        $row = JtSetting::current() ?? new JtSetting(['id' => 1]);
        $row->fill($this->withoutBlanks($data, [
            'password_sandbox', 'key_sandbox', 'password_production', 'key_production',
        ]));
        $row->save();

        $this->log($request, 'J&T Express');

        return back()->with('success', 'J&T Express settings saved.');
    }

    /**
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
        return blank($value) ? null : str_repeat('•', 8).substr($value, -4);
    }

    private function log(Request $request, string $courier): void
    {
        Activity::record(
            (int) $request->user('admin')->getKey(),
            "Updated {$courier} courier settings",
            'courier_settings',
            'settings_activity',
        );
    }
}
