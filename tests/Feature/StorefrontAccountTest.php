<?php

use App\Models\Member;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Notifications\VerifyCustomerEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function customer(array $overrides = []): Member
{
    return Member::create(array_merge([
        'email' => 'shopper@example.test',
        'password' => 'secret-password',
        'name' => 'Aisyah',
        'phone' => '+60123456789',
        'verification_status' => Member::VERIFIED,
        'status' => Member::STATUS_ACTIVE,
    ], $overrides));
}

// --- registration and verification ---------------------------------------

it('registers a customer as unverified and emails a code', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Aisyah', 'email' => 'new@example.test', 'phone' => '+60111',
        'password' => 'secret-password', 'password_confirmation' => 'secret-password',
    ])->assertRedirect('/verify-email');

    $member = Member::where('email', 'new@example.test')->firstOrFail();

    expect($member->isVerified())->toBeFalse()
        ->and($member->isActive())->toBeFalse()
        ->and($member->verification_code)->not->toBeNull();

    Notification::assertSentTo($member, VerifyCustomerEmail::class);
});

it('stores the password hashed', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'A', 'email' => 'hash@example.test', 'phone' => '01',
        'password' => 'secret-password', 'password_confirmation' => 'secret-password',
    ]);

    $stored = Member::where('email', 'hash@example.test')->value('password');

    expect($stored)->not->toBe('secret-password')
        ->and(password_get_info($stored)['algoName'])->not->toBe('unknown');
});

it('activates the account once the code checks out', function () {
    $member = customer(['verification_status' => Member::UNVERIFIED, 'status' => Member::STATUS_INACTIVE, 'verification_code' => '123456']);

    $this->post('/verify-email', ['email' => $member->email, 'code' => '123456'])
        ->assertRedirect('/login');

    $member->refresh();

    expect($member->isVerified())->toBeTrue()
        ->and($member->isActive())->toBeTrue()
        // The used code must not linger.
        ->and($member->verification_code)->toBeNull();
});

it('rejects a wrong code', function () {
    $member = customer(['verification_status' => Member::UNVERIFIED, 'verification_code' => '123456']);

    $this->post('/verify-email', ['email' => $member->email, 'code' => '999999'])
        ->assertSessionHasErrors('code');

    expect($member->fresh()->isVerified())->toBeFalse();
});

it('does not reveal whether an address has an account when resending', function () {
    Notification::fake();

    // Same response either way, so this cannot enumerate customers.
    $unknown = $this->post('/verify-email/resend', ['email' => 'nobody@example.test']);
    $known = $this->post('/verify-email/resend', ['email' => customer(['verification_status' => Member::UNVERIFIED])->email]);

    expect($unknown->getSession()->get('success'))->toBe($known->getSession()->get('success'));
});

// --- sign in -------------------------------------------------------------

it('signs a verified customer in', function () {
    $member = customer();

    $this->post('/login', ['email' => $member->email, 'password' => 'secret-password'])
        ->assertRedirect('/account');

    $this->assertAuthenticatedAs($member, 'web');
});

it('turns away an unverified customer', function () {
    $member = customer(['verification_status' => Member::UNVERIFIED, 'status' => Member::STATUS_INACTIVE]);

    $this->post('/login', ['email' => $member->email, 'password' => 'secret-password'])
        ->assertRedirect('/verify-email');

    $this->assertGuest('web');
});

it('turns away a banned customer', function () {
    $member = customer(['status' => Member::STATUS_BANNED]);

    $this->post('/login', ['email' => $member->email, 'password' => 'secret-password'])
        ->assertRedirect('/login');

    $this->assertGuest('web');
});

it('sends a guest asking for the account page to the shop login, not the admin one', function () {
    $this->get('/account')->assertRedirect('/login');
});

// --- account -------------------------------------------------------------

it('shows the customer only their own orders', function () {
    $member = customer();
    Order::factory()->count(2)->create(['customer_email' => $member->email]);
    Order::factory()->create(['customer_email' => 'someone@else.test']);

    $this->actingAs($member, 'web')->get('/account')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Shop/Account')->has('orders.data', 2));
});

// --- support -------------------------------------------------------------

it('opens a ticket and issues a number', function () {
    $this->post('/support', [
        'customer_name' => 'Aisyah', 'customer_email' => 'a@example.test',
        'title' => 'Where is my order?', 'description' => 'It has been a week.',
        'priority' => 'medium',
    ])->assertRedirect();

    expect(SupportTicket::count())->toBe(1)
        ->and(SupportTicket::first()->ticket_no)->toStartWith('T-');
});

it('needs the ticket number and the matching email to read a ticket', function () {
    $ticket = SupportTicket::create([
        'customer_name' => 'Aisyah', 'customer_email' => 'a@example.test',
        'ticket_no' => 'T-ABCD1234', 'title' => 'Help', 'description' => 'Please',
        'status' => SupportTicket::STATUS_NEW, 'priority' => 'medium',
    ]);

    $this->get('/support?ticket_no=T-ABCD1234&email=wrong@example.test')
        ->assertInertia(fn ($page) => $page->where('ticket', null));

    $this->get('/support?ticket_no=T-ABCD1234&email=a@example.test')
        ->assertInertia(fn ($page) => $page->where('ticket.ticket_no', 'T-ABCD1234'));
});

it('carries the looked-up email back to the page, so replying by link works', function () {
    // The reply form and the thread poll both need the address the ticket was
    // found with, and neither has a session to recover it from. Without this
    // the link in a notification opened the ticket read-only: the email box
    // was blank, so a reply failed validation and the poll never fired.
    SupportTicket::create([
        'customer_name' => 'Aisyah', 'customer_email' => 'a@example.test',
        'ticket_no' => 'T-LINK0001', 'title' => 'Help', 'description' => 'Please',
        'status' => SupportTicket::STATUS_NEW, 'priority' => 'medium',
    ]);

    $this->get('/support?ticket_no=T-LINK0001&email=a@example.test')
        ->assertInertia(fn ($page) => $page
            ->where('prefill.ticket_no', 'T-LINK0001')
            ->where('prefill.email', 'a@example.test')
            ->where('ticket.ticket_no', 'T-LINK0001'));
});

it('serves the live thread only to the number and matching email together', function () {
    // The polled endpoint is unauthenticated, exactly like the page lookup it
    // mirrors. If it ever answers a ticket number on its own it hands out
    // other people's support conversations.
    SupportTicket::create([
        'customer_name' => 'Aisyah', 'customer_email' => 'a@example.test',
        'ticket_no' => 'T-LIVE0001', 'title' => 'Help', 'description' => 'Please',
        'status' => SupportTicket::STATUS_NEW, 'priority' => 'medium',
    ]);

    $this->getJson('/support/thread?ticket_no=T-LIVE0001&email=wrong@example.test')
        ->assertNotFound();

    $this->getJson('/support/thread?ticket_no=T-LIVE0001')
        ->assertStatus(422);

    $this->getJson('/support/thread?ticket_no=T-LIVE0001&email=a@example.test')
        ->assertOk()
        ->assertJsonPath('ticket_no', 'T-LIVE0001')
        ->assertJsonPath('closed', false);
});

it('gives the polled thread the same shape the page was rendered with', function () {
    // Built in two places these drift, and the first poll would then quietly
    // replace a rendered field with nothing.
    SupportTicket::create([
        'customer_name' => 'Aisyah', 'customer_email' => 'a@example.test',
        'ticket_no' => 'T-SHAPE001', 'title' => 'Help', 'description' => 'Please',
        'status' => SupportTicket::STATUS_NEW, 'priority' => 'medium',
    ]);

    $rendered = null;
    $this->get('/support?ticket_no=T-SHAPE001&email=a@example.test')
        ->assertInertia(function ($page) use (&$rendered) {
            $rendered = $page->toArray()['props']['ticket'];
        });

    $polled = $this->getJson('/support/thread?ticket_no=T-SHAPE001&email=a@example.test')
        ->assertOk()
        ->json();

    expect($polled)->toEqual($rendered);
});

it('puts a replied-to ticket back in the queue', function () {
    $ticket = SupportTicket::create([
        'customer_name' => 'A', 'customer_email' => 'a@example.test',
        'ticket_no' => 'T-REPLY001', 'title' => 'Help', 'description' => 'Please',
        'status' => SupportTicket::STATUS_RESOLVED, 'priority' => 'low',
    ]);

    $this->post('/support/reply', [
        'ticket_no' => 'T-REPLY001', 'email' => 'a@example.test', 'message' => 'Still not sorted.',
    ])->assertRedirect();

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_IN_PROGRESS)
        ->and($ticket->replies()->count())->toBe(1);
});

// --- what the phone menu needs -------------------------------------------

it('tells the storefront nobody is signed in, so the menu offers a sign-in', function () {
    shopCountry();

    $this->get('/')->assertInertia(fn ($page) => $page->where('shop.customer', null));
});

it('names the signed-in customer for the menu', function () {
    shopCountry();
    $member = customer();

    $this->actingAs($member, 'web')
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('shop.customer.name', 'Aisyah'));
});
