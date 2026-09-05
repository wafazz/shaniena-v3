<?php

namespace App\Services;

use App\Models\MemberHq;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the admin sidebar for one signed-in staff member.
 *
 * Replaces view/Admin/01-menu.php, which called roleVerify() once per menu
 * item — around 30 cached lookups per request — and menuOrderCount(), which
 * ran six `SELECT *` queries and counted the returned rows. At 20k+ orders
 * that made the sidebar the most expensive thing on every page load.
 */
class AdminNavigation
{
    public const COUNTS_CACHE_KEY = 'admin:nav:order-counts';

    /** Short window: the queue badges are the operator's work signal. */
    public const COUNTS_CACHE_TTL = 30;

    public function __construct(private PageAccess $access) {}

    /**
     * The nav tree, verbatim from the source menu. `badge` names the order
     * status whose live count sits against the item.
     *
     * @return list<array<string, mixed>>
     */
    public function definition(): array
    {
        return [
            ['type' => 'item', 'slug' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'cilSpeedometer'],
            // The source split this in two — sales-stats (charts) and
            // sales-report (tables). One screen now carries both, so there is
            // no second page to link to.
            ['type' => 'item', 'slug' => 'sales-report', 'label' => 'Sales Report', 'icon' => 'cilChartLine'],

            ['type' => 'group', 'label' => 'Sales/Order', 'icon' => 'cilCart', 'items' => [
                // `working` marks a queue an operator is expected to clear.
                // Returned/Cancelled/Completed are outcomes, not work — showing
                // them as loud as a full New Order queue trains people to
                // ignore the colour entirely.
                ['slug' => 'new-order', 'label' => 'New Order', 'badge' => Order::STATUS_NEW, 'working' => true],
                ['slug' => 'process-order', 'label' => 'Process Order', 'badge' => Order::STATUS_PROCESSING, 'working' => true],
                ['slug' => 'indelivery-order', 'label' => 'Indelivery Order', 'badge' => Order::STATUS_IN_DELIVERY, 'working' => true],
                ['slug' => 'completed-order', 'label' => 'Completed Order', 'badge' => Order::STATUS_COMPLETED],
                ['slug' => 'returned-order', 'label' => 'Returned Order', 'badge' => Order::STATUS_RETURNED],
                ['slug' => 'cancelled-order', 'label' => 'Cancelled Order', 'badge' => Order::STATUS_CANCELLED],
                // No badge: the source indexed 7 slugs against a 6-element
                // count array, so this one always read an undefined slot.
                ['slug' => 'database-order', 'label' => 'Database Order'],
            ]],

            ['type' => 'group', 'label' => 'Manage Product', 'icon' => 'cilLayers', 'items' => [
                ['slug' => 'category-product', 'label' => 'Category Product'],
                ['slug' => 'brand-product', 'label' => 'Brand Product'],
                ['slug' => 'new-product', 'label' => 'New Product'],
                ['slug' => 'stock-control', 'label' => 'Stock Control'],
            ]],

            ['type' => 'title', 'label' => 'Settings'],

            ['type' => 'group', 'label' => 'Mandatory Setting', 'icon' => 'cilSettings', 'items' => [
                // One screen covers SenangPay, Bayarcash and Stripe; the source
                // had a separate page per gateway repeating the same form.
                ['slug' => 'payment-setting', 'label' => 'Payment Settings'],
                ['slug' => 'dhl-setting', 'label' => 'DHL Setting'],
                ['slug' => 'jt-express', 'label' => 'J&T Express'],
                // NinjaVan and PosLaju have no settings table anywhere in the
                // source, so there is no screen to link to yet.
                ['slug' => 'setting-policy', 'label' => 'Policy'],
                ['slug' => 'setting-terms', 'label' => 'Terms'],
                ['slug' => 'setting-about-us', 'label' => 'About Us'],
                ['slug' => 'logo-setting', 'label' => 'Logo Setting'],
                ['slug' => 'slider-setting', 'label' => 'Slider Setting'],
                ['slug' => 'store-setting', 'label' => 'Store Setting'],
            ]],

            ['type' => 'item', 'slug' => 'delivery-charge', 'label' => 'Shipping Cost', 'icon' => 'cilTruck'],
            ['type' => 'item', 'slug' => 'pickup-hub', 'label' => 'Pickup Hubs', 'icon' => 'cilGlobeAlt'],

            ['type' => 'group', 'label' => 'Multi Country', 'icon' => 'cilGlobeAlt', 'items' => [
                // Adding a country happens inline on the list, so the separate
                // add-new-country page is gone.
                ['slug' => 'list-country', 'label' => 'List Country'],
            ]],

            ['type' => 'title', 'label' => 'Support'],
            ['type' => 'item', 'slug' => 'support/tickets', 'label' => 'Support Tickets', 'icon' => 'cilCommentSquare'],

            ['type' => 'title', 'label' => 'Account'],
            ['type' => 'item', 'slug' => 'hq-staff', 'label' => 'HQ Staff', 'icon' => 'cilPeople'],
            ['type' => 'item', 'slug' => 'announcement-blog', 'label' => 'Announcement & Blog', 'icon' => 'cilBullhorn'],
            ['type' => 'item', 'slug' => 'activity-log', 'label' => 'Activity Log', 'icon' => 'cilDescription'],
            // Profile and Password were never role-gated in the source.
            ['type' => 'item', 'slug' => 'profile', 'label' => 'Profile', 'icon' => 'cilUser', 'always' => true],
            ['type' => 'item', 'slug' => 'password', 'label' => 'Password', 'icon' => 'cilLockLocked', 'always' => true],
        ];
    }

    /**
     * The nav this user may actually see, with badge counts attached.
     * Groups whose every child is denied are dropped, and so are the section
     * titles that would be left standing over nothing.
     *
     * @return list<array<string, mixed>>
     */
    public function for(MemberHq $user): array
    {
        $counts = $this->orderCounts();
        $built = [];

        foreach ($this->definition() as $entry) {
            $node = match ($entry['type']) {
                'title' => $entry,
                'item' => $this->buildItem($entry, $user, $counts),
                'group' => $this->buildGroup($entry, $user, $counts),
            };

            if ($node !== null) {
                $built[] = $node;
            }
        }

        return $this->dropEmptyTitles($built);
    }

    /**
     * Live order counts per status — one grouped query, replacing the six
     * `SELECT *` scans menuOrderCount() ran on every page load.
     *
     * @return array<int, int>
     */
    public function orderCounts(): array
    {
        return Cache::remember(self::COUNTS_CACHE_KEY, self::COUNTS_CACHE_TTL, fn () => DB::table('customer_orders')
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->pluck(DB::raw('COUNT(*)'), 'status')
            ->map(fn ($n) => (int) $n)
            ->all());
    }

    public function flushCounts(): void
    {
        Cache::forget(self::COUNTS_CACHE_KEY);
    }

    /** @return array<string, mixed>|null */
    private function buildItem(array $entry, MemberHq $user, array $counts): ?array
    {
        if (! ($entry['always'] ?? false) && ! $this->access->allows($user, $entry['slug'])) {
            return null;
        }

        $item = [
            'type' => 'item',
            'slug' => $entry['slug'],
            'label' => $entry['label'],
            'icon' => $entry['icon'] ?? null,
        ];

        if (isset($entry['badge'])) {
            $item['count'] = $counts[$entry['badge']] ?? 0;
            $item['working'] = (bool) ($entry['working'] ?? false);
        }

        return $item;
    }

    /** @return array<string, mixed>|null */
    private function buildGroup(array $entry, MemberHq $user, array $counts): ?array
    {
        $children = [];

        foreach ($entry['items'] as $child) {
            $built = $this->buildItem($child + ['type' => 'item'], $user, $counts);

            if ($built !== null) {
                $children[] = $built;
            }
        }

        if ($children === []) {
            return null;
        }

        return [
            'type' => 'group',
            'label' => $entry['label'],
            'icon' => $entry['icon'] ?? null,
            'items' => $children,
        ];
    }

    /**
     * A section title with no surviving entries under it is noise.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function dropEmptyTitles(array $nodes): array
    {
        $kept = [];

        foreach ($nodes as $i => $node) {
            if ($node['type'] !== 'title') {
                $kept[] = $node;

                continue;
            }

            $next = $nodes[$i + 1] ?? null;

            if ($next !== null && $next['type'] !== 'title') {
                $kept[] = $node;
            }
        }

        return $kept;
    }
}
