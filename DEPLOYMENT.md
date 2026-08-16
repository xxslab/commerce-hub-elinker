# DEPLOYMENT.md — rolling out the License Hub connection flow

Status: **DEPLOYED** (production record below; see `CLAUDE.md`).
This document is specifically the rollout runbook for the work on branch
`claude/project-foundations-xjmw4h`: the secure License Hub connection-code
flow (replacing raw `workspace_id` entry), the `elinker.*` feature-key
entitlement API, and the hardened `RefreshEntitlementJob`. It is an
**incremental deploy onto an already-running app**, not a from-scratch
install — see `INSTALL.md` for that.

The License Hub side of this same work (`product_link_tokens`, the
`/admin/plans` and `/admin/product-links` panels, `POST
/api/v1/product-links/consume`) lives in a separate repository and has its
own `DEPLOYMENT.md`, covering `license.dosieci.pl` on the same Plesk pattern.

## Production deployment record — 2026-08-17

- Target: `https://elink.dosieci.pl`
- Production SHA: `8a531e4cfdfe8af7349704da3c823291d3e416be`
- Previous production SHA: `379bfda58d37247b68a64ee47bf711033447bc4d`
- Migrations: PASS; no new migrations were required by this hotfix.
- Login/routing: PASS (`/login` HTTP 200; protected marketplace routes redirect to login; new channel-edit and marketplace-create routes are present).
- View rendering: PASS for marketplace app form, channel-name edit form, and order detail.
- Order source display: PASS; WooCommerce orders now show the channel domain (for example `gmtools.de` or `max4x4.pl`) and the channel name links to the edit form.
- Marketplace app form: PASS; fixed missing `marketplace` route parameters that caused the Allegro/eBay create pages to return HTTP 500.
- Scheduler: PASS; existing `current` release path remains configured with PHP 8.1.
- Queue: PASS; `elinker-queue.service` active after restart.
- Gating: remains `LICENSE_HUB_ENFORCE_GATING=false`.
- Live Allegro/eBay OAuth verification: NOT RUN; real marketplace app credentials and seller authorization are still required.

## Production deployment record — 2026-08-16

- Target: `https://elink.dosieci.pl`
- Base verified SHA: `6522f4c3c70c599bddae2b646ff829b2d738655b`
- Production SHA: `379bfda58d37247b68a64ee47bf711033447bc4d` (hotfixes: read encrypted WooCommerce credentials through `SalesChannel::getCredentials()` and clear `authentication_error` after a successful connection test).
- Previous rollback reference: `ab42bf748d29c03f0d3a34f5a40f11300bd07eae`; base rollback reference: `6522f4c3c70c599bddae2b646ff829b2d738655b` (the original live tree also had an uncommitted `Kernel.php` divergence).
- Migrations: PASS; additive migrations ran successfully.
- Login/routing: PASS (`/login` HTTP 200; protected routes redirect to login).
- Scheduler: PASS; explicit `current` release path with PHP 8.1.
- Queue: PASS; database queue with enabled/restarting systemd worker.
- S2S signing: configured and verified with a signed request to License Hub; values are server-only secrets.
- Gating: remains `LICENSE_HUB_ENFORCE_GATING=false`.
- Connection-code end-to-end smoke: NOT RUN; no controlled test workspace or business plan catalog existed at deployment time.
- Existing `failed_jobs`: 14 at verification; this pre-existing backlog was not deleted or rewritten.
- Live WooCommerce/Allegro/eBay/InPost/WHMCS verification: NOT VERIFIED; real credentials/business mapping are still required.

## 1. Deployment order between the two apps

License Hub must be deployed and reachable at whatever `LICENSE_HUB_URL`
eLinker will be configured with **before** eLinker's new "Połącz konto z
License Hub" UI is exposed to real company admins — a company admin who
clicks "Połącz konto" against an unreachable Hub gets a graceful, non-fatal
error (`LicenseHubUnavailableException` → "License Hub jest chwilowo
niedostępny"), but the feature is simply unusable until the Hub exists.

Nothing else in eLinker depends on License Hub being up: `/orders`,
dashboards, WooCommerce/Allegro/eBay sync, and InPost shipping are entirely
unaffected — `EnsureActiveSubscription` only ever blocks a handful of new-
resource actions, and only once `LICENSE_HUB_ENFORCE_GATING=true` (see §5).
So the two deploys are **not** a hard release-train dependency; only the
billing UI specifically is inert until License Hub exists.

Recommended order:

1. Deploy License Hub to `license.dosieci.pl` (its own `DEPLOYMENT.md`,
   §§1–6) — completed on 2026-08-16.
2. Confirm `https://license.dosieci.pl/api/v1/entitlements/check` and
   `.../api/v1/product-links/consume` respond (signed 401 for an unsigned
   request is a correct "up" signal — see its smoke tests).
3. Deploy this eLinker branch (§4 below).
4. Only after both are confirmed live: start actually handing out
   connection codes to real customers via License Hub's `/admin/product-
   links` panel.

## 2. What changed, concretely

**eLinker (this branch):**
- New migration: `2026_08_16_000000_create_company_billing_audit_logs_table`.
- `BillingSettingsController`: `link()` removed, replaced by `connect()` +
  `disconnect()`. Route names changed: `settings.billing.link` →
  `settings.billing.connect`, new `settings.billing.disconnect`.
- `RefreshEntitlementJob`: now keyed `WithoutOverlapping` per company, and
  its `$tries`/`$backoff` are load-bearing again (previously dead code —
  see the commit that fixed this).
- New: `FeatureKeys`, `SubscriptionEntitlementService::can()/limit()/
  usage()/assertAllowed()` — available, **not yet wired onto any route**
  (see that class's own docblock for why: every plan currently has zero
  `plan_features` rows).
- New GitHub Actions CI workflow (`.github/workflows/ci.yml`).

**License Hub (`claude/elinker-entitlement-integration`):**
- New migrations: `product_link_tokens`, `product_link_audit_logs`,
  `plans.active` column.
- New endpoint: `POST /api/v1/product-links/consume` (signed, rate-limited).
- New admin panels: `/admin/product-links`, `/admin/plans`.

## 3. Migrations

Both are additive-only (new tables / one new nullable-with-default
column) — neither touches or drops existing data.

```bash
# eLinker (elink.dosieci.pl)
php artisan migrate --force
# -> runs 2026_08_16_000000_create_company_billing_audit_logs_table only;
#    everything else in this branch is application code.

# License Hub (license.dosieci.pl) — as part of its own first deploy
php artisan migrate --force
# -> includes 2026_08_16_000000_create_product_link_tokens_table and
#    2026_08_16_000001_add_active_to_plans_table among the full migration set.
```

## 4. eLinker deploy steps (incremental, on top of the live app)

```bash
# On the server, as the site's system user, from the app root:
git fetch origin
git checkout <VERIFIED_COMMIT_SHA>   # the exact SHA CI passed for, never a branch name

composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# RefreshEntitlementJob's code changed -- restart the queue worker so it
# picks up the new class, don't rely on it reloading on its own:
php artisan queue:restart
```

No new required `.env` variables. `LICENSE_HUB_URL`/`LICENSE_HUB_KEY_ID`/
`LICENSE_HUB_SECRET`/`LICENSE_HUB_ENFORCE_GATING` are unchanged from
`INSTALL.md` §7a (still accurate) — `LICENSE_HUB_KEY_ID`/`_SECRET` must
match `DOSIECI_SIGNING_KEY_ID`/`_SECRET` configured on the License Hub side.

## 5. Scheduler + queue worker (Plesk)

Already documented in `INSTALL.md` §7 for the existing scheduled commands;
this section is the concrete Plesk configuration for
`commerce-hub:sync-entitlement` specifically, since Priorytet 10 asked for
it spelled out rather than left as "configure the scheduler":

1. **Scheduler** (Plesk → Websites & Domains → elink.dosieci.pl →
   Scheduled Tasks) — a single once-a-minute cron entry already covers
   every scheduled command including this one, since Laravel's own
   scheduler (not Plesk) decides which commands actually run each minute
   based on their configured cadence:
   ```
   * * * * * cd <VHOST_ROOT>/commerce-hub && php8.1 artisan schedule:run >> /dev/null 2>&1
   ```
   `commerce-hub:sync-entitlement` itself runs every
   `LICENSE_HUB_REFRESH_INTERVAL` minutes (env var, default 60) — set this
   in `.env`, not by editing the cron line.

2. **Queue worker** — `RefreshEntitlementJob` (like every other queued job
   in this app) needs `QUEUE_CONNECTION=database` or `redis` in production
   (never `sync` — `sync` would run entitlement refreshes inline during
   the scheduler's own request, defeating `WithoutOverlapping` and risking
   a slow License Hub response blocking the scheduler tick). Plesk
   "Scheduled tasks and processes" or a systemd unit:
   ```
   php8.1 <VHOST_ROOT>/commerce-hub/artisan queue:work --tries=3 --max-time=3600
   ```
   with `Restart=always` (or Plesk's own process supervisor) so a crashed
   worker restarts — `--max-time` bounds memory growth, the supervisor
   restarting it is what makes that safe. This is the same worker that
   already processes `SyncSalesChannelOrdersJob`/`PushOrderStatusToSourceJob`/
   etc. — no second, dedicated worker process is needed for entitlement
   refreshes specifically.

## 6. Post-deploy smoke tests

```bash
# eLinker itself still serves normally
curl -sf https://elink.dosieci.pl/login -o /dev/null

# Billing settings page loads for an authenticated company admin (manual
# check — this is a session-authenticated page, not a public API)
#   -> Ustawienia -> Plan i billing shows "Połącz konto z License Hub"
#      (not the old raw workspace_id field)

# Confirm the queue worker actually restarted onto the new job code:
php artisan queue:work --once   # process exactly one queued job, inspect output
```

**Before handing out real connection codes to customers**, run the actual
cross-repo E2E script against a *staging* License Hub instance (never
against production data) to confirm the wire protocol between the two
freshly-deployed apps genuinely works, not just that each one's own test
suite passes in isolation:

```bash
LICENSE_HUB_REPO=/path/to/a/license-hub/checkout scripts/e2e/run.sh
```

This is the same script used in development (`tests/E2E/
RealLicenseHubHandshakeTest.php`) — see its docblock for exactly which 11
scenarios it proves against a real, running Hub with no `Http::fake()`.

## 7. Rollback

Both changes are additive (new table, new nullable column) — rolling back
application code without rolling back the migration is safe; the new
`company_billing_audit_logs` table and `plans.active` column simply go
unused by older code.

```bash
# eLinker: redeploy the previous verified SHA
git checkout <PREVIOUS_VERIFIED_SHA>
composer install --no-dev --optimize-autoloader --no-interaction
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
# php artisan migrate:rollback is NOT required and not recommended here --
# the old code simply never reads the new table/column.
```

If a company got mid-flow (clicked "Połącz konto" during a bad deploy):
`companies.license_hub_workspace_id` is either set correctly (connect
succeeded) or still null (it didn't) — there is no partial/corrupt state to
clean up, since `connect()` only ever writes that column after License
Hub's real response is fully validated.

## 8. Why `LICENSE_HUB_ENFORCE_GATING` stays `false`

Unchanged from `CLAUDE.md`'s existing guidance, restated here because a
deploy runbook is exactly where someone might be tempted to flip it as
part of "finishing the rollout": do **not** set it to `true` until —

1. This connection flow (§§1–4) is live on both domains, confirmed via §6.
2. Real companies are actually linked via the new connection-code flow —
   not just capable of being linked.
3. License Hub's plan catalog (`/admin/plans`) has real, non-empty
   `plan_features` rows for the plan codes real customers are on. It ships
   empty by design (see that repo's `PlanController` docblock) — no
   authoritative pricing/limit data exists anywhere in either repo to seed
   it with, so an admin must configure it deliberately, once real business
   terms exist.

Until all three hold, `SubscriptionEntitlementService::isGatingApplicable()`
correctly returns `false` for every company, and `can()`/`limit()`/
`assertAllowed()` are unused code paths, not because they're broken but
because nothing has asked them to run yet.
