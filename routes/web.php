<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\PasswordResetController;
use App\Http\Controllers\Admin\AwbPrintController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CountrySettingController;
use App\Http\Controllers\Admin\CourierSettingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LiveFeedController;
use App\Http\Controllers\Admin\LogoSettingController;
use App\Http\Controllers\Admin\OrderQueueController;
use App\Http\Controllers\Admin\OrderSearchController;
use App\Http\Controllers\Admin\OrderStatusController;
use App\Http\Controllers\Admin\PageContentController;
use App\Http\Controllers\Admin\PaymentSettingController;
use App\Http\Controllers\Admin\PickupHubController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ShippingCostController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StockControlController;
use App\Http\Controllers\Admin\StoreSettingController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\Auth\CustomerAuthController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogueController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\ContentController;
use App\Http\Controllers\Shop\CountryController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderTrackingController;
use App\Http\Controllers\Shop\PaymentController;
use App\Http\Controllers\Shop\ProductController as ShopProductController;
use App\Http\Controllers\Shop\SupportController;
use App\Services\OrderQueues;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

// The country gate is a page, not a wall. The source bounced every storefront
// URL back to it until a cookie was set, so shared product links and crawlers
// both landed on a country picker.
Route::get('select-country', [CountryController::class, 'show'])->name('shop.country');
Route::post('select-country', [CountryController::class, 'store'])->name('shop.country.store');
Route::get('change-country', [CountryController::class, 'change'])->name('shop.country.change');

// Slug routing throughout — the source used /product-details/12.
Route::get('product/{product:slug}', ShopProductController::class)->name('shop.product');
Route::get('categories/{category:slug}', [CatalogueController::class, 'category'])->name('shop.category');
Route::get('brands/{brand:slug}', [CatalogueController::class, 'brand'])->name('shop.brand');
Route::get('promo-item', [CatalogueController::class, 'promos'])->name('shop.promos');
Route::get('shop', [CatalogueController::class, 'search'])->name('shop.search');

Route::get('cart', [CartController::class, 'show'])->name('shop.cart');
Route::post('cart', [CartController::class, 'store'])->name('shop.cart.store');
Route::put('cart/{line}', [CartController::class, 'update'])->name('shop.cart.update');
Route::delete('cart/{line}', [CartController::class, 'destroy'])->name('shop.cart.destroy');

Route::get('checkout', [CheckoutController::class, 'show'])->name('shop.checkout');
Route::post('checkout/address', [CheckoutController::class, 'address'])->name('shop.checkout.address');

Route::get('track-order', OrderTrackingController::class)->name('shop.track');
Route::get('blog', [ContentController::class, 'blog'])->name('shop.blog');
Route::get('blog/{post}', [ContentController::class, 'post'])->name('shop.post');
Route::get('contact', [ContentController::class, 'contact'])->name('shop.contact');

// --- customer accounts ---------------------------------------------------
Route::middleware('guest:web')->group(function () {
    Route::get('login', [CustomerAuthController::class, 'showLogin'])->name('shop.login');
    Route::post('login', [CustomerAuthController::class, 'login'])->name('shop.login.store');
    Route::get('register', [CustomerAuthController::class, 'showRegister'])->name('shop.register');
    Route::post('register', [CustomerAuthController::class, 'register'])->name('shop.register.store');
});

Route::get('verify-email', [CustomerAuthController::class, 'showVerify'])->name('shop.verify');
Route::post('verify-email', [CustomerAuthController::class, 'verify'])->name('shop.verify.store');
Route::post('verify-email/resend', [CustomerAuthController::class, 'resend'])->name('shop.verify.resend');
Route::post('logout', [CustomerAuthController::class, 'logout'])->name('shop.logout');

Route::middleware('auth:web')->group(function () {
    Route::get('account', [AccountController::class, 'show'])->name('shop.account');
    Route::put('account', [AccountController::class, 'update'])->name('shop.account.update');
});

// --- payment -------------------------------------------------------------
Route::post('pay/{channel}', [PaymentController::class, 'start'])->name('shop.pay');
Route::get('pay/{channel}/return', [PaymentController::class, 'return'])->name('shop.pay.return');
Route::get('order/{order}/thanks', [PaymentController::class, 'thanks'])->name('shop.order.thanks');
Route::get('order/{order}/failed', [PaymentController::class, 'failed'])->name('shop.order.failed');

// --- support -------------------------------------------------------------
Route::get('support', [SupportController::class, 'show'])->name('shop.support');
Route::post('support', [SupportController::class, 'store'])->name('shop.support.store');
Route::post('support/reply', [SupportController::class, 'reply'])->name('shop.support.reply');

foreach (['about', 'policy', 'terms'] as $page) {
    Route::get($page, [ContentController::class, 'page'])->defaults('page', $page)->name("shop.page.{$page}");
}

/*
|--------------------------------------------------------------------------
| Admin console
|--------------------------------------------------------------------------
|
| Every authenticated route carries its role_access slug through the `page`
| middleware — the source relied on ad-hoc roleVerify() calls inside views,
| which was easy to forget and is what Phase 7.4 has to close.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');

        Route::get('forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        // Own profile and password: no role_access slug, because every admin
        // can always reach their own account. The source did the same.
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('password', [ProfileController::class, 'editPassword'])->name('password');
        Route::put('password', [ProfileController::class, 'updatePassword'])->name('password.update');

        Route::get('dashboard', DashboardController::class)
            ->middleware('page:dashboard')
            ->name('dashboard');

        Route::get('dashboard/live', LiveFeedController::class)
            ->middleware('page:dashboard')
            ->name('dashboard.live');

        Route::get('search-order', OrderSearchController::class)
            ->middleware('page:search-order')
            ->name('orders.search');

        // One controller, seven routes — each carries its own role_access slug
        // so a Staff Logistic account granted only `new-order` cannot reach
        // `database-order` by typing the URL.
        foreach (OrderQueues::slugs() as $slug) {
            Route::get($slug, [OrderQueueController::class, 'index'])
                ->defaults('queue', $slug)
                ->middleware("page:{$slug}")
                ->name('orders.'.$slug);
        }

        Route::post('orders/{order}/status', [OrderStatusController::class, 'update'])
            ->middleware('page:new-order')
            ->name('orders.status');

        Route::post('orders/status', [OrderStatusController::class, 'bulkUpdate'])
            ->middleware('page:new-order')
            ->name('orders.status.bulk');

        Route::post('orders/{order}/ship', [ShipmentController::class, 'store'])
            ->middleware('page:new-order')
            ->name('orders.ship');

        Route::post('orders/ship', [ShipmentController::class, 'bulk'])
            ->middleware('page:new-order')
            ->name('orders.ship.bulk');

        Route::get('orders/{order}/awb', [AwbPrintController::class, 'show'])
            ->middleware('page:new-order')
            ->name('orders.awb');

        Route::post('orders/awb', [AwbPrintController::class, 'bulk'])
            ->middleware('page:new-order')
            ->name('orders.awb.bulk');

        Route::get('stock-control', [StockControlController::class, 'index'])
            ->middleware('page:stock-control')
            ->name('stock.index');

        Route::post('stock-control/{variant}/adjust', [StockControlController::class, 'adjust'])
            ->middleware('page:stock-control')
            ->name('stock.adjust');

        Route::middleware('page:new-product')->group(function () {
            Route::get('new-product', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            // Bound by id, not slug: Product::getRouteKeyName() is `slug` for
            // storefront URLs, but the slug is editable on this very form —
            // saving a new one would move the page out from under the operator.
            Route::get('products/{product:id}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('products/{product:id}', [ProductController::class, 'update'])->name('products.update');
        });

        // Admin routes bind by id throughout. Category, Brand, Product,
        // PickupHub and SupportTicket all set getRouteKeyName() to a
        // slug/code for public URLs — and those values are editable on the
        // very screens that save them.
        // --- catalogue --------------------------------------------------
        Route::middleware('page:category-product')->group(function () {
            Route::get('category-product', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::post('categories/{category:id}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category:id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        });

        Route::middleware('page:brand-product')->group(function () {
            Route::get('brand-product', [BrandController::class, 'index'])->name('brands.index');
            Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
            Route::post('brands/{brand:id}', [BrandController::class, 'update'])->name('brands.update');
            Route::delete('brands/{brand:id}', [BrandController::class, 'destroy'])->name('brands.destroy');
        });

        // --- content ------------------------------------------------------
        Route::middleware('page:slider-setting')->group(function () {
            Route::get('slider-setting', [SliderController::class, 'index'])->name('sliders.index');
            Route::post('sliders', [SliderController::class, 'store'])->name('sliders.store');
            Route::put('sliders/{slider}', [SliderController::class, 'update'])->name('sliders.update');
            Route::post('sliders/reorder', [SliderController::class, 'reorder'])->name('sliders.reorder');
            Route::delete('sliders/{slider}', [SliderController::class, 'destroy'])->name('sliders.destroy');
        });

        Route::middleware('page:announcement-blog')->group(function () {
            Route::get('announcement-blog', [BlogController::class, 'index'])->name('blog.index');
            Route::post('blog', [BlogController::class, 'store'])->name('blog.store');
            Route::put('blog/{post}', [BlogController::class, 'update'])->name('blog.update');
            Route::delete('blog/{post}', [BlogController::class, 'destroy'])->name('blog.destroy');
        });

        // --- settings -----------------------------------------------------
        Route::middleware('page:store-setting')->group(function () {
            Route::get('store-setting', [StoreSettingController::class, 'edit'])->name('settings.store');
            Route::put('store-setting', [StoreSettingController::class, 'update'])->name('settings.store.update');
        });

        Route::middleware('page:delivery-charge')->group(function () {
            Route::get('delivery-charge', [ShippingCostController::class, 'edit'])->name('settings.shipping');
            Route::post('delivery-charge/postage', [ShippingCostController::class, 'savePostage'])->name('settings.postage.save');
            Route::post('delivery-charge/cod', [ShippingCostController::class, 'saveCod'])->name('settings.cod.save');
        });

        Route::middleware('page:payment-setting')->group(function () {
            Route::get('payment-setting', [PaymentSettingController::class, 'edit'])->name('settings.payments');
            Route::put('payment-setting/senangpay', [PaymentSettingController::class, 'updateSenangPay'])->name('settings.payments.senangpay');
            Route::put('payment-setting/bayarcash', [PaymentSettingController::class, 'updateBayarcash'])->name('settings.payments.bayarcash');
            Route::put('payment-setting/stripe', [PaymentSettingController::class, 'updateStripe'])->name('settings.payments.stripe');
        });

        Route::middleware('page:dhl-setting')->group(function () {
            Route::get('dhl-setting', [CourierSettingController::class, 'dhl'])->name('settings.dhl');
            Route::put('dhl-setting', [CourierSettingController::class, 'updateDhl'])->name('settings.dhl.update');
        });

        Route::middleware('page:jt-express')->group(function () {
            Route::get('jt-express', [CourierSettingController::class, 'jt'])->name('settings.jt');
            Route::put('jt-express', [CourierSettingController::class, 'updateJt'])->name('settings.jt.update');
        });

        // The three single-row storefront pages share one controller.
        foreach (['policy' => 'setting-policy', 'terms' => 'setting-terms', 'about-us' => 'setting-about-us'] as $page => $slug) {
            Route::get($slug, [PageContentController::class, 'edit'])
                ->defaults('page', $page)->middleware("page:{$slug}")->name('pages.'.$page);
            Route::put($slug, [PageContentController::class, 'update'])
                ->defaults('page', $page)->middleware("page:{$slug}")->name('pages.'.$page.'.update');
        }

        Route::middleware('page:logo-setting')->group(function () {
            Route::get('logo-setting', [LogoSettingController::class, 'index'])->name('logo.index');
            Route::post('logo-setting', [LogoSettingController::class, 'store'])->name('logo.store');
            Route::post('logo-setting/{logo:id}/default', [LogoSettingController::class, 'makeDefault'])->name('logo.default');
            Route::delete('logo-setting/{logo:id}', [LogoSettingController::class, 'destroy'])->name('logo.destroy');
        });

        Route::middleware('page:list-country')->group(function () {
            Route::get('list-country', [CountrySettingController::class, 'index'])->name('countries.index');
            Route::post('countries', [CountrySettingController::class, 'store'])->name('countries.store');
            Route::put('countries/{country}', [CountrySettingController::class, 'update'])->name('countries.update');
            Route::get('countries/{country}/states', [CountrySettingController::class, 'states'])->name('countries.states');
            Route::post('countries/{country}/states', [CountrySettingController::class, 'saveState'])->name('countries.states.save');
        });

        // --- logistics ----------------------------------------------------
        Route::middleware('page:pickup-hub')->group(function () {
            Route::get('pickup-hub', [PickupHubController::class, 'index'])->name('hubs.index');
            Route::post('pickup-hub', [PickupHubController::class, 'store'])->name('hubs.store');
            Route::put('pickup-hub/{hub:id}', [PickupHubController::class, 'update'])->name('hubs.update');
        });

        // --- support & reporting -------------------------------------------
        Route::middleware('page:support/tickets')->group(function () {
            Route::get('support/tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
            Route::get('support/tickets/{ticket:id}', [SupportTicketController::class, 'show'])->name('tickets.show');
            Route::post('support/tickets/{ticket:id}/reply', [SupportTicketController::class, 'reply'])->name('tickets.reply');
        });

        Route::get('sales-report', SalesReportController::class)
            ->middleware('page:sales-report')
            ->name('reports.sales');

        Route::get('activity-log', ActivityLogController::class)
            ->middleware('page:activity-log')
            ->name('reports.activity');

        Route::middleware('page:hq-staff')->group(function () {
            Route::get('hq-staff', [StaffController::class, 'index'])->name('staff.index');
            Route::post('hq-staff', [StaffController::class, 'store'])->name('staff.store');
            Route::get('hq-staff/{staff}', [StaffController::class, 'edit'])->name('staff.edit');
            Route::put('hq-staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
            Route::post('hq-staff/{staff}/permissions', [StaffController::class, 'setPermission'])->name('staff.permissions');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Gateway callbacks
|--------------------------------------------------------------------------
|
| Server-to-server only, so there is no session and no CSRF token to send —
| the exemption is registered in bootstrap/app.php and covers these paths and
| nothing else. Each gateway verifies its own signature before the payload is
| trusted, and confirming an order happens here, never on the browser return.
|
*/

Route::post('payment/callback/{channel}', [PaymentController::class, 'callback'])
    ->name('shop.pay.callback');
