# Shaniena — online shop

Laravel 12 + Vue 3 rewrite of the Shaniena/Rozz Beauty storefront and back office,
migrated from the original native-PHP application (raw `mysqli`, one file per
screen, four standalone cron scripts, credentials in `config/function.php`).

It is one deployment serving two surfaces:

| Surface | Who | Stack |
|---|---|---|
| **Storefront** — 19 screens | Customers, in Malaysia and overseas | Vue 3 + Inertia on the Ashion template, server-rendered for crawlers |
| **Admin console** — 31 screens | HQ staff: orders, stock, products, couriers, settings | Vue 3 + Inertia on CoreUI, behind a per-page permission matrix |

Fewer components than the source had screens (43 admin, 23 storefront), because
several were merged: one payment-settings screen replaces the source's page per
gateway, one sales report replaces its split stats/report pair.

The shop takes MYR and foreign-currency orders, prices postage by weight and
zone, charges a COD fee against a benchmark, settles through SenangPay,
Bayarcash and COD, and books consignments with DHL, J&T and NinjaVan with
printable AWB labels.

**Status:** eight of nine migration phases are complete. Cutover is written and
rehearsable but has not run — the live remote database is the only usable
migration source and nobody has read access to it yet. See
[project-plan.md](project-plan.md) for the phase-by-phase detail and the dated
verification log, and [docs/cutover.md](docs/cutover.md) for what is blocked.

---

## Requirements

- PHP **8.2+** (8.4 tested) with `pdo_mysql`, `redis`, `mbstring`, `gd`, `zip`, `intl`, `bcmath`, `curl`, `dom`, `fileinfo`
- MySQL 8.0 (`utf8mb4`)
- Redis 7 — sessions, cache and queue
- Node **20+** (24 tested), Composer 2

## Getting started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
# .env: point DB_* at your MySQL, REDIS_* at your Redis

php artisan migrate
php artisan db:seed --class=ReferenceDataSeeder   # 5 countries, 16 states, 56,234 postcodes
php artisan db:seed --class=AdminUserSeeder       # admin@shaniena.com / admin1234, all page grants

composer dev            # serve + queue worker + log tail + vite, all at once
```

- Storefront — <http://127.0.0.1:8000>
- Admin console — <http://127.0.0.1:8000/admin/login>

The reference seed is not optional: with no countries the shop cannot price
anything, and with no postcodes the checkout cannot resolve a state.

**Server-side rendering** is separate from `composer dev`; the storefront falls
back to client rendering without it, which is fine locally:

```bash
npm run build:ssr && php artisan inertia:start-ssr    # 127.0.0.1:13714
```

---

## How it is laid out

```
app/
  Http/Controllers/Admin/     console screens, one controller per job
  Http/Controllers/Shop/      storefront, checkout, payment callbacks
  Http/Middleware/            EnsurePageAccess, HandleStorefrontRequests, HandleInertiaRequests
  Services/
    Storefront/               Basket (pricing, postage, COD), Catalogue, PlaceOrder
    Payments/                 SenangPay, Bayarcash, COD, and the channel registry
    Shipping/                 DHL, J&T, NinjaVan, AWB labels, tracking sync
    PageAccess.php            the admin permission matrix
    AdminNavigation.php       the sidebar, filtered through that matrix
  Jobs/                       the three scheduled jobs (see routes/console.php)
  Models/                     54 models, bound to the source's table names
database/migrations/          80 migrations, from 59 source tables
resources/js/
  Pages/Admin/                31 console screens
  Pages/Shop/                 19 storefront screens
  Storefront/                 shop components — cart drawer, visitor card, product card, hero, SEO
  Layouts/                    AdminLayout, StorefrontLayout, AuthLayout
resources/sass/
  app.scss                    console (CoreUI)
  storefront.scss             shop (Ashion) + storefront/_motion.scss
deploy/                       nginx, supervisor, cron, deploy.sh, rollback.sh
docs/                         deployment runbook, cutover and rollback plan
tests/                        415 Pest tests, plus a browser journey in tests/e2e
```

**Two bundles, on purpose.** The shop must not download CoreUI and the console
must not download Ashion; the SSR bundle resolves `Pages/Shop/**` only, because
the console is behind a login and indexed by nobody. There are tests that keep
it that way.

---

## Things to know before changing anything that touches money

These are the rules the source got wrong, or got right in a way that is not
obvious. Each one has a test.

- **Baskets are priced from the database, never from the request.** A tampered
  line price is ignored; the customer pays what the product costs now.
- **Postage takes the first kilogram flat and `ceil()`s the rest.** 1.1 kg is
  charged as 2 kg.
- **The COD benchmark boundary belongs to the above-benchmark fee.** The source
  compares `subtotal < benchmark`, so on a RM100 benchmark RM99.99 pays
  `cod_fee_below` and RM100.00 pays `cod_fee_above`. Which of the two is larger
  is whatever the `cod_charges` row says — it is not a rule.
- **The shipping zone is decided by whether a country has configured states**,
  not by a hardcoded Malaysia id.
- **The basket is keyed to a `cart_token` cookie, not the session id.** Signing
  in regenerates the session, and the source orphaned the customer's cart every
  time it happened.
- **Order reference hashes are 64 random hex characters** — not a hash of
  id + name + time, which was guessable.
- **A payment callback claims an order with a conditional `UPDATE`**, not a read
  followed by a write. Two simultaneous callbacks cannot both confirm.
- **`payment/callback/*` is the only CSRF-exempt path**, and each callback
  authenticates itself with the gateway's own signature.
- **`Order` deliberately omits status, money and courier columns from
  `$fillable`.** Legitimate writers use `forceFill`.
- **There is no super-admin bypass in the permission matrix.** Every admin route
  carries a `page:<slug>` grant; an admin with no `role_access` rows signs in
  fine and then 403s everywhere. `AdminUserSeeder` grants all slugs.
- **Legacy passwords upgrade themselves.** `member_hq` rows are unsalted
  SHA-256; the first successful sign-in rehashes to bcrypt.

---

## Commands

```bash
composer dev                      # serve + queue + logs + vite
composer test                     # config:clear, then the full suite
php artisan test --filter=Basket  # one file or one test

npm run build                     # client bundles + SSR bundle
npm run dev                       # vite only

php artisan shaniena:preflight    # 16 checks: can this host serve the shop?
php artisan queue:work redis
php artisan schedule:list         # baskets expire, visitors refresh, delivery syncs
```

`shaniena:preflight` exits non-zero on failure, so it gates a deploy and runs
happily from cron as a canary. `--skip=ssr` where a check does not apply.

---

## Testing

```bash
php artisan test                  # 415 tests, 1,963 assertions
vendor/bin/pint --test            # style
python3 ~/Desktop/CS/coresentinel.py verify
```

Tests run against a separate `shaniena_v3_test` database (see `phpunit.xml`).

Beyond the usual feature coverage, a set of **standing guards** fail when the
shape of the code slips rather than when behaviour breaks: no closure under
`config/` (which would break `config:cache` on the server), no admin route
without a permission grant, no model with an open mass-assignment guard, no
`v-html` anywhere, no raw SQL concatenating a variable, no duplicate route
names, no undocumented env knob, no upload without a MIME allowlist and a
ceiling, and no admin screen missing from the smoke walk.

`tests/e2e/critical-journey.mjs` drives a real browser over CDP — land, open a
product, add to basket, check out, price postage, place a COD order — using
Node's built-in WebSocket, so there is nothing to install:

```bash
php artisan serve --port=8123
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless=new --remote-debugging-port=9222 --user-data-dir=/tmp/e2e about:blank
OUT=/tmp node tests/e2e/critical-journey.mjs
```

It exits non-zero on any failure. It is what found three defects the unit suite
could not see, because each step passed in isolation — most seriously, COD
orders losing every line item ten minutes after being placed.

> **Note:** while `npm run dev` is running, `public/hot` makes Laravel emit Vite
> dev asset URLs, and two tests that assert on *built* asset URLs
> (`StorefrontSeoTest`, `StorefrontHomeTest`) fail. Stop the dev server before a
> full run.

---

## Deployment

Release directories with an atomic symlink flip — never an upload over the live
directory. `deploy/deploy.sh` builds, migrates, caches, flips, reloads php-fpm,
restarts the queue worker and the SSR process, runs preflight, and **rolls
itself back if preflight fails**.

- [docs/deployment.md](docs/deployment.md) — server requirements, first-time
  provisioning, deploys, logs, troubleshooting
- [docs/cutover.md](docs/cutover.md) — the 20-step cutover, the one irreversible
  step, and all three rollback procedures
- [.env.production.example](.env.production.example) — annotated production
  template; every secret ships empty

Five things must be running in production: nginx + php-fpm, two queue workers,
the Inertia SSR node process, and `schedule:run` from cron. Config for each is
in `deploy/`.

---

## Not migrated

Deliberate, and listed so nobody goes looking:

- **Stripe** — scoped, never built; the source had no working Stripe flow.
- **Referrals** — no implementation and no schema in the source to port.
- **Loyalty points, phone verification, announcements, NinjaVan/PosLaju
  settings** — the source has no DDL for these tables anywhere, and money
  columns cannot be guessed.
- **The `shop/*` subsystem** — reads a second, separate database; out of scope.
- **Foreign keys and new indexes** — deferred until after the data import, since
  adding them first would fail on orphaned rows.
- **Data import** — `shaniena:import` waits on access to the live database.

## Conventions

- `vendor/bin/pint` before committing; CI-equivalent is `pint --test`.
- Comments explain *why*, especially where this app deliberately differs from
  the source. If a decision took thought, write down what it replaced.
- A new admin screen either joins the smoke walk or is recorded in
  `tests/Support/Screens.php` against the test that covers it — doing neither
  fails the suite.
- Migrations must be safe for the release *currently* serving traffic: add
  columns, drop them a deploy later. That rule is what keeps rollback a symlink
  move.
