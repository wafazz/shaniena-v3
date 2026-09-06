# Migration Plan: Shaniena Ecom — Native PHP → Laravel 12 + Vue 3

> **MIMIC Protocol** | *"Same heart, different armor"*
> **Source:** `project/shaniena/ecom` — hand-rolled MVC, PHP 8.4, mysqli, raw PHP views
> **Target:** `project/shaniena-v3` — Laravel 12.69.1 + Inertia.js + Vue 3 + Bootstrap 5 / CoreUI Vue + MySQL
> **Generated:** 2026-09-05

---

## Locked Decisions

| Decision | Choice |
|---|---|
| Base | Fresh build (prior `new-version-shaniena` Blade port **not** reused) |
| Vue integration | **Inertia.js** (not a separate REST API) |
| Storefront rendering | **Inertia SSR** (node process) for SEO on shop pages |
| Admin template | **CoreUI Vue 5** (Bootstrap 5) |
| Database | Fresh Laravel migrations + import of live data |
| Sequence | **Admin first**, then storefront, payments last |

## Source Inventory (measured)

| Item | Count |
|---|---|
| Routes (`route/routes.php`) | 131 |
| Controllers | 32 |
| Models | 44 |
| Views | 78 (43 admin, 23 ecom, rest shop/auth) |
| DB tables (`base_ecom.sql`) | 59 |
| Background jobs / cron | 3 / 1 |
| PHP LOC (excl. vendor) | ~45,700 |

---

## Phase 1: Foundation & Setup

- [x] **1.1** Scaffold Laravel 12 — `laravel/framework v12.69.1` (pinned; installer defaulted to 13.30.1)
- [x] **1.2** Install Inertia + Vue 3 + CoreUI + Bootstrap; remove Tailwind
- [x] **1.3** Configure `vite.config.js` for Vue SFC + Sass; wire `resources/js/app.js` Inertia client
- [x] **1.4** Root Blade layout `resources/views/app.blade.php` + `HandleInertiaRequests` middleware
- [x] **1.5** Bootstrap/CoreUI Sass entry `resources/sass/app.scss` with brand tokens (primary `#e53637`)
- [x] **1.6** `.env` — MySQL `shaniena_v3` (local), Redis session/cache, `APP_TIMEZONE=Asia/Kuala_Lumpur`
- [x] **1.7** Configure Redis session + cache drivers; verify connection
- [x] **1.8** Install Inertia SSR (`@inertiajs/vue3/server`, `php artisan inertia:start-ssr`)
- [x] **1.9** Git baseline commit; add `.gitignore` guards for `.env`
- [x] **1.10** Smoke test: `npm run build` + `php artisan serve` render a CoreUI shell page

## Phase 2: Database — Schema & Data

- [x] **2.1** Extract full schema from `base_ecom.sql` (59 tables) into a normalised inventory
- [x] **2.2** Write Laravel migrations for **core commerce**: `products`, `product_variants`, `product_images`, `product_attributes`, `product_attribute_values`, `variant_attribute_values`, `categories`, `brands`, `stock_control`
- [x] **2.3** Migrations for **orders**: `customer_orders`, `order_details`, `order_temp_data`, `cart`, `cart_lock`, `cart_lock_senangpay`
- [x] **2.4** Migrations for **people**: `member_hq`, `members`, `role_access`, `role_access_button`, `user_activities`, `activities`
- [x] **2.5** Migrations for **geo & pricing**: `list_country`, `list_country_product_price`, `postage_cost`, `state`, `state_my`, `postcode_my`, `all_country`
- [x] **2.6** Migrations for **shipping**: `dhl`, `dhl_ship`, `dhl_token`, `dhl_bulk_print`, `jt_setting`, `jt_code`, `awb_printed`, `pickup_hubs`, `pickup_hub_staff`
- [x] **2.7** Migrations for **payments**: `senangpay_api`, `stripe_setting`, `billplz`, bayarcash tables
- [x] **2.8** Migrations for **CMS & support**: `news_blog`, `blog_views`, `about_us`, `policy`, `terms_conditions`, `image_setting`, `sliders`, `store_settings`, `cs_*` (7 tables)
- [x] **2.9** Migrations for **analytics**: `visitors`, `online_visitor_unique`, `online_visitor_return`
- [ ] **2.10** Add foreign keys + indexes — *deferred until after data import; 11 source FKs preserved. Adding new constraints before the data lands would fail on orphaned rows.*
- [x] **2.11** Drop dead tables (`sbtest1`, `all`, duplicate `dhl_token_test`) — log each removal
- [x] **2.12** Datetime columns as `datetime` (never `timestamp`) per project convention
- [x] **2.16** Migrations for `cod_charges`, `bayarcash_api`, `bayarcash_transactions` — DDL found in the source project root (`migration_cod_charges.sql`, `migration_bayarcash.sql`), so no column types were guessed. `sql/bayarcash_alter_columns.sql` folded in.
- [ ] **2.13** *(blocked — needs the live DB, see Open Risk 1)* Build data-import command `php artisan shaniena:import` from source DB → new schema
- [ ] **2.14** *(blocked — needs 2.13)* Run import; row-count reconciliation report old vs new for every table
- [x] **2.15** Seeders for lookup/reference data — `ReferenceDataSeeder` populates `list_country` (5), `all_country` (352), `state_my` (16), `postcode_my` (56,234) from files committed under `database/data/`, so a fresh clone seeds without the source DB. Idempotent; 6 Pest tests.
      **Not seeded: `state`.** Its `shipping_zone` (1 = Peninsular, 2 = Sabah/Sarawak/Labuan) drives postage *and* the COD benchmark fee. It was empty in the dump and the source has no hardcoded zone map to port, so it must come from live data or merchant confirmation.

## Phase 3: Backend Core — Models & Auth

- [x] **3.1** Eloquent models for commerce: `Product`, `ProductVariant`, `ProductImage`, `Category`, `Brand`, `StockControl` + relationships
- [x] **3.2** Eloquent models for orders: `Order` (`customer_orders`), `OrderDetail`, `Cart`, `CartLock`, `OrderTempData` + status constants (0–10) — *`CartLock` and `OrderTempData` were missing from the first pass; added.*
- [x] **3.3** Eloquent models for people: `MemberHq` (admin auth), `Member` (customer auth), `RoleAccess`, `Activity`, `UserActivity` — *`RoleAccess`, `Activity` and `UserActivity` were missing from the first pass; `MemberHq::activities()` pointed at a non-existent class. Added.*
- [x] **3.4** Eloquent models for geo/pricing: `ListCountry`, `AllCountry`, `CountryPrice`, `PostageCost`, `CodCharge`, `StateSetting`, `StateMy`, `PostcodeMy`. Postage and COD arithmetic ported onto the models (`costForWeight()`, `feeForSubtotal()`).
- [x] **3.5** Eloquent models for settings/CMS: `StoreSetting`, `Slider`, `NewsBlog`, `BlogView`, `PageContent` (abstract) + `AboutUs`/`Policy`/`TermsConditions`, `ImageSetting`, `JtSetting`
- [x] **3.6** Eloquent models for support: `SupportTicket` (source `SupportTicket` + `CsTicket` merged — both were the same table), `CsCustomer`, `CsStaffUser`, `CsTicketReply`, `CsTicketAttachment`, `CsReplyAttachment`, `CsTicketLog`
- [x] **3.7** Eloquent models for payments/shipping settings: `SenangPaySetting`, `BayarcashSetting`, `BayarcashTransaction`, `StripeSetting`, `BillplzSetting`, `DhlSetting`, `DhlToken`, `JtSetting`, `NinjavanToken`, `PickupHub`, `PickupHubStaff`. Each exposes `credentials()` resolving the active sandbox/production pair.
      *Deferred to Phase 6 (transactional, not settings): `dhl_ship`, `dhl_bulk_print`, `jt_code`, `awb_printed`, `apps_token`.*
- [x] **3.8** Multi-guard auth: `admin` guard → `member_hq`, `web` guard → `members`
- [x] **3.9** Password compatibility — admins stored **unsalted SHA-256**; `LegacyHashUserProvider` verifies then upgrades to bcrypt on login. Customers already bcrypt. 5 Pest tests green.
- [x] **3.10** Authorization: `access` / `perform` Gates over `App\Services\PageAccess` (replaces `roleVerify()`), plus a `page:<slug>` route middleware so Phase 7.4 has one place to enforce. Fail-closed, no super-admin bypass, 300 s cache with version-stamp invalidation. 9 Pest tests.
- [x] **3.11** Global helpers → service classes: `App\Services\StoreSettings` (singleton, 600 s cache, write-through invalidation) replaces `getStoreSettings()`; `Activity::record()` replaces `activity()`. 6 Pest tests.
- [x] **3.12** Replace `dateNow()` with Carbon + app timezone. **Found and fixed:** stock `config/app.php` hardcoded `'timezone' => 'UTC'` and ignored `APP_TIMEZONE`, so every write would have landed 8 h off the source data. Now `env('APP_TIMEZONE', 'UTC')`; 2 Pest tests lock it in.

## Phase 4: Admin Panel — Vue + CoreUI *(43 screens)*

> **Design gate (20-design-protocol.md): no screen is built before its Screen Brief is approved.**
> Briefs for the 8 foundation screens: https://claude.ai/code/artifact/38ccea32-c493-454f-ae84-e0c117aaa0a1 — **approved 2026-09-05.**
> Locked answers: volume **20k+ orders** (server-side paging, archives search-first) · reference **CoreUI Vue 5 demo** · **build remember-me and password reset properly**.
> **Q1/Q2 revised:** on inspection they gate individual *controls*, not whole screens. Q1 was resolved by fixing the filter (decision 36); Q2 still open, and only affects whether Order Search renders checkboxes.

- [x] **4.1** Admin layout shell: CoreUI sidebar + header + breadcrumb, driven by `role_access` via `App\Services\AdminNavigation`. Groups with no permitted child are dropped, as are section titles left over nothing; queue badges are loud only on the three working queues. Search moved from the sidebar to the topbar. 7 Pest tests. Screenshotted at 1280 and 390.
- [~] **4.2** Shared Vue components. **Built:** `StatusPill`, `EmptyState`, `FlashToast`, `DataTable` (server-paged over `CTable` + `CPagination`, header/cell slots, skeleton loading), `MoneyCell`, plus the SCSS token layer and `Order`/`Product`/`Category`/`Brand` factories. **Deferred by design:** `DataTable`, `FilterBar`, `MoneyCell`, `FormModal`, `FileUpload`, `RichText`, `Sortable` — each ships with the first screen that consumes it, rather than being designed speculatively against no table. *Deviation from the brief's build order, raised not absorbed.*
      **Corrected:** `CSmartTable` is a CoreUI **PRO** component — verified absent from the installed package (173 components exported; `CSmartTable`, `CSmartPagination`, `CDatePicker`, `CMultiSelect` all missing). `DataTable` builds on the free `CTable` + `CPagination` with a Laravel paginator, which is the right shape at 20k+ orders anyway. `CChart` also absent — needs `@coreui/vue-chartjs` (v3.0.0, MIT) + `chart.js`.
- [x] **4.3** Auth screens: login, logout, forgot/reset password. Rate-limited (5/min per email+IP), one message for wrong-password and unknown-email alike, a distinct message for inactive/banned accounts, and `AdminResetPassword` so links land on the admin routes. 14 Pest tests. *Backend built ahead of the design gate:* `remember_token` added to both auth tables (the source checkbox was decorative — no column existed), dedicated `admins` password broker on its own `admin_password_reset_tokens` table, 5 Pest tests. UI awaits brief approval.
- [x] **4.4** Dashboard — three headline figures with a working-queue panel, a secondary strip, a 14-day sales trend (`@coreui/vue-chartjs`), latest orders and the staff activity feed. Pre-aggregated behind a 30s cache carrying its own `generated_at`, so staleness is visible. 5 Pest tests.
- [x] **4.5** Products: create, edit, variants, images, country pricing — one component with a mode, replacing two drifted 400-line files. Server-side validation, 5-image cap, SKU uniqueness, variants soft-deleted not destroyed. 8 Pest tests. **All Products** list added: server-paged at 25, search across name/slug/**variant SKU**, filters on category, brand and published state, with stock and price range per product. The source had no product list at all — finding a product meant going through Stock Control, which lists *variants* and so cannot show a product that has none yet. 6 Pest tests.
- [x] **4.6** Stock control — server-paged (was client-side DataTables loading every row), computed balance and sold count in two grouped queries, append-only ledger preserved, per-button permissions, configurable low-stock threshold. 6 Pest tests.
> **Grouped brief — settings, CMS & reference screens (4.7, 4.12–4.20), 2026-09-05.**
> *Who:* HQ/Owner and Staff Admin, a few times a month. *Primary action:* save one form, or
> open one record. *Volume:* tens of rows — client-side is fine, server paging over-engineers it.
> *Density:* `roomy-form` for settings, `dense-table` for lists. *States:* empty carrying its
> create action, saving, inline validation, permission-denied via middleware. *Reuses:*
> `DataTable`, `MoneyCell`, `StatusPill`, `EmptyState`, CoreUI forms — no new components.
> *Reference:* CoreUI forms and tables demo, as approved.

- [x] **4.7** Categories & brands — list, add, edit, image upload; deleting is refused while products still point at the row (the source allowed it), and a category can no longer be its own parent.
- [x] **4.8** Orders: 7 routes on one screen (6 statuses + Database Order), filtering, server-side paging, per-row and bulk stage moves. 10 Pest tests. Screenshotted at 1280 and 390. Order detail is a slide-over fetched for the one order being looked at — the source rendered the same block inline for every row and ran four raw queries *per variant* to do it. Delivery details are editable from it, with an explicit warning and a louder log line when the parcel is already booked, since that edit cannot reach the courier. 8 Pest tests.
- [x] **4.9** Order search — search-first across every status, server-paged, resting state before any query. The source's checkboxes are gone: they were wired to a bulk bar that does not exist on that page, so it threw four null dereferences on load (**Q2 still open**). CSV export added: streamed and `chunkById`'d rather than assembled in memory, one row per order or per item, filtered by status, date range and the current search. Every export is written to the activity log — it is a file of customers' addresses leaving the building. 8 Pest tests.
- [x] **4.10** Courier submission (DHL / J&T / NinjaVan), AWB print, bulk print — *Send to courier* and *Print / Re-print AWB* are wired into the order queue, single and bulk. A bulk booking books each order on its own, so one courier refusal cannot take the batch down or attach an AWB to the wrong parcel. Bulk print POSTs its ids; the source put them in `?id=1,2,3` and interpolated that straight into SQL.
- [x] **4.11** HQ Staff + role-access matrix — server-side password rules (was browser-only), bcrypt on create (the source added a new SHA-256 hash per account), HQ/Owner role not assignable, self-edit blocked, permission toggles labelled rather than colour-only, every change logged. 8 Pest tests.
- [~] **4.12** Settings: store settings (allowlisted keys), shipping cost — postage and COD per country + zone. Plus the storefront copy pages (Policy, Terms, About Us) and Logo Setting. *Announcements not built: the `announcement` table has no DDL anywhere in the source.*
- [x] **4.13** Payment settings: SenangPay, Bayarcash, Stripe on one screen. **Secrets are never sent to the browser** — only whether each is set and a 4-char tail; a blank field keeps the stored value. The source rendered live secret keys into `value="..."`. Courier credentials (DHL, J&T) get the same treatment.
- [x] **4.14** Slider manager — drag reorder via vuedraggable, the whole running order saved in one request; upload, show/hide, remove.
- [x] **4.15** Blog manager — list, write, edit, remove, reader counts, author attribution.
- [x] **4.16** Country & state settings — add from the world list (switched off until postage is set), edit currency/rate/selling, and per-state shipping zones. The MYR rate is required, never defaulted: it fills every order's stored rate.
- [x] **4.17** Support tickets — queue sorted urgent-first, ticket detail with the conversation, staff reply, status change logged to `cs_ticket_logs`.
- [x] **4.18** Sales report — date range in the query string (so a filtered report is a shareable link), revenue/orders/average/postage summary, daily bar chart, breakdown by country and courier. Absorbs the source's separate *Sales Statistic* page.
- [x] **4.19** Pickup hub management — list with staff and order counts, add/edit, unique hub code enforced.
- [x] **4.20** Activity log viewer — paginated, filterable by staff member and area. Stage moves, permission changes, catalogue edits and settings saves all write here.

## Phase 5: Storefront — Vue + Inertia SSR *(23 screens)*

> **Visual direction, approved 2026-09-05: port Ashion faithfully.** The shop runs on
> **Ashion** (Colorlib, Bootstrap 4 + jQuery). Its SCSS source is vendored into
> `resources/sass/storefront/` and compiled by our pipeline; Owl Carousel, SlickNav,
> Magnific Popup and mixitup do not come across. Storefront and console stay visually
> separate and ship as **two bundles** — the shop is 189 kB CSS against the console's 333 kB,
> and neither downloads the other's framework.

- [x] **5.1** Storefront layout — Ashion header, nav (brand/category dropdowns from the DB), offcanvas mobile menu, search overlay, cart badge and footer from `store_settings`. Separate Vite entry, root Blade view and Inertia `rootView()` switch.
- [x] **5.2** Country selector — a page, not a gate. The source made it the site root and bounced every storefront URL back to it until a cookie was set, so shared product links and crawlers both landed on a picker. Switching country now also empties the basket, which the source left priced in the old currency.
- [x] **5.3** Homepage — hero slider, categories, New Arrival, Top Best Seller and Promo rows, service tiles. The hero is Swiper (no jQuery, no Owl) fed by the `sliders` table: the source's version was three hardcoded `.webp` files inside a block that **was commented out**, so the console's Slider Setting screen had been managing a table the storefront never read. Autoplay stands down under `prefers-reduced-motion`; the first slide loads eager/high-priority and the rest lazily; a single slide renders as a banner with no controls. **Fixed while here:** the category tiles put their heading and link as siblings of a `display:flex` `.categories__item`, so every tile read "Body CareShop now" — they now use Ashion's `.categories__text` wrapper, carry the category photo, show a live item count, and fall back to a flat ground instead of 314px of empty white. The category row stays a grid by choice, not a pending carousel: 8 tiles fit, and a grid needs no jQuery and does not reflow on load. 13 Pest tests.
- [x] **5.4** Category / brand / promo listings — server-paged at 24 with sort by newest, price or name. The source had **no LIMIT at all** and sorted `created_at` ascending, so a listing led with the oldest stock in the catalogue.
- [x] **5.5** Product details — opens on the first in-stock variant (decided server-side), gallery, stock and purchase cap per variant, related products. Out-of-stock variants are disabled rather than selectable-then-blocked.
- [x] **5.6** Search + slug routing — every storefront URL is a slug (`/product/hydra-glow-cleanser`), not the source's `/product-details/12`.
- [x] **5.7** Cart — add, update, remove. The purchase cap applies to the basket total, not one request, so adding one at a time no longer walks past it. *Cart locking belongs with the payment flow in Phase 6.*
- [~] **5.8** Checkout — address form with 30-day opt-in persistence, courier choice, postage and COD computed **server-side** in `App\Services\Storefront\Basket`. *Payment initiation waits on Phase 6.*
- [x] **5.9** Blog listing + detail with the per-IP view counter.
- [x] **5.10** Customer account — sign in, register, **email** verification (the source's "member verify" was email, not phone), and order history. The source had all of this commented out and unreachable, with dead links to `/forgot-password` and `/resend-verification`. Resend gives the same answer either way, so it cannot enumerate customers. 13 Pest tests.
- [x] **5.11** Support tickets (customer side) — open a ticket, look one up by number **and** matching email, reply. Replying puts a resolved ticket back in the queue.
- [x] **5.12** Static pages — about, policy, terms, contact. *No Maps embed: it needs an API key and the source hardcoded none.*
- [ ] **5.13** Referral flow — *not built: there is no referral implementation in the source to port, and no schema for one.*
- [x] **5.14** Inertia SSR — verified: a product page returns rendered markup, one `<title>`, an absolute `<link rel=canonical>` and Open Graph tags. SSR resolves only `Pages/Shop/**`, so the console never enters the SSR bundle. Cart, checkout and account are `noindex`.
      **Why it was silently off:** a stale `public/hot` file from a killed `npm run dev` made `Vite::isRunningHot()` true, so Inertia dispatched SSR to a dev server that was not running and fell back to client rendering without an error.
- [x] **5.15** PWA — manifest, service worker, offline page, icons and install banner. Product images are deliberately **not** cache-first: the source cached them that way, so a re-uploaded photo never reached anyone who had seen the old one. Admin and gateway hosts are never cached. Icons are generated from the brand mark: 192/512 `any`, a 512 `maskable` sized to the safe zone so Android's shape crop cannot bite into the R, a 180 apple-touch-icon, and a 32 favicon — **all four paths the manifest and the page head referenced were 404, so the shop had no favicon and Chrome would not have offered to install it.** The install bar defers `beforeinstallprompt`, remembers a dismissal, and never shows in standalone. 4 Pest tests assert every icon the manifest promises exists at its declared size.

## Phase 6: Payments & Shipping Integrations

> **The shared machinery is in place.** `App\Contracts\PaymentGateway` +
> `PaymentHandoff` / `PaymentResult`, a `PaymentGateways` registry, and
> `App\Services\Storefront\PlaceOrder`, which prices the basket from the catalogue at
> the moment the order is written. **No gateway ever receives an amount from the caller.**
> Confirmation is idempotent, because every gateway redelivers callbacks.

- [x] **6.1** `SenangPayGateway` — both hashes reproduced byte-for-byte from `lib/gateway/SenangPayGateway.php`: `hmac_sha256(secret . detail . amount . order_id, secret)` for the request and `hmac_sha256(secret . status_id . order_id . transaction_id . msg, secret)` for the callback. No separator; each field `urldecode()`d; the secret appears twice, prefixed on the message and as the HMAC key. Pending (status 2) is not a payment.
- [x] **6.2** `BayarcashGateway` — 5-field intent checksum and 13-field callback checksum, both `hmac_sha256(implode('|', values), secret)`.
      **The load-bearing detail:** the source declares the callback payload in Bayarcash's documented order and then runs `ksort()`, so the bytes actually hashed are **alphabetical by key**. Reproducing the documented order would make every callback fail. Tested against the exact byte string.
      Ported from `lib/gateway/BayarcashGateway.php`, **not** `model/Bayarcash.php` — that one ksorts whatever fields the caller happened to send, so an attacker choosing which fields to post chooses the payload. It is unused, and was left behind.
- [x] **6.3** COD flow + charge calculation — placed as a live order, fee from `cod_charges` via `Basket`. The source revealed the fee row with JavaScript and rewrote the total client-side. 10 Pest tests.
- [ ] **6.4** Stripe integration
- [x] **6.13** `BillplzGateway` *(added 2026-09-06, after the phase)* — the source had Billplz and this migration had not carried it across. Bill creation is ported from `billPlzzOrder()`: basic auth with the API key as the username, `api/v3/bills`, amount in cents, `reference_1` carrying the order id. Two source behaviours are deliberately **not** reproduced. Its callback (`bp-callback.php`) checked no signature at all and read `if($paid == "true" AND $state = "paid")` — a single `=`, so that half is an assignment and always true; anyone who knew a bill id could settle an order. And it added `bill_charge` to every bill regardless of `payment_charge`, so a shop that had chosen to absorb the FPX fee charged it to the customer anyway.
      **How a callback is trusted now:** the request is trusted for the bill id and nothing else. Whether that bill was paid, and for how much, is read back from Billplz over an authenticated request with our own API key, so a forged callback achieves nothing unless Billplz itself says the bill is paid. Underpayment is refused. `x_signature` is verified where a key is configured but is logged rather than trusted — a signature construction is a thing you can get subtly wrong, and the re-read is not. 13 Pest tests. **Still to do before go-live: place one real sandbox payment and confirm the callback lands and settles.**
      **Security gate (Argus/Cipher/Aegis, 2026-09-06) — one Medium, fixed:** the settings form took the API base URL as an editable field, and that URL is where the API key is sent as HTTP Basic auth. A staff member holding only the `payment-setting` grant could not read the masked key, but could point the endpoint at a host of their own and collect it on the next checkout — and aim an authenticated request at internal addresses on the way. Billplz has exactly two hosts, so they are pinned in `BillplzSetting::baseUrl()` the way `BayarcashGateway` already pins its own; the columns stay for schema parity and nothing reads them. A test asserts the request goes to Billplz even when the stored columns say otherwise.
- [x] **6.5** Payment channel toggles — a channel is offered only if it is switched on **and** configured. The source rendered a SenangPay button whose settings row did not exist, and computed `$senangpayEnabled` without ever using it.
- [x] **6.6** Callback + return routes — CSRF exempt for `payment/callback/*` **only**, with each gateway verifying its own signature. A callback confirms an order; the browser return never does, because it can be forged or simply never arrive. An unverified callback is refused with 400; the source answered 200 `OK` and ran an UPDATE on order id 0.
- [x] **6.7** Thank-you / failed pages, shared across channels, plus an auto-submitting handoff page for gateways that need a POST (it still works with JavaScript off).
- [x] **6.8** `NinjaVanGateway` — OAuth cached per mode via `updateOrCreate` (the source INSERTed a row on every refresh and never pruned). No token cron: the token is renewed lazily with a five-minute margin the moment a booking needs it, so there is no window where a cron has not run yet.
- [x] **6.9** `DhlGateway` + `JtExpressGateway` behind a `ShippingGateway` contract, dispatched by `Couriers` (case-insensitive — the source wrote back `J&T EXPRESS` but dispatched on `J&T Express`). `BookShipment` is idempotent and books one order at a time; the source posted a batch and matched AWBs back **by array position** across differently-sorted arrays. DHL fixes: production read the sandbox token, expiry was never checked, credentials rode in the query string, weight was hardcoded to 10g and COD was never declared.
- [x] **6.10** Mail — `OrderPlaced` and `TicketReplied` Mailables, both queued so a slow mail server cannot hold up a payment callback or an agent's screen. Replaces PHPMailer with **hardcoded Brevo SMTP credentials in the checkout controller**. The source's admin reply screen only wrote the row: nothing was ever emailed, so "Reply sent." was untrue.
- [x] **6.11** Queued jobs on the Scheduler: `ExpireAbandonedCarts` (one indexed chunked UPDATE, not a row-by-row loop that echoed HTML), `RefreshVisitorCounts` (to the cache — the source wrote a **0666 JSON file inside the web root**), `SyncDeliveryStatus` (`delivery-status.php`: dropped its `last_processed.json` cursor, which skipped any order shipped after the cursor passed its id, and restored TLS verification). `live-orders.php` — an **SSE loop holding a PHP worker per signed-in admin, eight uncached queries every two seconds** — is now a 30s poll of `GET /admin/dashboard/live`, paused while the tab is hidden.
- [x] **6.12** AWB labels: `endroid/qr-code` + `picqer/php-barcode-generator` (CODE 128) rendered through `barryvdh/laravel-dompdf`, all in memory — the source wrote a PNG per parcel into a web-readable `temp/` and never cleaned up. Paper is configurable (`AWB_LABEL_PAPER`: A5 sheets or 4x6in thermal). `awb_printed` is now one row per order, written **after** the PDF renders; the source wrote one blob row (`[12],[13]`) before rendering, so failed prints still counted. `AwbPrint::forOrder()` still reads the legacy blob shape.

## Phase 7: Security Hardening *(Gate 6 — Argus/Cipher/Aegis)*

- [x] **7.1** All credentials in `.env` — swept `app/`, `config/` and this repo's whole git history; the only matches are fabricated test fixtures (`sk_live_realone`) asserting that secrets are *not* sent to the browser. `.env` has never been committed. A standing test fails on any quoted credential literal. **The source repo still has the production DB password, J&T's tracking signing key and Billplz API keys in its history — flagged to Fakrul for rotation, not acted on here.**
- [x] **7.2** CSRF on all state-changing routes. The exempt list is `payment/callback/*` and nothing else — server-to-server posts with no session, each authenticated by its own signature. A test reads `bootstrap/app.php` and fails if anything is added.
- [x] **7.3** Mass-assignment guards on every model. 53 models audited reflectively; `Order` was the one denylist (`$guarded = ['id']`, i.e. everything but the primary key). It now has an explicit `$fillable` that **deliberately omits** status, the money columns and the courier fields — the four things an attacker would want to set. Every legitimate writer uses `forceFill`, which greps cleanly. A test asserts no model carries an open guard and that `Order::PROTECTED_COLUMNS` stay unfillable.
- [x] **7.4** Authorization on every admin route. 96 admin routes; 85 carry a `page:` grant and the other 11 are the auth screens and the self-service profile/password pages. A test asserts exactly that allowlist, so a new unguarded admin route fails the suite. **Found doing this:** `admin.password.update` named *two* routes — the reset-link flow and the signed-in change-password form — so `route()` resolved to whichever registered last and the other was unreachable by name. A second test now forbids duplicate route names.
- [x] **7.5** File-upload validation. Every upload states a MIME allowlist and a ceiling (a test proves it, following the FormRequest a controller type-hints). **SVG is refused everywhere** — it is XML, it can carry a `<script>`, and uploads are served from the storefront's own origin. The Logo Setting form listed `svg` in `mimes:` where Laravel's `image` rule was already rejecting it, so an admin uploading one got a confusing error. **Ticket attachments moved behind an authorised route:** the screen handed the browser the stored `file_path` and linked straight at it, so the file had no authorization of its own and an imported path reading `javascript:...` would have been a clickable script. The browser now only ever sees an id; the file is streamed from a non-public disk after checking it belongs to that ticket. 6 Pest tests.
- [x] **7.6** Rate limiting. Both logins already limited in-controller; **no route carried a `throttle` middleware at all**, so password reset, registration, email verification, checkout, payment start, callbacks, support tickets and order tracking were all unbounded. Six named limiters now cover them. Password reset is keyed on the address *and* the IP, so hammering one target does not lock out everyone behind the same NAT. Gateway callbacks are limited generously — throttling a retry would lose a real payment. 4 Pest tests exercise the limits rather than asserting the config.
- [x] **7.7** SQL injection sweep. One interpolation in the codebase: `HomeController`'s `FIELD(id, …)` best-seller ordering. The ids came from the database so it was never exploitable, but it was the only one left and a poor precedent — it now builds placeholders and passes bindings. A test fails on any raw fragment concatenating a variable without bindings.
- [x] **7.8** XSS. Zero `v-html` in the project — a test keeps it that way. The one place untrusted data reached an attribute was the ticket attachment `href`, closed under 7.5.
- [x] **7.9** Callback replay protection. `confirm()` read the status and then wrote it, which leaves a window: gateways retry, and two callbacks arriving together would both pass the check, both confirm, and the customer would get two emails. **The claim is now the conditional UPDATE itself**, so exactly one caller comes back with a row. A replay cannot overwrite the payment code from the payment that actually settled, and cannot walk a shipped order back to New. `fail()` was writing `STATUS_AWAITING_PAYMENT` over `STATUS_AWAITING_PAYMENT`; checking the source showed that is *correct* — status 10 is one bucket the dashboard calls "Failed Payment" and the SenangPay bot polls as "ask the gateway again" — so it now logs and deliberately leaves the row where the bot can still find it. 9 Pest tests.
- [x] **7.10** Dependency audit — `composer audit` reports no advisories; `npm audit --audit-level=moderate` reports 0 vulnerabilities. Both run inside CS verify.

## Phase 8: Testing & Verification *(Gate 4 — Probe/Echo)*

- [x] **8.1** Pricing, postage, COD charge and stock math — `ShippingChargesTest`, `StockControlTest` and the Basket suite. Postage takes the first kilo flat and `ceil()`s the extras; the COD boundary belongs to the above-benchmark fee (the source compares `subtotal < benchmark`, so RM100.00 on a RM100 benchmark pays `cod_fee_above`); the zone is decided by whether a country has configured states, not by a hardcoded Malaysia id.
- [x] **8.2** Auth on both guards and the authorization matrix — `AdminLoginScreenTest`, `AdminAuthFeaturesTest`, `LegacyAdminPasswordTest`, `PageAccessTest`, `StorefrontAccountTest`, plus the Phase 7 guards that assert *every* admin route carries a grant rather than testing a sample.
- [x] **8.3** Product CRUD, variants and stock — `ProductFormTest`, `ProductListTest`, `StockControlTest`.
- [x] **8.4** Cart → checkout → order — `StorefrontJourneyTest`, `CheckoutPaymentTest`, and `CodOrderLifecycleTest`, which covers what the browser run found (below).
- [x] **8.5** Gateway callbacks with valid and tampered checksums — `PaymentGatewayTest`, plus `RateLimitAndReplayTest` for replay and idempotency.
- [ ] **8.6** *(blocked — needs the live DB, see Open Risk 1)* Data-parity harness: replay real orders through old vs new pricing, diff every total
- [x] **8.7** Browser E2E of the critical journey — `tests/e2e/critical-journey.mjs`, driven over CDP with Node's built-in WebSocket (the Chrome extension is unavailable here), so it needs nothing installed. 12 steps: land, open a product, add to basket, check out, price postage, place a COD order. It exits non-zero on any failure, so it can gate a deploy. **This is what found the three defects below** — none of which the Pest suite could see, because each step passed in isolation.
- [x] **8.8** Screen smoke coverage, as tests rather than a checklist. A hand-kept list of 66 screens goes stale the first time someone adds one, so `tests/Support/Screens.php` holds the inventory and `ScreenCoverageTest` compares it against the routes that actually exist: a new admin screen either joins the smoke walk or is recorded against the named test that covers it, and doing neither fails the suite. The storefront walk renders every public page twice — with and without a country chosen — against an empty catalogue, which is what a fresh deployment looks like.

## Phase 9: Cutover

- [x] **9.1** Production `.env` + config caching — `.env.production.example`, annotated, with thirteen values marked CHANGE ME and every secret committed empty (a test asserts that). Production-only decisions are written down where they are made: `SESSION_SAME_SITE=lax` because gateways return the customer by a cross-site POST and `strict` would drop the session on the way back; `LOG_LEVEL=warning` because `debug` writes query bindings, which here means customer addresses on disk; three Redis databases so `cache:clear` cannot sign every customer out. All four caches (`config`, `route`, `view`, `event`) build clean, and a test keeps them buildable by failing on a closure in `config/` or an application route that is one.
- [x] **9.2** Deployment runbook — `docs/deployment.md`, with the config it tells you to install sitting next to it in `deploy/`: nginx vhost, two supervisor programs (queue worker, SSR node process), the single cron entry that replaced the source's four standalone cron scripts. Deploys are release directories and an atomic symlink flip, not the source's FTP-over-the-live-directory: `deploy/deploy.sh` builds a release, migrates, caches, flips, reloads php-fpm, restarts both supervised programs, then runs preflight — **and rolls itself back if preflight fails**. `deploy/rollback.sh` is a symlink move, an fpm reload and a restart, and deliberately touches no data.
- [x] **9.3** Asset build pipeline — `npm run build` produces the two independent client bundles and the SSR bundle; the split (the shop must not ship CoreUI, the console must not ship Ashion) already has a test. Hashed output is served `immutable` for a year, `public/sw.js` `no-cache` because it is not hashed and would otherwise pin customers to a stale worker across a deploy.
- [ ] **9.4** *(blocked — needs the live DB, see Open Risk 1)* Parallel run against live data; reconcile
- [x] **9.5** Cutover + rollback plan — `docs/cutover.md`. Twenty steps from a T-7 rehearsal to the T+1 reconciliation, each marked reversible or not: **step 14, repointing the gateway callback URLs and courier webhooks, is the point of no return**, and everything before it is a staging exercise with the old shop still able to serve. Three failures, three responses — a bad release (symlink move), a cutover aborted before step 14 (nothing lost but the window), and a cutover that failed after orders were taken (a data-loss decision that needs Fakrul, with the order-export procedure written out). It also states the one rule that keeps every rollback safe: a migration must be backward compatible with the release *currently* serving traffic, so drops ship one deploy after the code that stopped using the column.
- [x] **9.6** Handoff doc — published as an Artifact: <https://claude.ai/code/artifact/aa2a1b8e-6089-4c12-9fac-c0bdc22cd25c>. Phase status, the five processes production now needs, the cutover's one irreversible step, the four open risks and the five things needed from Fakrul.

**Also delivered in Phase 9:** `php artisan shaniena:preflight` — sixteen checks that answer "can this host serve the shop", exiting non-zero so it can gate a deploy or run from cron as a canary. Every check is a failure the source project actually had no way of noticing: a missing `APP_KEY`, a queue nobody is draining, an SSR process that died with the release before it, a `public/storage` link that was never made, reference tables that were never seeded, `MAIL_MAILER=log` in production quietly swallowing every order confirmation.

---

## Schema Gap — tables the code needs that the dump lacks

Resolved (migrations written from the source project's pending `sql/` files):
`store_settings`, `blog_views`, `sliders`, `ninjavan_token`

Resolved (DDL found in the source project **root**, not `sql/` — this is where the
earlier "DDL unknown" conclusion went wrong): `cod_charges` (`migration_cod_charges.sql`),
`bayarcash_api` + `bayarcash_transactions` (`migration_bayarcash.sql`, with
`sql/bayarcash_alter_columns.sql` applied). No money precision was guessed.

**Still missing — DDL required before the dependent features can migrate:**

| Table | Needed by | Why it cannot be reconstructed |
|---|---|---|
| `membership`, `membership_point_history` | Loyalty points | `point_amount`, `purchase_amount` are money |
| `phone_verify_code` | Phone verification | Column names known, types not |
| `announcement` | Admin announcements | Unknown |
| `ninjavan_setting`, `poslaju_setting` | Courier config | Unknown |
| `product`, `category`, `customer_order`, `member`, `so_states` | `shop/*` controllers | **Separate database** — a second shop subsystem, not part of `2025_rozeyana` |

## Open Risks

1. **Data source for import — measured 2026-09-05, and worse than assumed.** `base_ecom.sql` has all 59 tables but is essentially a *schema* dump: `customer_orders` 0, `order_details` 0, `products` 0, `product_variants` 0, `role_access` 0 (it did carry `members` 1,914 and `member_hq` 14). Local `2025_rozeyana` holds 22 tables of throwaway test data (2 orders, 5 products, 1 admin). **Neither local database is a migration source — the live remote DB is the only one.** Blocks 2.13, 2.14 and therefore 2.10. Needs Fakrul's credentials and authorisation.
2. ~~**Legacy password hashes**~~ — *resolved in 3.9*: `member_hq` is unsalted SHA-256, `members` is already bcrypt; `LegacyHashUserProvider` verifies then upgrades on login.
3. **Inertia SSR** adds a node process to production deployment.
4. **CoreUI free tier** lacks some PRO components (advanced multiselect); substituted `@vueform/multiselect`.
5. **Source credential leaks** (DB password, GitHub PAT) exist in git history — rotation is outside this migration.

## Decision Log

| # | Decision | Rationale |
|---|---|---|
| 1 | No Laravel starter kit | Vue kit is Tailwind-based; auth assumes `users` table, we need multi-guard on `member_hq`/`members` |
| 2 | Pinned `laravel/framework ^12.0` via `composer create-project` | `laravel new` installed 13.30.1; spec requires Laravel 12 |
| 3 | `@vueform/multiselect` over `vue-select` | `vue-select` has no Vue 3 stable release |
| 4 | `vuedraggable@^4.1.0` | v2.x is Vue 2 only |
| 5 | Register CoreUI via named exports, not `app.use()` | `@coreui/vue` v5 removed its `install()` hook; `app.use(CoreuiVue)` is a silent no-op that renders every `<C*>` tag as an empty comment. Handled in `resources/js/coreui.js`. |
| 6 | Import dump into scratch DB `shaniena_src`, generate migrations from metadata | Far more reliable than parsing 59 `CREATE TABLE` blocks by hand. Needed `utf8mb4_0900_ai_ci` → `utf8mb4_unicode_ci` (MySQL 8 dump, MariaDB 10.4 local). |
| 7 | `timestamp` → `datetime`, `softDeletes()` → explicit `datetime` | Project convention: `datetime` avoids MySQL timezone conversion. 59 + 17 columns converted. |
| 8 | Zero-date defaults → `nullable()` | Source had `DEFAULT '0000-00-00 00:00:00'` on 8 columns; MySQL strict mode rejects it. |
| 9 | Laravel `users` table not created | App authenticates against migrated `member_hq` / `members`. Kept `password_reset_tokens` + `sessions`. |
| 10 | `LegacyHashUserProvider` for the admin guard | `member_hq` stores unsalted SHA-256 (`config/function.php: hash('sha256', ...)`). Verifying then rehashing to bcrypt on login keeps every admin able to log in while removing the weak hash. |
| 11 | Prices come from `list_country_product_price`, not variant columns | Source resolves display price via `getPriceOnCountry()`; `sale_price` is charged and `market_price` shows struck through only when `sale < market`. `price_retail`/`price_sale` kept for schema parity only. |
| 12 | Cart active state is `status IN (0,1)` | Source `model/Cart.php` treats unpaid(0) and paid(1) as the live basket; 4 marks removed. |
| 13 | Dedicated `shaniena_v3_test` database | `RefreshDatabase` against the dev DB would wipe it; migrations use MySQL-specific types, so sqlite is not a safe substitute. |
| 14 | `SupportTicket` merges the source's `SupportTicket` + `CsTicket` | Both were models over `cs_tickets`, split only by which controller used them. One Eloquent model, one table. |
| 15 | `PageContent` is abstract with `AboutUs` / `Policy` / `TermsConditions` subclasses | The source passed the table name in as a string (`"SELECT * FROM {$tableName}"`), same for `CourierSetting`. Subclasses make the table a constant, so it can never be caller-supplied. |
| 16 | No super-admin bypass on the `access` gate | `roleVerify()` grants role 1 nothing implicitly — it is a pure `role_access` lookup. A bypass would widen access beyond what the migrated data says. |
| 17 | `CartLock` binds to `cart_lock_senangpay` | That is the table the source `CartLock` model used. The identically-shaped `cart_lock` table appears in the schema but in no code path — dead, kept only for import parity. |
| 18 | `allowed_user` keeps the `[1][7][12]` bracket format | Existing rows are written that way and the admin matrix editor must stay byte-compatible with imported data. `RoleAccess::allowedUserIds()` / `setAllowedUserIds()` wrap the encoding. |
| 19 | DHL and J&T mode flags are left inverted | `dhl.production_sandbox` is 1=production/2=sandbox; `jt_setting.production_sandbox` is 0=sandbox/1=production. Both kept as the source defines them; `isProduction()` on each model is the only sanctioned reader. |
| 20 | Deleted the stock `User` model, `UserFactory` and seeder body | They pointed at a `users` table decision 9 says will never exist — `php artisan db:seed` would have failed. |
| 21 | Reference data committed to `database/data/`, not read from `shaniena_src` at seed time | A seeder that depends on a scratch database is not reproducible on a fresh clone or in CI. `postcode_my` ships gzipped (349 KB for 56k rows). |
| 22 | `state_my` names supplied, not imported | The table was empty in the dump. The 16 codes are exactly the distinct `state_code` values in `postcode_my` (13 states + 3 federal territories); the names are the standard Pos Malaysia readings. Names only — nothing here affects pricing. |
| 23 | `postcode_my` seeded with `delete()`, not `truncate()` | `TRUNCATE` implicitly commits in MySQL, which would break the transaction `RefreshDatabase` wraps each test in. |
| 24 | `PostcodeMy` declares `$primaryKey = null`, `$incrementing = false` | The source table genuinely has no primary key — a postcode maps to many areas and no column pair is unique. The model defaulted to a non-existent `id`. |
| 25 | Test suite `memory_limit` raised to 512M in `phpunit.xml` | Seeding 56k postcode rows inside the test transaction exceeds PHP's 128M default. Measured: passes at 256M, headroom to 512M. |
| 26 | Admins get their own `admin_password_reset_tokens` table | `password_reset_tokens` is keyed by email alone. Staff who also shop on the store exist in both `member_hq` and `members`, so a shared table would let one reset request overwrite the other's token. |
| 27 | `DataTable` built on free `CTable` + `CPagination`, not `CSmartTable` | `CSmartTable` is CoreUI PRO and is not in the installed package. At 20k+ orders a client-side smart table would load every row anyway — server-side paging is the correct shape regardless of licensing. |
| 28 | Order queues split into working queues vs archives | New/Processing/In-Delivery hold tens–hundreds of live rows and are worked sequentially; Completed/Returned/Cancelled/Database hold the 20k+ bulk and are only ever searched. One template for both is what makes the archive screens slow. |
| 29 | `bootstrap/scss/bootstrap` import dropped; CoreUI only | CoreUI 5 is a full Bootstrap fork, so importing both shipped the framework twice **and** locked Sass's `$prefix` to `bs-` — Bootstrap sets it first, so CoreUI's `$prefix: cui- !default` could never win. Every `--cui-*` override was silently inert. Saved 113 kB of CSS. |
| 30 | Separate `$brand-interactive: #cc2f30` for filled buttons | White on the brand `#e53637` is 4.27:1, under the 4.5:1 AA floor, so Bootstrap's contrast function picked black text — accessible but visually broken on a CTA. The darker shade takes white to 5.23:1. The brand red is unchanged everywhere it sits on a light ground. |
| 31 | Queue badges are loud only on New / Processing / In Delivery | Returned and Cancelled are outcomes, not work. Colouring every non-zero count urgent teaches operators to ignore the colour. |
| 32 | Sidebar nav uses Inertia `<Link>` inside `CNavItem`'s slot | `CNavItem`'s own `href` prop renders a plain `<a>`, which is a full page reload on every nav click. |
| 33 | Shared components ship with their first consumer | `DataTable`/`FilterBar`/`MoneyCell` cannot be designed well against no table. Building them speculatively is how a component library ends up fitting nothing. |
| 34 | `.wrapper` padding rule written by hand | CoreUI sets `--cui-sidebar-occupy-start` on the fixed sidebar's siblings but ships no rule consuming it — that lives in their paid template. Without it the content sits under the sidebar. |
| 35 | `customer_orders` is one row per **order**, not per variant | The Phase 3 model docblock said the opposite and hung a `belongsTo` off `product_var_id`. Line items are `cart` rows sharing the order's `session_id` (`model/Order.php::listByStatus` joins cart and groups by `co.id`); `product_var_id` is a denormalised comma list, not a foreign key. Corrected, with `lines()` and `variantIds()`. |
| 36 | Product filter fixed to match any line, not only single-item orders | The source's `HAVING COUNT(*) = 1` meant filtering by a product hid every multi-item order containing it. Raised as Q1 and built as the fix — reverting is a one-line change to `applyFilters()`. |
| 37 | Stage transitions validated against `Order::ALLOWED_TRANSITIONS` | The source put the from/to pair in the button href, so a crafted URL could move an order to any status. `moveToProcessing()` also ran with **no** `checkAccess()` at all. |
| 38 | Archive queues open empty and require a filter | Completed/Returned/Cancelled/Database hold the bulk of 20k+ orders and are only ever searched. Rendering page 1 of 18,000 costs a query nobody wanted. Working queues still list immediately. |
| 39 | `.data-table { min-width: 60rem }` with horizontal scroll | Without it a 390px viewport crushes columns until a product name breaks over four lines. The source did the same with `min-width: 700px`. |
| 40 | Admin product routes bind by `{product:id}`, not slug | `Product::getRouteKeyName()` is `slug` for storefront URLs, but the slug is editable on the product form — saving a new one would move the page out from under the operator. Found as a 404 on update. |
| 41 | `variants.*.sku` validated `distinct` | `product_variants.sku` carries a UNIQUE index and a soft-deleted variant still holds its SKU, so a clash was a 500 rather than a field error. |
| 42 | `DataTable` takes `minWidth` as a prop | 60rem is right for a full-page queue and wrong for a table inside a narrow dashboard card, where it clipped the status pills. |
| 43 | Dashboard shows three figures, not the source's eight | Only what someone acts on gets to be loud. Total Products and all-time order count moved to a hairline strip. |
| 44 | Dashboard cache is 30s with a visible `generated_at` | The source polled a static JSON every 2s for figures that move a few times an hour, and showed nothing when the fetch failed. Pre-aggregation was right; invisible staleness was not. |
| 45 | Low-stock threshold read from `store_settings` | Hardcoded as 101 in two places in the source, so a slow-moving line showed red permanently. Defaults to 101, so behaviour is unchanged until set. |
| 46 | Payment and courier secrets are never sent to the browser | The source rendered live gateway secret keys into `value="..."` on the settings page. Now only a masked 4-char tail crosses the wire, and a blank field means "keep the stored value" so changing mode does not force retyping every secret. |
| 47 | One Payment Settings screen for three gateways | The source had a page per gateway repeating the same form. Replaces the `bayarcash-setting` nav slug with `payment-setting`. |
| 48 | NinjaVan, PosLaju, Announcements and Add-New-Country removed from the nav | The first three have no table anywhere in the source; adding a country now happens inline on the country list. A nav item that 404s is worse than one that is absent. |
| 49 | *Sales Statistic* folded into *Sales Report* | The source split charts and tables across two pages; one screen carries both. |
| 50 | Every admin route binds by id | Category, Brand, Product, PickupHub and SupportTicket all set `getRouteKeyName()` to a slug/code for public URLs — and those values are editable on the very screens that save them. |
| 51 | `LegacyHashUserProvider::isLegacySha256()` made public and reused | `Hash::check()` throws outright on a non-bcrypt hash, so the change-password screen has to test for a legacy hash first — the same order the auth provider uses. Restating the regex would have let the two drift. |
| 52 | Storefront and console are two Vite bundles with two root views | A customer downloading CoreUI, or an admin downloading Ashion, is pure waste. `HandleInertiaRequests::rootView()` picks by path. |
| 53 | Storefront primary is `#ca1515`, not `#e53637` | The plan recorded `#e53637` as "carried over from the source storefront", but that value is the PWA `theme-color` **meta tag**. The CSS primary customers actually see is Ashion's own `#ca1515`. Both are kept where they belong; the console is unaffected. |
| 54 | Bootstrap reboot + grid imported under Ashion, with `$link-decoration: none` | Ashion is a Bootstrap **4** theme and assumes those layers exist. Bootstrap 5 also underlines links by default where 4 did not, so without the override the whole shop rendered underlined and in Bootstrap blue. |
| 55 | Best-seller ranking resolves ids in a second query | MariaDB rejects `LIMIT` inside an `IN` subquery. The ordering is replayed with `FIELD()`. |
| 56 | Order search: an exact id match wins outright | A digits-only term also LIKE-matched phone and email, so typing an order number returned unrelated orders alongside it. Caught by a factory collision, not by design. |
| 57 | **Checkout totals are computed server-side, never read from the request** | The source computed subtotal, postage and COD *in the checkout view*, wrote them to `$_SESSION`, and let the payment controllers bill whatever was there — so anything that could render checkout could set the amount charged. `App\Services\Storefront\Basket` recalculates from the catalogue every time. |
| 58 | The basket is keyed to a 30-day `cart_token` cookie, not the session id | The source used the raw session id, so signing in — which regenerates the session — silently orphaned the customer's cart, and so did a session timeout mid-shop. |
| 59 | Shipping zone decided by whether a country has states configured | The source keyed on `country == "1"` in several places, which breaks the moment a second zoned country is added. |
| 60 | `cart_token` and `country_id` cookies are exempt from encryption | Both are opaque and non-secret — a 40-char random token and a country id. Guessing a token yields an empty cart. The source stored its country cookie in the clear too. |
| 61 | Test `SESSION_DRIVER` is `file`, and `TestCase::keep()` carries the shop cookies | The array driver forgets the session between requests, so no multi-request flow — cart, checkout, country gate — could be tested at all. |
| 62 | `Member` status and verification are string enums, not integers | The Phase 3 model cast both to `integer`, so every comparison read 0 — `scopeActive()` matched nothing and `isVerified()` was always false. The columns are `enum('inactive','active','banned')` and `enum('unconfirm','confirm')`. |
| 63 | SSR resolves only `Pages/Shop/**` | SSR exists for SEO and first paint on shop pages. The console is behind a login and indexed by nobody, so pulling CoreUI and every admin page into the SSR bundle would only slow the server. |
| 64 | `public/hot` is a build artefact, never committed | A stale one silently disabled SSR *and* made `@vite` emit source paths instead of hashed build URLs — which had a test passing for the wrong reason. |
| 65 | Canonical URLs come from the server, absolute and query-free | A filtered listing is the same page as the unfiltered one, and a client-derived canonical is wrong under SSR. |
| 66 | `order_details.order_id` holds `customer_orders.id` | It is a bigint. The Phase 3 model documented it as the session id and hung a `hasMany` off it, which the column type does not permit — found by writing the first real order. |
| 67 | `order_details.hash_code` is 64 random hex characters | The source used `sha256(id . '_' . name . '_' . dateNow)` — derived entirely from guessable inputs, and the only thing protecting the order page. |
| 68 | Order confirmation happens in the callback, never on the browser return | A return can be forged, arrive twice, or never arrive at all. `PlaceOrder::confirm()` is idempotent because every gateway redelivers. |
| 69 | `to_myr_rate` records the country's rate at the time of the order | The source read `list_country.rate` and then always wrote 1, discarding it. |
| 70 | Bayarcash ported from `lib/gateway/`, not `model/Bayarcash.php` | The two have different, incompatible checksums. `model/Bayarcash.php::verifyCallbackChecksum` unsets `checksum` and hashes whatever fields remain, so an attacker choosing which fields to post chooses the payload. It is dead code and stays that way. |
| 71 | Payment channel is never taken from the request | The source wrote `$_POST['payment_type']` straight into `payment_channel` on SenangPay success — and that field is not covered by the verified hash. |
| 72 | Bayarcash channel validated against the offered list | The source cast an unvalidated `?channel=` to int and put it in the checksum. |
| 73 | Nothing about a payment is written to the log | `BayarcashGateway` logged the full checksum payload, the resulting checksum and the secret's first four characters; the controller logged entire callback payloads. |
| 74 | Shipments are booked one order at a time | The source posted a batch to J&T and matched returned AWBs back **by array position**, walking a sorted id list against rows returned in SELECT order. One mismatch puts a customer's AWB on someone else's parcel. |
| 75 | `Couriers::for()` matches case-insensitively | The source dispatched on `'J&T Express'` but wrote `'J&T EXPRESS'` back to the order, so re-dispatching an already-shipped order fell into the "no courier assigned" branch. |
| 76 | `BookShipment` is idempotent on `awb_number` | The source had no guard: clicking *Send to Courier* twice booked two parcels for one order and paid for both. |
| 77 | No token cron for NinjaVan or DHL | Tokens are renewed lazily with a five-minute margin at the moment a booking needs one. A cron leaves a window where it has not run yet; this cannot. DHL's stored `expired_at` was never checked at all, so an expired token was reused until someone re-saved the settings form by hand. |
| 78 | Courier credentials moved out of the query string | DHL's OAuth call appended `clientId` and `password` to the URL, where they land in every proxy, CDN and server access log along the path. |
| 79 | AWB labels are generated in memory | The source wrote a QR PNG per parcel into `temp/` under the web root and never deleted one, leaving every shipment's code publicly fetchable. |
| 80 | `awb_printed` is one row per order, written after the render | The source wrote a single row holding `[12],[13],[14]` **before** generating the PDF, so a failed print still counted and the column could not be queried. `AwbPrint::forOrder()` reads both shapes so imported history still answers. |
| 81 | Bulk print takes its ids in the body | `awb-jt.php?id=1,2,3` interpolated `$_GET['id']` straight into `SELECT * FROM dhl_bulk_print WHERE id='...'`. |
| 82 | The live dashboard polls; it does not stream | `live-orders.php` was an SSE script with an infinite loop that held a PHP-FPM worker open per signed-in admin and ran eight uncached queries every two seconds — against a connection whose root password was written into the file. It is a 30-second fetch of `/admin/dashboard/live` now, paused while the tab is hidden. |
| 83 | Visitor counts live in the cache | `live-updater.php` wrote them to `live_visitors.json` inside the document root and `chmod`'d it 0666 — world-readable traffic figures in a world-writable file. |
| 84 | Delivery sync has no cursor file | `last_processed.json` only moved forward, so an order shipped after the cursor passed its id was skipped until the file happened to reset to 0. Status *is* the cursor now: a completed order is no longer in delivery, so it is never polled again. |
| 85 | TLS verification restored on tracking calls | `delivery-status.php` set `CURLOPT_SSL_VERIFYPEER = false`, making every tracking call interceptable — and it carried a hardcoded production signing key. |
| 103 | COD retires its basket at the moment the order is placed | A COD order is live immediately and no callback is coming, so its cart rows stayed status 0 — and `ExpireAbandonedCarts` soft-deleted them ten minutes later. Since `cart` *is* where an order's line items live, the order kept its totals and lost every product: the queue showed nothing, the AWB printed empty at minimum weight, the courier was quoted for the wrong parcel, and COD never counted toward best sellers. Found by the browser run, proven by a test that fails without the fix. |
| 104 | The checkout form uses the template's own class names | The markup said `checkout__input`; the stylesheet says `checkout__form__input`. Every field on the most important form in the shop rendered as a bare browser box, with the two address lines side by side and overflowing. |
| 105 | Checkout fields are real labels with ids and autocomplete | Ashion used a `<p>` as a pseudo-label, so nothing was programmatically associated — a screen reader announced an anonymous text box, clicking the text did not focus the field, and no browser or password manager could fill an address. The SCSS now styles `label` alongside `p`, and its `<select>`s at all, which the template never did. |
| 106 | Track and Support are `noindex` | Both show one customer their own order or ticket after a lookup, and both were using a plain `<Head>` with no robots directive at all. |
| 107 | One test helper for staff, in `tests/Pest.php` | Seven files had grown a near-identical copy, and the one that was shared lived in another test file — so any file using it could not be run on its own. Every test file now runs standalone, which is the most common thing a developer does. |
| 96 | `Order` drops the columns that decide its worth from `$fillable` | A `$guarded` denylist protects only what someone remembered to list. Status, money and courier fields now require a deliberate `forceFill`, which is greppable; a stray `Order::create($request->all())` cannot reach them. |
| 97 | One order-status vocabulary, held in step by a test | The model said `10 = Awaiting Payment`, the pill said `Failed Payment`, and the source said both — its dashboard labelled 10 "Failed Payment" while the SenangPay bot polled the same code as "pending, ask again". The model's wording wins and a test fails if the two maps drift. |
| 98 | The confirmation claim is the UPDATE, not a prior read | Two retried callbacks arriving together both passed a read-then-write check, both confirmed, and both emailed the customer. |
| 99 | `fail()` deliberately does not move the order | Status 10 is where the retry bot looks. Moving a failed payment out of it would take the order out of the bot's reach for good, so the method logs and leaves the row alone. |
| 100 | Ticket attachments are served by a controller, not by URL | The screen linked at the stored `file_path`, so the file carried no authorization and an imported path could put `javascript:` into an href. Only an id reaches the browser now. |
| 101 | No SVG upload anywhere | It is XML, it can carry a script, and uploads are served from the storefront's own origin. Laravel's `image` rule already refused it, so the `mimes:` list naming svg only made the error message confusing. |
| 102 | Security controls are asserted as tests, not checked once | A route without a page grant, a model with an open guard, a new CSRF exemption, an unthrottled public route, an SVG rule, raw SQL interpolation or a `v-html` now fails the suite rather than waiting for the next audit. |
| 92 | `npm run build` builds the SSR bundle too | It was `vite build`, which builds the client only — so `bootstrap/ssr/ssr.js` had been going stale since Phase 5. The server rendered one version of a page and the browser hydrated a newer one over it. Caught because a new component was missing from the SSR output. |
| 93 | Swiper is driven through its core API, not a Vue wrapper | Swiper dropped its Vue components at v9; v14 ships web components instead, which would need `isCustomElement` plumbing in the Vite Vue plugin. A `ref`'d element and `new Swiper()` needs neither and keeps the markup server-renderable. |
| 94 | The hero renders server-side, controls and all | It is the largest thing above the fold, so it has to be in the SSR HTML for LCP and for crawlers. Only the behaviour is client-side. |
| 95 | PWA icons are generated from the brand mark, not hand-placed | The tile is found by darkness rather than by pixel coordinates read off a screenshot, so the crop is reproducible. The maskable variant sits at 60% on its own ground because Android crops to an arbitrary shape inside the middle 80%. |
| 87 | `HandleStorefrontRequests` skips admin paths, and sets its cookie with `setCookie()` | It ran on the whole `web` group and called `withCookie()`, which only exists on Laravel's own Response — so **any** streamed or file response from an admin route threw a fatal for a visitor without a cart cookie. The first CSV export from a fresh browser would have 500'd. |
| 88 | `Order::detail()` corrected to `hasOne` on `order_id` | It was left as `belongsTo(OrderDetail, 'session_id', 'order_id')` after `order_details.order_id` was found to hold the order id, so it matched nothing. Nothing read it, which is why it went unnoticed. |
| 89 | The order export marks identifiers as text | A bare `05100` postcode becomes `5100` in Excel and a 13-digit AWB becomes `6.3E+11`. A UTF-8 BOM goes in for the same reason: without it Excel reads the file as Latin-1 and mangles every accented name. |
| 90 | Every order export is logged with its scope | The file carries customers' names, addresses and phone numbers. Somebody has to be able to say who took it and what was in it. |
| 91 | An address edit after booking warns rather than being blocked | Refusing it would block a legitimate correct-and-rebook. Saving it silently would leave our record and the courier's disagreeing with nobody told. It saves, warns, and says so in the log. |
| 86 | The ticket reply email link carries the ticket number only | The address the ticket was raised with would otherwise sit in browser history and referrer logs. The support page prefills the number and asks for the email, which is what actually authorises the lookup. |

## Verification Log

| Date | Checked | Result |
|---|---|---|
| 2026-09-05 | Laravel version | 12.69.1 ✓ (spec: Laravel 12) |
| 2026-09-05 | `npm run build` | 876 modules, CSS 443 kB / JS 366 kB ✓ |
| 2026-09-05 | MySQL `shaniena_v3` @ 127.0.0.1:3307 | created, base migrations ran ✓ |
| 2026-09-05 | Redis session + cache | write/read verified ✓ |
| 2026-09-05 | Inertia payload | `component: Welcome`, props delivered, Ziggy present ✓ |
| 2026-09-05 | Inertia SSR | full Bootstrap/CoreUI HTML server-rendered ✓ |
| 2026-09-05 | Dump import into `shaniena_src` | 286 statements, 0 failures, 59 tables ✓ |
| 2026-09-05 | `migrate:fresh` (71 migrations) | all ran clean ✓ |
| 2026-09-05 | Schema parity vs source | 57 tables column-compared, **0 mismatches** ✓ |
| 2026-09-05 | Multi-guard auth resolves | `web`→members, `admin`→admins via LegacyHashUserProvider ✓ |
| 2026-09-05 | Legacy password upgrade | 5 Pest tests, 7 assertions, all passing ✓ |
| 2026-09-05 | 3 new migrations (`cod_charges`, `bayarcash_api`, `bayarcash_transactions`) | ran clean on `shaniena_v3` ✓ |
| 2026-09-05 | Every model binds to a real table, every `$fillable`/cast column exists | 47 models checked, 0 failures ✓ |
| 2026-09-05 | Every Eloquent relation compiles to SQL | 60 relations checked, 0 failures ✓ |
| 2026-09-05 | `config('app.timezone')` after fix | `Asia/Kuala_Lumpur`, `now()` at UTC+8 ✓ |
| 2026-09-05 | Pest suite after Phase 3 | 29 passed, 47 assertions ✓ |
| 2026-09-05 | `vendor/bin/pint --test` | passed (whole repo, incl. pre-existing violations fixed) ✓ |
| 2026-09-05 | CS verify | VERIFIED 85/100 before Pint; lint gate now green ✓ |
| 2026-09-05 | Candidate import sources profiled | `shaniena_src` commerce tables empty, `2025_rozeyana` test-only → live DB required ✓ |
| 2026-09-05 | Reference seed row counts vs source | `list_country` 5/5, `all_country` 352/352, `postcode_my` 56,234/56,234 ✓ |
| 2026-09-05 | Reference seed **content** vs source | MD5 over all rows, binary-ordered: identical for all three tables ✓ |
| 2026-09-05 | Postcode → state coverage | 0 postcodes without a `state_my` row (16/16 codes) ✓ |
| 2026-09-05 | `ReferenceDataSeeder` re-run | counts unchanged — idempotent ✓ |
| 2026-09-05 | Pest suite after 2.15 | 35 passed, 63 assertions ✓ |
| 2026-09-05 | CoreUI free package component audit | 173 exported; `CSmartTable`/`CSmartPagination`/`CDatePicker`/`CMultiSelect`/`CChart` absent ✓ |
| 2026-09-05 | Remember-me on both guards | token persisted for `admin` and `web` ✓ |
| 2026-09-05 | Admin password reset on its own broker | link sent, reset applied, bcrypt stored ✓ |
| 2026-09-05 | Admin/customer reset-token isolation | same email, 2 tokens, neither overwritten ✓ |
| 2026-09-05 | Pest suite after 4.3 backend | 40 passed, 76 assertions ✓ |
| 2026-09-05 | Sidebar order counts | 1 grouped query, not 6 `SELECT *` scans — asserted in the test ✓ |
| 2026-09-05 | Sign-in journey in a real browser (headless Chrome/CDP) | form → redirect to `/admin/dashboard`, 0 console errors ✓ |
| 2026-09-05 | Shell + auth screenshots at 1280 and 390 | captured, reviewed, 3 defects found and fixed ✓ |
| 2026-09-05 | CSS bundle after dropping the duplicate Bootstrap import | 444.9 kB → 331.7 kB (gzip 60.9 → 45.4) ✓ |
| 2026-09-05 | Primary button contrast, measured in-browser | white on `#cc2f30` = 5.23:1, passes AA ✓ |
| 2026-09-05 | Pest suite after 4.1 + 4.3 | 56 passed, 131 assertions ✓ |
| 2026-09-05 | Order line items load without a query per row | ≤4 queries for 5 orders × 2 lines; source ran 4 raw queries **per variant per order** ✓ |
| 2026-09-05 | Product filter matches multi-item orders | 2 of 3 orders matched where the source's `HAVING COUNT(*) = 1` returned 1 ✓ |
| 2026-09-05 | Illegal stage transition rejected | New → Completed refused, order unchanged ✓ |
| 2026-09-05 | Order queues screenshotted at 1280 and 390 | 3 layout defects found and fixed ✓ |
| 2026-09-05 | Pest suite after 4.8 | 66 passed, 192 assertions ✓ |
| 2026-09-05 | Revenue excludes cancelled and failed orders | RM100 counted, RM500 cancelled and RM900 failed excluded ✓ |
| 2026-09-05 | Stock balance = SUM(stock_in) − SUM(stock_out) | 500 − 120 = 380, two grouped queries for the page ✓ |
| 2026-09-05 | New staff password stored bcrypt, never SHA-256 | asserted against `hash('sha256', ...)` ✓ |
| 2026-09-05 | Permission toggle takes effect on the gate immediately | grant → allowed, revoke → denied, cache flushed ✓ |
| 2026-09-05 | Variant dropped from the product form is soft-deleted | 1 active, 2 with trashed ✓ |
| 2026-09-05 | Every new screen rendered at 1280 and 390 | 0 server errors, 5 layout defects found and fixed ✓ |
| 2026-09-05 | Pest suite after 4.4–4.11 | 98 passed, 373 assertions ✓ |
| 2026-09-05 | Payment + courier secrets absent from the HTML payload | 6 secrets asserted missing; only masked tails present ✓ |
| 2026-09-05 | Blank secret field preserves the stored value | mode changed, secrets unchanged ✓ |
| 2026-09-05 | Every admin screen renders for a permitted admin | 24 screens, all 200 + correct component ✓ |
| 2026-09-05 | Every admin screen 403s without its slug | 23 screens ✓ |
| 2026-09-05 | Sidebar link crawl in a real browser | 31 links, **0 dead ends** ✓ |
| 2026-09-05 | Legacy SHA-256 accepted as current password | verified, then stored as bcrypt ✓ |
| 2026-09-05 | Pest suite after Phase 4 | 175 passed, 708 assertions ✓ |
| 2026-09-05 | Storefront serves its own bundle, not the console's | asserted `storefront.js` present and `app.js` absent ✓ |
| 2026-09-05 | Ashion CSS applies to the ported Vue markup | header, section titles, product cards and prices render as the template ✓ |
| 2026-09-05 | Storefront home at 1280 and 390 | captured, 3 defects found and fixed ✓ |
| 2026-09-05 | Pest suite after 5.1 + 5.3 | 184 passed, 791 assertions ✓ |
| 2026-09-05 | Basket priced from the database, not the request | line price tampered to 0.01, subtotal still 119.80 ✓ |
| 2026-09-05 | Purchase cap holds across separate additions | 2 + 2 against a cap of 3 rejected ✓ |
| 2026-09-05 | One session cannot touch another's cart line | 403 ✓ |
| 2026-09-05 | Postage rounds extra weight up to the whole kilo | 0.9/1.0kg → 6.50, 1.1/2.0kg → 9.50, 2.1kg → 12.50 ✓ |
| 2026-09-05 | COD boundary pays the above-benchmark fee | benchmark 100, below 10, above 8: 99.99 → 10.00, 100.00 → 8.00 ✓ |
| 2026-09-05 | Tracking needs order number **and** matching email | wrong email returns nothing ✓ |
| 2026-09-05 | Every storefront route responds | 9 routes, all 200 ✓ |
| 2026-09-05 | Pest suite after Phase 5 slice | 199 passed, 862 assertions ✓ |
| 2026-09-05 | Customer registers unverified, code emailed | account inactive until verified ✓ |
| 2026-09-05 | Unverified and banned customers cannot sign in | redirected, session not left open ✓ |
| 2026-09-05 | Account page shows only that customer's orders | 2 of 3 ✓ |
| 2026-09-05 | Ticket needs its number **and** matching email | wrong email returns nothing ✓ |
| 2026-09-05 | SSR renders a product page for a crawler | markup + single title + absolute canonical + og tags ✓ |
| 2026-09-05 | Storefront serves only its own bundle | admin chunk absent from the HTML ✓ |
| 2026-09-05 | Pest suite after Phase 5 | 217 passed, 953 assertions ✓ |
| 2026-09-05 | COD order priced entirely server-side | 2×59.90 + 6.50 postage + 8.00 COD = 134.30 ✓ |
| 2026-09-05 | Order reference hash is unguessable | 64 random hex, not sha256 of id+name+time ✓ |
| 2026-09-05 | Paying issues a fresh cart token | a confirmed basket cannot be reused ✓ |
| 2026-09-05 | A switched-off channel cannot be paid to | 404, no order created ✓ |
| 2026-09-05 | Gateway callbacks skip CSRF, nothing else does | `payment/callback/*` reachable without a token ✓ |
| 2026-09-05 | Pest suite after the Phase 6 core | 227 passed, 974 assertions ✓ |
| 2026-09-05 | SenangPay request + callback hashes | match the source's construction byte-for-byte ✓ |
| 2026-09-05 | Bayarcash 5-field and 13-field checksums | match the ksort-ed byte string ✓ |
| 2026-09-05 | Tampered checksum, altered amount, missing checksum | all refused ✓ |
| 2026-09-05 | Replayed callback confirms once | one order_details row, one confirmation ✓ |
| 2026-09-05 | A channel switched on but unconfigured is not offered | ✓ |
| 2026-09-05 | Pest suite after 6.1 + 6.2 | 241 passed, 1002 assertions ✓ |
| 2026-09-06 | `config:cache`, `route:cache`, `view:cache`, `event:cache` | all four build clean; 0 closures under `config/`, 0 application routes that are one ✓ |
| 2026-09-06 | `npm run build` (client + SSR) | 876 modules client, SSR bundle 244.9 kB from 35 modules — shop pages only, by design ✓ |
| 2026-09-06 | SSR bundle run as supervisor would (`node bootstrap/ssr/ssr.js`) | `/health` 200 ✓ |
| 2026-09-06 | `shaniena:preflight` against this host | 11 ok, 3 n/a on a local box (debug, framework caches, secure cookie), 2 correctly FAIL — SSR process down and `MAIL_FROM_ADDRESS` still `hello@example.com`; green once the SSR process was started ✓ |
| 2026-09-06 | `deploy.sh` / `rollback.sh` | `bash -n` clean, executable, asserted by a test ✓ |
| 2026-09-06 | Supervisor commands match the app | worker queue = the template's `QUEUE_CONNECTION`, SSR path = `config('inertia.ssr.bundle')` ✓ |
| 2026-09-06 | Committed production template carries no secret | `APP_KEY`, both DB passwords, Redis, mail, NinjaVan and J&T keys all empty ✓ |
| 2026-09-06 | `vendor/bin/pint --test` | passed ✓ |
| 2026-09-06 | Pest suite after Phase 9 | 404 passed, 1925 assertions ✓ |
| 2026-09-06 | Billplz: bill creation fields, cents, fee ownership, callback via API re-read, replay, short payment, masked keys | 13 tests, 43 assertions ✓ |
| 2026-09-06 | Security gate on the Billplz change | 1 Medium (editable API endpoint → key exfiltration + SSRF), fixed and covered by a test; no bypass found in the callback path ✓ |
| 2026-09-06 | Storefront themes (Ashion / Electro) | one stylesheet served per theme and never both, every storefront screen renders under either, unknown theme falls back to Ashion ✓ |
