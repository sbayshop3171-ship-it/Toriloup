# Controlled Soft Launch Runbook

Week 8 target window: September 7, 2026 to September 13, 2026.

This runbook defines the controlled soft-launch process for Toriloup.

## Exit criteria

Soft launch can be approved only when all of these are true:

- full regression passes
- tenant leak checks pass
- wrong-host checks pass
- billing and domain critical paths pass
- live-like smoke checks pass
- selected merchant cohort is prepared and reviewed

## QA gate

Run the complete gate from the project root:

```bash
bash scripts/qa-soft-launch.sh
```

Optional manifest dry run:

```bash
SOFT_LAUNCH_MANIFEST="docs/examples/soft-launch-merchants.example.json" bash scripts/qa-soft-launch.sh
```

The gate performs:

- deploy health checks
- smoke checks
- backup freshness audit
- soft-launch readiness audit
- full Laravel regression suite
- production frontend build

## Readiness audit

Use this command for a quick launch snapshot:

```bash
php artisan ops:soft-launch-audit
```

It reports:

- total tenants
- active, draft, and suspended tenants
- live and basic-complete tenants
- pending and verified custom domains
- active subscriptions

## Selected merchant onboarding

Prepare a cohort manifest and dry-run it first:

```bash
php artisan ops:soft-launch-onboard docs/examples/soft-launch-merchants.example.json --dry-run
```

When approved, run the real onboarding:

```bash
php artisan ops:soft-launch-onboard /absolute/path/to/soft-launch-merchants.json --mark-live
```

Command behavior:

- skips existing merchants safely
- provisions new tenants through the SaaS merchant registration service
- can mark the created cohort as `live`
- prints a per-merchant result table and storefront URLs

## Recommended soft-launch cohort

Use a very small first cohort:

- 3 to 5 merchants only
- different business types if possible
- one merchant already familiar with your workflow
- one merchant likely to stress billing/domain setup
- one merchant likely to stress storefront checkout

## Manual approval checklist

Before saying "approved":

1. Owner login works only on `owner.company.com`.
2. Merchant login and register work only on `merchant.company.com`.
3. Storefront login and account flows work only on verified tenant storefront domains.
4. One merchant can create products, manage settings, request a domain, and view billing summary.
5. One customer can browse, add to cart, checkout, and view order history end-to-end.
6. A custom domain can be verified without breaking merchant admin access.
7. Cross-tenant access attempts fail.
8. Deploy smoke and rollback procedure are ready.

## Approval evidence to capture

Keep these before approving the soft launch:

- `bash scripts/qa-soft-launch.sh` output
- `php artisan ops:soft-launch-audit` output
- onboarding dry-run or live cohort output
- latest successful CI run
- latest successful deploy smoke run from staging or live-like environment

## Suggested rollout sequence

1. Run the QA gate.
2. Review the readiness audit.
3. Dry-run the cohort manifest.
4. Onboard the selected merchants.
5. Ask each merchant to complete core setup.
6. Re-run smoke checks after onboarding.
7. Approve the soft launch only after owner and merchant signoff.
