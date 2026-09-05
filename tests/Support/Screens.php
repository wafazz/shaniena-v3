<?php

namespace Tests\Support;

/**
 * The screen inventory the smoke tests walk.
 *
 * It lives here rather than inline in one test so the coverage guard can read
 * the same list: a new admin screen has to be added here, or explicitly
 * recorded as covered by another test, or the suite fails.
 */
class Screens
{
    /** @return array<string, array{0: string, 1: string, 2: string}> slug, path, Inertia component */
    public static function admin(): array
    {
        return [
            'dashboard' => ['dashboard', '/admin/dashboard', 'Admin/Dashboard'],

            'new order queue' => ['new-order', '/admin/new-order', 'Admin/Orders/Queue'],
            'process order queue' => ['process-order', '/admin/process-order', 'Admin/Orders/Queue'],
            'indelivery order queue' => ['indelivery-order', '/admin/indelivery-order', 'Admin/Orders/Queue'],
            'completed order queue' => ['completed-order', '/admin/completed-order', 'Admin/Orders/Queue'],
            'returned order queue' => ['returned-order', '/admin/returned-order', 'Admin/Orders/Queue'],
            'cancelled order queue' => ['cancelled-order', '/admin/cancelled-order', 'Admin/Orders/Queue'],
            'database order queue' => ['database-order', '/admin/database-order', 'Admin/Orders/Queue'],
            'order search' => ['search-order', '/admin/search-order', 'Admin/Orders/Search'],

            'product list' => ['product-list', '/admin/product-list', 'Admin/Products/Index'],
            'stock control' => ['stock-control', '/admin/stock-control', 'Admin/Products/StockControl'],
            'new product' => ['new-product', '/admin/new-product', 'Admin/Products/Form'],
            'categories' => ['category-product', '/admin/category-product', 'Admin/Catalogue/Categories'],
            'brands' => ['brand-product', '/admin/brand-product', 'Admin/Catalogue/Brands'],

            'sliders' => ['slider-setting', '/admin/slider-setting', 'Admin/Content/Sliders'],
            'blog' => ['announcement-blog', '/admin/announcement-blog', 'Admin/Content/Blog'],

            'store settings' => ['store-setting', '/admin/store-setting', 'Admin/Settings/Store'],
            'shipping cost' => ['delivery-charge', '/admin/delivery-charge', 'Admin/Settings/ShippingCost'],
            'payments' => ['payment-setting', '/admin/payment-setting', 'Admin/Settings/Payments'],
            'countries' => ['list-country', '/admin/list-country', 'Admin/Settings/Countries'],
            'dhl' => ['dhl-setting', '/admin/dhl-setting', 'Admin/Settings/Dhl'],
            'jt express' => ['jt-express', '/admin/jt-express', 'Admin/Settings/JtExpress'],
            'policy' => ['setting-policy', '/admin/setting-policy', 'Admin/Settings/PageContent'],
            'terms' => ['setting-terms', '/admin/setting-terms', 'Admin/Settings/PageContent'],
            'about us' => ['setting-about-us', '/admin/setting-about-us', 'Admin/Settings/PageContent'],
            'logo' => ['logo-setting', '/admin/logo-setting', 'Admin/Settings/Logo'],

            'pickup hubs' => ['pickup-hub', '/admin/pickup-hub', 'Admin/Logistics/PickupHubs'],
            'support tickets' => ['support/tickets', '/admin/support/tickets', 'Admin/Support/Tickets'],
            'sales report' => ['sales-report', '/admin/sales-report', 'Admin/Reports/Sales'],
            'activity log' => ['activity-log', '/admin/activity-log', 'Admin/Reports/ActivityLog'],
            'hq staff' => ['hq-staff', '/admin/hq-staff', 'Admin/Staff/Index'],
        ];
    }

    /**
     * Guarded admin GET routes the smoke walk deliberately skips, each with
     * the test that does cover it. A route that is in neither list fails the
     * coverage guard — which is the point.
     *
     * @return array<string, string>
     */
    public static function adminCoveredElsewhere(): array
    {
        return [
            // Needs a record to open, so it is tested with one.
            'admin/products/{product}/edit' => 'ProductFormTest',
            'admin/support/tickets/{ticket}' => 'TicketReplyMailTest',
            'admin/hq-staff/{staff}' => 'StaffManagementTest',
            'admin/countries/{country}/states' => 'StoreSettingsTest',
            'admin/orders/{order}/detail' => 'OrderDetailTest',
            'admin/support/tickets/{ticket}/attachments/{attachment}' => 'SupportAttachmentTest',

            // Not Inertia screens: JSON, a PDF and a CSV.
            'admin/dashboard/live' => 'ScheduledJobsTest',
            'admin/orders/postcode' => 'OrderDetailTest',
            'admin/orders/{order}/awb' => 'AwbPrintTest',
            'admin/orders/export' => 'OrderExportTest',
        ];
    }

    /**
     * Pages that show one shopper's own state and must never be indexed.
     *
     * @return list<string>
     */
    public static function storefrontNoIndex(): array
    {
        return ['Cart', 'Checkout', 'Track', 'Support', 'Account', 'Thanks', 'PaymentFailed', 'Handoff'];
    }

    /**
     * Storefront pages a stranger can open. Every one must render for a guest
     * with an empty catalogue — an empty shop is what a new deployment looks
     * like, and it is where "undefined index" bugs live.
     *
     * @return array<string, array{0: string, 1: string}> path, Inertia component
     */
    public static function storefront(): array
    {
        return [
            'home' => ['/', 'Shop/Home'],
            'shop' => ['/shop', 'Shop/Listing'],
            'promos' => ['/promo-item', 'Shop/Listing'],
            'cart' => ['/cart', 'Shop/Cart'],
            'checkout' => ['/checkout', 'Shop/Checkout'],
            'track order' => ['/track-order', 'Shop/Track'],
            'support' => ['/support', 'Shop/Support'],
            'blog' => ['/blog', 'Shop/Blog'],
            'contact' => ['/contact', 'Shop/Contact'],
            'about' => ['/about', 'Shop/Page'],
            'policy' => ['/policy', 'Shop/Page'],
            'terms' => ['/terms', 'Shop/Page'],
            // /change-country is a redirect helper; this is the screen itself.
            'select country' => ['/select-country', 'Shop/SelectCountry'],
            'login' => ['/login', 'Shop/Auth/Login'],
            'register' => ['/register', 'Shop/Auth/Register'],
        ];
    }
}
