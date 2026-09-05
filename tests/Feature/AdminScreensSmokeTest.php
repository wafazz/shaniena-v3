<?php

use App\Models\Activity;
use App\Models\CsTicketReply;
use App\Models\DhlSetting;
use App\Models\ImageSetting;
use App\Models\JtSetting;
use App\Models\MemberHq;
use App\Models\NewsBlog;
use App\Models\Order;
use App\Models\PickupHub;
use App\Models\Policy;
use App\Models\Slider;
use App\Models\SupportTicket;
use App\Models\TermsConditions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Every GET screen an admin can reach, and the component each must render. */
dataset('admin screens', [
    'dashboard' => ['dashboard', '/admin/dashboard', 'Admin/Dashboard'],
    'new order queue' => ['new-order', '/admin/new-order', 'Admin/Orders/Queue'],
    'order search' => ['search-order', '/admin/search-order', 'Admin/Orders/Search'],
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
    'pickup hubs' => ['pickup-hub', '/admin/pickup-hub', 'Admin/Logistics/PickupHubs'],
    'support tickets' => ['support/tickets', '/admin/support/tickets', 'Admin/Support/Tickets'],
    'sales report' => ['sales-report', '/admin/sales-report', 'Admin/Reports/Sales'],
    'activity log' => ['activity-log', '/admin/activity-log', 'Admin/Reports/ActivityLog'],
    'hq staff' => ['hq-staff', '/admin/hq-staff', 'Admin/Staff/Index'],
    'dhl' => ['dhl-setting', '/admin/dhl-setting', 'Admin/Settings/Dhl'],
    'jt express' => ['jt-express', '/admin/jt-express', 'Admin/Settings/JtExpress'],
    'policy' => ['setting-policy', '/admin/setting-policy', 'Admin/Settings/PageContent'],
    'terms' => ['setting-terms', '/admin/setting-terms', 'Admin/Settings/PageContent'],
    'about us' => ['setting-about-us', '/admin/setting-about-us', 'Admin/Settings/PageContent'],
    'logo' => ['logo-setting', '/admin/logo-setting', 'Admin/Settings/Logo'],
]);

it('renders for an admin who has the slug', function (string $slug, string $path, string $component) {
    $this->actingAs(queueAdmin($slug), 'admin')->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with('admin screens');

it('403s for an admin who does not', function (string $slug, string $path) {
    // The control admin holds `dashboard`, so that one screen is legitimately open to them.
    if ($slug === 'dashboard') {
        expect(true)->toBeTrue();

        return;
    }

    $this->actingAs(queueAdmin('dashboard'), 'admin')->get($path)->assertForbidden();
})->with('admin screens');

// --- content -------------------------------------------------------------

it('adds a slide at the end of the running order', function () {
    Storage::fake('public');
    $admin = queueAdmin('slider-setting');

    foreach (['one.jpg', 'two.jpg'] as $name) {
        $this->actingAs($admin, 'admin')
            ->post('/admin/sliders', ['image' => UploadedFile::fake()->image($name)])
            ->assertRedirect();
    }

    expect(Slider::orderBy('id')->pluck('sort_order')->all())->toBe([1, 2]);
});

it('saves a dragged slide order', function () {
    Storage::fake('public');
    $admin = queueAdmin('slider-setting');
    $a = Slider::create(['image' => 'a.jpg', 'sort_order' => 1, 'status' => true]);
    $b = Slider::create(['image' => 'b.jpg', 'sort_order' => 2, 'status' => true]);

    $this->actingAs($admin, 'admin')->post('/admin/sliders/reorder', ['order' => [$b->id, $a->id]])->assertRedirect();

    expect($b->fresh()->sort_order)->toBe(1)->and($a->fresh()->sort_order)->toBe(2);
});

it('publishes a blog post against its author', function () {
    $admin = queueAdmin('announcement-blog');

    $this->actingAs($admin, 'admin')
        ->post('/admin/blog', ['title' => 'Raya restock', 'contents' => 'Everything is back in stock.'])
        ->assertRedirect();

    expect(NewsBlog::first()->post_by)->toBe($admin->id);
});

// --- logistics -----------------------------------------------------------

it('rejects a duplicate hub code', function () {
    $admin = queueAdmin('pickup-hub');
    PickupHub::create(['hub_code' => 'KL01', 'hub_name' => 'KL Central', 'status' => 'active']);

    $this->actingAs($admin, 'admin')
        ->post('/admin/pickup-hub', ['hub_code' => 'KL01', 'hub_name' => 'Another', 'status' => 'active'])
        ->assertSessionHasErrors('hub_code');
});

// --- support -------------------------------------------------------------

it('replies to a ticket and logs the status change', function () {
    $admin = queueAdmin('support/tickets');
    $ticket = SupportTicket::create([
        'customer_name' => 'Aisyah', 'customer_email' => 'a@example.test',
        'ticket_no' => 'T-1', 'title' => 'Where is my order?',
        'status' => SupportTicket::STATUS_NEW, 'priority' => SupportTicket::PRIORITY_HIGH,
    ]);

    $this->actingAs($admin, 'admin')->post("/admin/support/tickets/{$ticket->id}/reply", [
        'message' => 'It shipped this morning.',
        'status' => SupportTicket::STATUS_RESOLVED,
    ])->assertRedirect();

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_RESOLVED)
        ->and($ticket->replies()->count())->toBe(1)
        ->and($ticket->replies()->first()->user_type)->toBe(CsTicketReply::FROM_STAFF)
        ->and($ticket->logs()->count())->toBe(1);
});

it('sorts open tickets urgent first', function () {
    $admin = queueAdmin('support/tickets');
    foreach ([['T-low', 'low'], ['T-urgent', 'urgent'], ['T-medium', 'medium']] as [$no, $priority]) {
        SupportTicket::create([
            'customer_name' => 'X', 'customer_email' => 'x@example.test', 'ticket_no' => $no,
            'title' => $no, 'status' => SupportTicket::STATUS_NEW, 'priority' => $priority,
        ]);
    }

    $this->actingAs($admin, 'admin')->get('/admin/support/tickets')
        ->assertInertia(fn ($page) => $page->where('tickets.data.0.ticket_no', 'T-urgent'));
});

// --- reporting -----------------------------------------------------------

it('reports revenue for the requested range only', function () {
    $admin = queueAdmin('sales-report');
    Order::factory()->status(Order::STATUS_COMPLETED)->create(['myr_value_include_postage' => 200, 'created_at' => now()]);
    Order::factory()->status(Order::STATUS_COMPLETED)->create(['myr_value_include_postage' => 999, 'created_at' => now()->subMonths(3)]);

    $this->actingAs($admin, 'admin')
        ->get('/admin/sales-report?from='.now()->subDays(2)->toDateString().'&to='.now()->toDateString())
        ->assertInertia(fn ($page) => $page->where('summary.revenue', 200)->where('summary.orders', 1));
});

it('rejects a range that ends before it starts', function () {
    $this->actingAs(queueAdmin('sales-report'), 'admin')
        ->get('/admin/sales-report?from=2026-09-05&to=2026-09-01')
        ->assertSessionHasErrors('to');
});

it('filters the activity log by staff member', function () {
    $admin = queueAdmin('activity-log');
    $other = MemberHq::create([
        'email' => 'x@example.test', 'password' => bcrypt('x'), 'sec_pin' => '',
        'f_name' => 'Other', 'l_name' => 'Staff', 'phone' => '01', 'role' => 3, 'status' => 1,
    ]);

    Activity::record($admin->id, 'Mine', null, 'test_activity');
    Activity::record($other->id, 'Theirs', null, 'test_activity');

    $this->actingAs($admin, 'admin')->get("/admin/activity-log?user={$admin->id}")
        ->assertInertia(fn ($page) => $page->has('entries.data', 1)
            ->where('entries.data.0.description', 'Mine'));
});

// --- courier & CMS settings ---------------------------------------------

it('never sends a courier password to the browser', function () {
    $admin = queueAdmin('dhl-setting');
    DhlSetting::create([
        'id' => 1, 'production_sandbox' => 1,
        'clientid' => 'live-id', 'password' => 'live-password-secret',
        'format' => 'json', 'url' => 'https://api.dhl.test',
        'clientid_test' => 't', 'password_test' => 'test-password-secret',
        'format_test' => 'json', 'url_test' => 'https://sandbox.dhl.test',
    ]);

    $body = $this->actingAs($admin, 'admin')->get('/admin/dhl-setting')->assertOk()->getContent();

    expect($body)->not->toContain('live-password-secret')->not->toContain('test-password-secret');
});

it('keeps a courier secret when the field is blank', function () {
    $admin = queueAdmin('jt-express');
    JtSetting::create([
        'id' => 1, 'production_sandbox' => 0,
        'url_sandbox' => 'https://sandbox.jt.test', 'username_sanbox' => 'u',
        'password_sandbox' => 'keep-me', 'cuscode_sandbox' => 'c', 'key_sandbox' => 'keep-key',
        'url_production' => 'https://api.jt.test', 'username_production' => 'u',
        'password_production' => 'p', 'cuscode_production' => 'c', 'key_production' => 'k',
    ]);

    $this->actingAs($admin, 'admin')->put('/admin/jt-express', [
        'production_sandbox' => 1, 'password_sandbox' => '', 'key_sandbox' => '',
    ])->assertRedirect();

    $row = JtSetting::current();

    expect($row->production_sandbox)->toBe(1)
        ->and($row->password_sandbox)->toBe('keep-me')
        ->and($row->key_sandbox)->toBe('keep-key');
});

it('saves storefront page copy against the right table', function () {
    $admin = queueAdmin('setting-policy', 'setting-terms');

    $this->actingAs($admin, 'admin')->put('/admin/setting-policy', ['description' => 'Our returns policy.'])->assertRedirect();
    $this->actingAs($admin, 'admin')->put('/admin/setting-terms', ['description' => 'The terms.'])->assertRedirect();

    expect(Policy::content()->description)->toBe('Our returns policy.')
        ->and(TermsConditions::content()->description)->toBe('The terms.');
});

it('makes the first uploaded logo the active one', function () {
    Storage::fake('public');
    $admin = queueAdmin('logo-setting');

    $this->actingAs($admin, 'admin')->post('/admin/logo-setting', ['image' => UploadedFile::fake()->image('logo.png')]);
    expect(ImageSetting::logos()->first()->isDefault())->toBeTrue();

    $this->actingAs($admin, 'admin')->post('/admin/logo-setting', ['image' => UploadedFile::fake()->image('logo2.png')]);
    expect(ImageSetting::logos()->where('sorting', 1)->count())->toBe(1);
});

it('will not delete the logo currently in use', function () {
    Storage::fake('public');
    $admin = queueAdmin('logo-setting');
    $this->actingAs($admin, 'admin')->post('/admin/logo-setting', ['image' => UploadedFile::fake()->image('logo.png')]);

    $logo = ImageSetting::logos()->first();

    $this->actingAs($admin, 'admin')->delete("/admin/logo-setting/{$logo->id}")->assertStatus(422);
    expect(ImageSetting::logos()->count())->toBe(1);
});

// --- own account ---------------------------------------------------------

it('lets an admin reach their own profile without any slug', function () {
    $this->actingAs(queueAdmin(), 'admin')->get('/admin/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Account/Profile'));
});

it('changes an admin password once the current one checks out', function () {
    $admin = queueAdmin();
    $admin->forceFill(['password' => bcrypt('old-Password!1')])->save();

    $this->actingAs($admin, 'admin')->put('/admin/password', [
        'current_password' => 'wrong',
        'password' => 'New-Password!1',
        'password_confirmation' => 'New-Password!1',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($admin, 'admin')->put('/admin/password', [
        'current_password' => 'old-Password!1',
        'password' => 'New-Password!1',
        'password_confirmation' => 'New-Password!1',
    ])->assertRedirect();

    expect(Hash::check('New-Password!1', $admin->fresh()->password))->toBeTrue();
});

it('accepts a legacy sha256 password as the current one', function () {
    $admin = queueAdmin();
    // Stored the way the source stored it, bypassing the model's hashed cast.
    DB::table('member_hq')->where('id', $admin->id)->update(['password' => hash('sha256', 'legacy-pass')]);

    $this->actingAs($admin->fresh(), 'admin')->put('/admin/password', [
        'current_password' => 'legacy-pass',
        'password' => 'New-Password!1',
        'password_confirmation' => 'New-Password!1',
    ])->assertRedirect();

    // And it lands as bcrypt, retiring the weak hash.
    expect(Hash::check('New-Password!1', $admin->fresh()->password))->toBeTrue();
});
