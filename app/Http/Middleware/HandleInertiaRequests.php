<?php

namespace App\Http\Middleware;

use App\Models\MemberHq;
use App\Services\AdminNavigation;
use App\Services\StoreSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $admin = $request->user('admin');

        return [
            ...parent::share($request),

            'auth' => [
                'admin' => $admin instanceof MemberHq ? [
                    'id' => $admin->id,
                    'name' => $admin->full_name,
                    'email' => $admin->email,
                    'role' => $admin->roleLabel(),
                ] : null,
            ],

            // The sidebar is rebuilt per request but only for signed-in
            // admins, and lazily so partial reloads don't pay for it.
            'nav' => $admin instanceof MemberHq
                ? fn () => app(AdminNavigation::class)->for($admin)
                : null,

            // Topbar search is a permitted action, not a nav entry — it moved
            // out of the sidebar, so it needs its own capability flag.
            'can' => [
                'searchOrders' => $admin instanceof MemberHq && $admin->can('access', 'search-order'),
            ],

            'store' => fn () => [
                'name' => app(StoreSettings::class)->get('store_name', 'Shaniena'),
            ],

            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
