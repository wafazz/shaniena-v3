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
- [ ] **2.13** *(deferred by decision)* Build data-import command `php artisan shaniena:import` from source DB → new schema
- [ ] **2.14** *(deferred by decision)* Run import; row-count reconciliation report old vs new for every table
- [ ] **2.15** *(deferred by decision)* Seeders for lookup/reference data (countries, states, postcodes)

## Phase 3: Backend Core — Models & Auth

- [ ] **3.1** Eloquent models for commerce: `Product`, `ProductVariant`, `ProductImage`, `Category`, `Brand`, `StockControl` + relationships
- [ ] **3.2** Eloquent models for orders: `Order` (`customer_orders`), `OrderDetail`, `Cart`, `CartLock`, `OrderTempData` + status constants (0–10)
- [ ] **3.3** Eloquent models for people: `MemberHq` (admin auth), `Member` (customer auth), `RoleAccess`, `Activity`
- [ ] **3.4** Eloquent models for geo/pricing: `ListCountry`, `CountryPrice`, `PostageCost`, `CodCharge`, `StateSetting`
- [ ] **3.5** Eloquent models for settings/CMS: `StoreSetting`, `Slider`, `NewsBlog`, `BlogView`, `PageContent`, `ImageSetting`, `CourierSetting`
- [ ] **3.6** Eloquent models for support: `SupportTicket`, `CsTicket`, `CsCustomer`, `CsReply`
- [ ] **3.7** Eloquent models for payments/shipping settings: `SenangPaySetting`, `Bayarcash`, `BayarcashTransaction`, `StripeSetting`, `DhlSetting`, `PickupHub`
- [ ] **3.8** Multi-guard auth: `admin` guard → `member_hq`, `web` guard → `members`
- [ ] **3.9** Port password hashing/verification from source (audit legacy hash format, add rehash-on-login)
- [ ] **3.10** Authorization: Gates/Policies replacing `checkAccess()` + `role_access` matrix
- [ ] **3.11** Global helpers → config + service classes (`getStoreSettings()` → cached `StoreSetting::all()`)
- [ ] **3.12** Replace `dateNow()` with Carbon + app timezone; audit every datetime write

## Phase 4: Admin Panel — Vue + CoreUI *(43 screens)*

- [ ] **4.1** Admin layout shell: CoreUI sidebar + header + breadcrumb, driven by `role_access`
- [ ] **4.2** Shared Vue components: `DataTable` (CSmartTable), `FormModal`, `ConfirmDialog` (SweetAlert2), `FileUpload`, `RichText` (TinyMCE), `Sortable` (vuedraggable)
- [ ] **4.3** Auth screens: login, logout, forgot/reset password
- [ ] **4.4** Dashboard — sales widgets, latest orders, charts
- [ ] **4.5** Products: list, create, edit, variants, images, country pricing
- [ ] **4.6** Stock control screens
- [ ] **4.7** Categories & brands
- [ ] **4.8** Orders: 6 status listings, order detail, customer edit, bulk status move
- [ ] **4.9** Order search + export (PhpSpreadsheet → maatwebsite/excel)
- [ ] **4.10** Courier submission (DHL / J&T / NinjaVan), AWB print, bulk print
- [ ] **4.11** Members & staff management, role access matrix editor
- [ ] **4.12** Settings: store settings, shipping, postage, COD charge, announcements
- [ ] **4.13** Payment settings: SenangPay, Bayarcash, Stripe
- [ ] **4.14** Slider manager (drag reorder)
- [ ] **4.15** Blog / news manager
- [ ] **4.16** Country & state settings
- [ ] **4.17** Support tickets (admin side)
- [ ] **4.18** Sales reports
- [ ] **4.19** Pickup hub management
- [ ] **4.20** Activity log viewer

## Phase 5: Storefront — Vue + Inertia SSR *(23 screens)*

- [ ] **5.1** Storefront layout: header, nav, footer (from `store_settings`), PWA meta
- [ ] **5.2** Country selector landing page
- [ ] **5.3** Homepage: slider, category carousel (Swiper), featured products
- [ ] **5.4** Category / brand / promo listing pages
- [ ] **5.5** Product details: variant selection, auto-select first in-stock variant, image gallery
- [ ] **5.6** Search + slug routing
- [ ] **5.7** Cart: add/update/remove, cart locking
- [ ] **5.8** Checkout: address form, cookie persistence (30-day), courier selection, postage + COD calc
- [ ] **5.9** Blog listing + detail with unique-view counter (IP based)
- [ ] **5.10** Customer account: login, register, phone verification, order history
- [ ] **5.11** Support ticket (customer side)
- [ ] **5.12** Static pages: about, policy, terms, contact (Google Maps embed)
- [ ] **5.13** Referral flow
- [ ] **5.14** Inertia SSR verification — crawler sees rendered HTML on product/category pages
- [ ] **5.15** Re-implement PWA: manifest, service worker, install banner (Android + iOS)

## Phase 6: Payments & Shipping Integrations

- [ ] **6.1** Port `SenangPayGateway` → `app/Services/Payments/SenangPayGateway` (preserve SHA256 hash logic exactly)
- [ ] **6.2** Port `BayarcashGateway` → `app/Services/Payments/BayarcashGateway` (5-field intent checksum, 13-field callback checksum)
- [ ] **6.3** COD flow + COD charge calculation
- [ ] **6.4** Stripe integration
- [ ] **6.5** Payment channel toggles (`senangpay_enabled`, `bayarcash_enabled`, `cod_enabled`)
- [ ] **6.6** Callback + return-URL routes with signature verification; CSRF exemption only on callbacks
- [ ] **6.7** Thank-you / failed pages (shared across channels)
- [ ] **6.8** Port `NinjaVanGateway` + token cron → Laravel Scheduler
- [ ] **6.9** DHL + J&T shipping services
- [ ] **6.10** Mail: PHPMailer/MailerSend → Laravel Mailables (order confirmation, ticket replies)
- [ ] **6.11** Queued jobs: `CheckAbandonJob`, `LiveOrdersJob`, `LiveUpdaterJob`
- [ ] **6.12** QR/barcode consolidation → `endroid/qr-code` + picqer barcode; PDF via `barryvdh/laravel-dompdf`

## Phase 7: Security Hardening *(Gate 6 — Argus/Cipher/Aegis)*

- [ ] **7.1** All credentials in `.env` — zero hardcoded secrets (source leaked DB password + GitHub PAT)
- [ ] **7.2** CSRF on all state-changing routes
- [ ] **7.3** Mass-assignment guards on every model (`$fillable`)
- [ ] **7.4** Authorization check on every admin route (source relied on ad-hoc `checkAccess()`)
- [ ] **7.5** File-upload validation: MIME, size, extension allowlist, non-public storage
- [ ] **7.6** Rate limiting on login, phone verification, checkout, callbacks
- [ ] **7.7** SQL injection sweep — Eloquent/bindings only, no raw interpolation
- [ ] **7.8** XSS: verify Vue escaping, audit every `v-html`
- [ ] **7.9** Payment callback replay protection + idempotency
- [ ] **7.10** Dependency audit (`composer audit`, `npm audit`)

## Phase 8: Testing & Verification *(Gate 4 — Probe/Echo)*

- [ ] **8.1** Pest unit tests: pricing, postage, COD charge, stock math
- [ ] **8.2** Feature tests: auth (both guards), authorization matrix
- [ ] **8.3** Feature tests: product CRUD, variant + stock flows
- [ ] **8.4** Feature tests: cart → checkout → order creation
- [ ] **8.5** Payment gateway tests with mocked callbacks (valid + tampered checksums)
- [ ] **8.6** Data-parity harness: replay real orders through old vs new pricing, diff every total
- [ ] **8.7** Browser E2E smoke of the critical journey (claude-in-chrome)
- [ ] **8.8** Manual smoke checklist for admin (43 screens) and storefront (23 screens)

## Phase 9: Cutover

- [ ] **9.1** Production `.env` + config caching
- [ ] **9.2** Deployment runbook (queue worker, scheduler, SSR node process)
- [ ] **9.3** Asset build pipeline
- [ ] **9.4** Parallel run against live data; reconcile
- [ ] **9.5** Cutover + rollback plan
- [ ] **9.6** Handoff doc (Artifact, per 52-handoff-protocol)

---

## Open Risks

1. **Data source for import** — local `2025_rozeyana` (port 3307) has only **22 of 59 tables**. `base_ecom.sql` (4.1 MB) has all 59. Live remote DB is the true source. *Must confirm which is authoritative before Phase 2.13.*
2. **Legacy password hashes** — unknown algorithm in `member_hq`/`members`; if not bcrypt, needs rehash-on-login shim or a forced reset.
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
| 6 | Import dump into scratch DB `shaniena_src`, generate migrations from metadata | Far more reliable than parsing 59 `CREATE TABLE` blocks by hand. Needed `utf8mb4_0900_ai_ci` → `utf8mb4_unicode_ci` (MySQL 8 dump, MariaDB 10.4 local). |
| 7 | `timestamp` → `datetime`, `softDeletes()` → explicit `datetime` | Project convention: `datetime` avoids MySQL timezone conversion. 59 + 17 columns converted. |
| 8 | Zero-date defaults → `nullable()` | Source had `DEFAULT '0000-00-00 00:00:00'` on 8 columns; MySQL strict mode rejects it. |
| 9 | Laravel `users` table not created | App authenticates against migrated `member_hq` / `members`. Kept `password_reset_tokens` + `sessions`. |
