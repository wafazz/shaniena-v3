# Cutover and rollback plan

How the live shop moves from the native-PHP application to this one, and how it
moves back if it has to.

Deploying a *release* is routine and reversible — `deploy/rollback.sh` is a
symlink move. **Cutover is neither**, because two things are one-way: the
customer data imported into the new database keeps changing the moment traffic
arrives, and the payment gateways only ever call one callback URL. Everything
below exists to keep those two decisions as late and as reversible as possible.

---

## Where this plan is blocked

Steps 2 and 4 below cannot be rehearsed yet. `base_ecom.sql` is effectively a
schema dump — `customer_orders`, `order_details`, `products`,
`product_variants` and `role_access` all have zero rows — and the local
`2025_rozeyana` database is test data (2 orders, 5 products, 1 admin).
**The live remote database is the only migration source**, and nothing has been
imported, reconciled or parity-checked without it.

Needed from Fakrul: read-only credentials on the live database, and a window
to take a dump. Until then plan items 2.13 (the `shaniena:import` command),
2.14 (the reconciliation report), 8.6 (the pricing parity harness) and 9.4 (the
parallel run) stay open, and no cutover date can be set.

## Migrations that can be rolled back

The one rule that makes every deploy reversible:

> A migration must be safe for the release *currently serving traffic*, not
> only for the one shipping with it.

In practice: add columns and tables, never drop or rename them in the same
deploy as the code that stopped using them. A drop goes out one deploy later,
once the release that used the column can no longer be rolled back to. Column
renames are an add, a backfill, and a drop — three deploys.

This is what makes `rollback.sh` safe: it moves the symlink and restarts
processes, and it does **not** touch the database. If a migration in the failed
release dropped something, there is nothing to roll back to.

## Timeline

### T-7 days — rehearsal
1. Stand up a staging host from [deployment.md](deployment.md), pointing at a
   copy of the live database, with `APP_ENV=production` and a `noindex` robots
   rule. Everything below is rehearsed here first, end to end, at least twice.
2. Run the import against a live dump: `php artisan shaniena:import`, then the
   row-count reconciliation report, old versus new, for all 59 source tables.
   Zero unexplained differences, or the date moves.
3. Run the pricing parity harness (plan item 8.6): replay real orders through
   the old and new pricing and diff every total — subtotal, postage, COD
   charge, currency conversion. **Any difference is a stop.**
4. Place a real, small-value order on staging against each live gateway
   (SenangPay, Bayarcash, Billplz) with production credentials, and refund it.
   Callback URL, checksum and confirmation all exercised for real.
5. Book one real courier consignment per active courier, print the AWB,
   then cancel it.

### T-2 days — freeze
6. Freeze the source project. No further code or settings changes; anything
   that must change is applied to both.
7. Drop the DNS TTL for `shaniena.com` to 300s. Do this **two days out** — the
   old TTL has to expire before the short one is useful.
8. Confirm every operator has an account on the new console and can sign in.
   Legacy SHA-256 passwords are accepted once and rehashed to bcrypt on first
   login, so this is worth proving rather than assuming.

### T-0 — the window (target: a weeknight after 01:00 MYT, the quietest hour)

| # | Step | Reversible? |
|---|---|---|
| 9 | Put the source shop into maintenance mode. Note the last order id. | yes |
| 10 | Wait for in-flight gateway callbacks to settle — 15 minutes, then check for orders stuck awaiting payment. | yes |
| 11 | Take the final dump of the live database. | yes |
| 12 | `php artisan down` on the new host, run the import, run reconciliation. | yes |
| 13 | `php artisan shaniena:preflight` — all sixteen checks green. | yes |
| 14 | Point the gateway callback URLs and courier webhooks at the new host. | **one-way in practice** — each gateway is a manual change in someone else's dashboard |
| 15 | Repoint DNS at the new server. | yes, at TTL cost |
| 16 | `php artisan up`. Walk the critical journey by hand on the live site: land → product → basket → checkout → pay a small real order → verify it appears in the queue with its line items → print its AWB. | — |
| 17 | Watch for 60 minutes: `storage/logs`, `queue:failed`, nginx 5xx rate, the order queue. | — |

Step 14 is the point of no return in operational terms. Everything before it is
a staging exercise with the old shop still able to serve.

### T+1 day
18. Reconcile: every order placed on the new shop settled, no failed queue
    jobs, no 5xx in nginx, Search Console fetching server-rendered pages.
19. Raise the DNS TTL back to 3600s.
20. Keep the old application and its database **read-only and untouched for 30
    days**. It is the only copy of anything the import got wrong.

## Rollback

Three different failures, three different responses.

**A bad release, cutover already done.** The normal case, and cheap:

```bash
/var/www/shaniena/current/deploy/rollback.sh          # previous release
/var/www/shaniena/current/deploy/rollback.sh /var/www/shaniena/releases/2026...  # a specific one
```

Symlink move, fpm reload, worker and SSR restart, preflight. Under a minute,
no rebuild, database untouched.

**Cutover fails before step 14.** Abort: take the new host down, bring the
source shop out of maintenance mode, leave DNS alone. Nothing was lost but the
window, because no customer reached the new application.

**Cutover fails after step 14 — orders have been taken on the new shop.**
Going back is a data-loss decision, not a technical one, and it needs Fakrul.
The mechanics:

1. Put the new shop in maintenance mode.
2. Export every order placed since step 16 — this is the set that will be lost
   or has to be re-entered by hand into the old shop.
3. Point the gateway callback URLs and courier webhooks back.
4. Repoint DNS; bring the source shop up.
5. Reconcile the exported orders by hand, and contact those customers.

Triggers that justify it: payments settling against the wrong order, prices
differing from the source, orders arriving without line items, or a sustained
5xx rate. A slow page, a broken admin screen or a styling defect is not a
rollback — it is a hotfix release.

## What is deliberately not part of cutover

- **Credential rotation.** The source repository has the production database
  password, J&T's tracking signing key and Billplz API keys in its git history.
  They need rotating, and this migration does not do it — but a rotation during
  the window would be indistinguishable from a cutover failure, so do it a week
  before or a week after, never during.
- **The `shop/*` subsystem.** It reads a second, separate database that is not
  part of `2025_rozeyana` and was never migrated. It is out of scope and stays
  where it is.
