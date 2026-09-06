# Deployment runbook

Everything the shop needs to run, and the exact commands to put a release on a
server. The source project was deployed by uploading files over the live
directory; this replaces that with release directories, an atomic symlink flip
and a preflight that refuses to leave a broken release serving traffic.

Paths below assume `/var/www/shaniena` and Ubuntu 24.04. All of them are
overridable: `APP_DIR`, `REPO`, `PHP_FPM` and `KEEP` are read from the
environment by `deploy/deploy.sh`.

---

## 1. What runs

| Process | Supervised by | What breaks without it |
|---|---|---|
| nginx + php-fpm | systemd | Everything |
| `queue:work redis` ×2 | supervisor (`shaniena-worker`) | Orders are taken, nobody is emailed, couriers are never booked |
| `node bootstrap/ssr/ssr.js` | supervisor (`shaniena-ssr`) | Storefront falls back to client rendering — SEO and first paint, not an outage |
| `schedule:run` every minute | cron (`www-data`) | Abandoned baskets hold stock forever; delivery status stops syncing |
| MySQL 8, Redis 7 | systemd | Everything |

Config for each is in `deploy/`: `nginx/shaniena.conf`,
`supervisor/shaniena-worker.conf`, `supervisor/shaniena-ssr.conf`, `crontab`.

## 2. Server requirements

- PHP **8.2+** (8.4 tested) with `mysqli`/`pdo_mysql`, `redis`, `mbstring`,
  `gd`, `zip`, `intl`, `bcmath`, `curl`, `dom`, `fileinfo`, `opcache`
- `php.ini`: `upload_max_filesize = 8M`, `post_max_size = 8M`,
  `memory_limit = 256M`, `opcache.enable=1`,
  `opcache.validate_timestamps=0` (releases are immutable — the fpm reload in
  `deploy.sh` is what picks up new code)
- MySQL 8.0 with `utf8mb4`, Redis 7 with `requirepass` set
- Node **20+** (24 tested) and npm — the build runs on the server
- Composer 2, git, supervisor, cron, certbot

## 3. First deploy (once per server)

The first deploy is deliberately manual: `deploy.sh` refuses to run without a
shared `.env`, because a generated `APP_KEY` in the wrong place silently
invalidates every session and encrypted column.

```bash
sudo mkdir -p /var/www/shaniena/{releases,shared/storage}
sudo chown -R www-data:www-data /var/www/shaniena
sudo mkdir -p /var/log/shaniena && sudo chown www-data:www-data /var/log/shaniena

# storage/ skeleton (releases symlink to it, so it must be complete)
sudo -u www-data mkdir -p /var/www/shaniena/shared/storage/{app/public,app/private,framework/{cache/data,sessions,views},logs}

# .env — from the template in this repo
sudo -u www-data cp .env.production.example /var/www/shaniena/shared/.env
sudo -u www-data vim /var/www/shaniena/shared/.env      # every CHANGE ME
sudo chmod 600 /var/www/shaniena/shared/.env

# database and the app key
mysql -e "CREATE DATABASE shaniena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'shaniena'@'localhost' IDENTIFIED BY '...'; \
          GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES ON shaniena.* TO 'shaniena'@'localhost';"
php artisan key:generate --show          # paste into shared/.env as APP_KEY

# services
sudo cp deploy/nginx/shaniena.conf /etc/nginx/sites-available/shaniena
sudo ln -s /etc/nginx/sites-available/shaniena /etc/nginx/sites-enabled/
sudo certbot --nginx -d shaniena.com -d www.shaniena.com
sudo nginx -t && sudo systemctl reload nginx

sudo cp deploy/supervisor/*.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update

sudo crontab -u www-data -l > /tmp/cron; cat deploy/crontab >> /tmp/cron
sudo crontab -u www-data /tmp/cron
```

Then run a normal deploy, and seed the reference tables once:

```bash
deploy/deploy.sh origin/main
cd /var/www/shaniena/current
php artisan db:seed --class=ReferenceDataSeeder   # countries, states, 56,234 postcodes

# The first admin. There is no super-admin bypass in the permission matrix, so
# this also grants every page slug — an admin without grants signs in fine and
# then 403s on every screen. The seeder refuses to run in production on its
# default password, so give it a real one here and do not put it in .env:
ADMIN_SEED_EMAIL=you@shaniena.com ADMIN_SEED_PASSWORD='<a real password>' \
    php artisan db:seed --class=AdminUserSeeder

php artisan shaniena:preflight
```

## 4. Every deploy after that

```bash
deploy/deploy.sh              # origin/main
deploy/deploy.sh v1.4.0       # any git ref
```

What it does, in order: fetch the ref → build a new release directory →
`composer install --no-dev` → `npm ci && npm run build` → `migrate --force` →
`php artisan optimize` → flip `current` → reload php-fpm → restart the worker
and SSR → `shaniena:preflight`. **If preflight fails it rolls itself back** and
leaves the failed release on disk for inspection.

Deploys are not zero-downtime in the strict sense: migrations run against the
live database before the flip. That is safe as long as each migration is
backward compatible with the release still serving traffic — see the rule in
[cutover.md](cutover.md#migrations-that-can-be-rolled-back).

## 5. Assets

`npm run build` produces two independent bundles plus the SSR bundle:

| Entry | Output | Ships |
|---|---|---|
| `resources/js/app.js` + `sass/app.scss` | `public/build/assets/app-*.js` | Admin console — CoreUI, Chart.js, TinyMCE |
| `resources/js/storefront.js` + `sass/storefront.scss` | `public/build/assets/storefront-*.js` | Shop — Ashion, Swiper |
| `resources/js/ssr.js` | `bootstrap/ssr/ssr.js` | Shop pages only, for the node renderer |

The split is deliberate and there is a test that keeps it that way: the shop
must not download CoreUI, and the console must not download Ashion. The SSR
bundle resolves `./Pages/Shop/**` only — admin pages fall back to client
rendering by design.

Output is content-hashed, so nginx serves `/build/assets/` with a one-year
immutable cache. `public/sw.js` is *not* hashed and is served
`no-cache` — cached, it would pin customers to a stale service worker across a
deploy.

Anything a customer uploaded or an admin uploaded (product images, ticket
attachments) lives in `shared/storage/app/public` and is reached through the
`public/storage` symlink, which `deploy.sh` remakes in every release.

## 6. Health and preflight

- `GET /up` — Laravel's health endpoint. Point the uptime monitor here.
- `php artisan shaniena:preflight` — sixteen checks that answer "can this host
  serve the shop": app key, debug off, https URL, database, pending
  migrations, Redis round-trip, queue backend, scheduled tasks registered,
  built assets, SSR `/health`, storage link, writable paths, framework caches,
  secure session cookie, real mailer, seeded reference data. Non-zero exit on
  any failure, so it gates the deploy and can be run from cron as a canary.
  `--skip=ssr` where a check does not apply.

## 7. Logs

| What | Where |
|---|---|
| Application | `shared/storage/logs/laravel-YYYY-MM-DD.log` (daily, 14 kept) |
| Queue worker | `/var/log/supervisor/shaniena-worker.log` |
| SSR | `/var/log/supervisor/shaniena-ssr.log` |
| Scheduler | `/var/log/shaniena/schedule.log` |
| nginx | `/var/log/nginx/shaniena.{access,error}.log` |

`LOG_LEVEL=warning` in production on purpose: `debug` writes query bindings,
which for this app means customer addresses and gateway payloads on disk.

## 8. Routine operations

```bash
cd /var/www/shaniena/current

php artisan queue:failed                  # what died
php artisan queue:retry all               # after fixing it
sudo supervisorctl restart shaniena-worker:   # after any code change to a job

php artisan down --secret=<random>        # maintenance mode, /<secret> lets you in
php artisan up

php artisan optimize:clear && php artisan optimize   # after an .env edit
```

An `.env` edit is not picked up until the config cache is rebuilt — that is the
one manual step that is easy to forget, and `optimize:clear` alone leaves
production uncached and slow.

## 9. When something is wrong

| Symptom | First thing to check |
|---|---|
| 500 on every page | `storage/logs`, then `ls -l /var/www/shaniena/current` — is the symlink where you think? |
| Old code still serving | `systemctl reload php8.4-fpm` — opcache holds the previous `$realpath_root` |
| Pages render but look unstyled | `public/build/manifest.json` missing → `npm run build` |
| Product images 404 | `public/storage` link gone → `php artisan storage:link` |
| Orders placed, no email | worker down → `supervisorctl status shaniena-worker:` |
| Google sees an empty page | SSR down → `supervisorctl status shaniena-ssr:`, `curl 127.0.0.1:13714/health` |
| Baskets never expire | scheduler → `php artisan schedule:list`, then the cron entry |
| Gateway callbacks 419 | CSRF exemption — `payment/callback/*` only; check the URL the gateway is configured with |
