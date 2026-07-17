# Go-Live Remaining Master Checklist

Date: July 17, 2026
Status: Repo-backed go-live assessment
Purpose: Turn the current SaaS build into a launchable, tenant-safe, production-ready product

## 1. Executive Summary

The project is no longer at the "idea" stage.

It already has a meaningful SaaS foundation:

- multi-tenant registry and domain tables
- platform, merchant, and storefront API splits
- merchant commerce APIs for products, stock, suppliers, purchases, orders, returns, and POS
- platform APIs for tenants, plans, subscriptions, providers, and domains
- tenant isolation tests
- billing and quota enforcement tests
- CI and deploy workflow base

But it is not fully live-ready yet.

The main reason is not missing database structure.
The main reason is that the product is currently split between:

- new SaaS APIs
- legacy admin routes
- one central SPA shell
- incomplete production hardening

So the remaining work is less about inventing the architecture and more about finishing the ownership model, critical flows, and live operations.

## 2. Current Verified Status

This assessment is based on the current repository state on July 17, 2026.

### 2.1 Strong areas already in place

- SaaS route files exist for platform, merchant, storefront, and auth splits.
- Merchant SaaS APIs already exist for products, catalog, suppliers, purchases, damages, orders, returns, stock, POS, billing, and domains.
- Platform SaaS APIs already exist for overview, tenants, plans, subscriptions, domains, and providers.
- Storefront bootstrap and storefront auth entry points already exist.
- Tenant, customer, domain, settings, feature flag, provider, audit, and billing migrations already exist.
- Feature tests pass for SaaS foundation, platform workspace, merchant tenant isolation, and billing workspace.

### 2.2 Verified test result

The current feature suite passes:

- 23 tests passed
- 169 assertions

Command used:

```bash
php artisan test --testsuite=Feature
```

### 2.3 What this means

This repo is already beyond a risky prototype.
The backend core is real.

What is still missing is:

- final surface ownership cleanup
- complete end-to-end UI behavior
- customer-ready storefront/account quality
- production authentication/session hardening
- scheduler/queue/backup/rollback operations
- controlled go-live workflow

## 3. Repo-Backed Critical Risks

These are the most important risks visible in the repository right now.

### Risk 1: legacy admin surface still overlaps with the SaaS model

The legacy `api/admin/*` routes are still active and still expose store-operation modules such as products, orders, customers, stock, suppliers, promotions, settings, and reports.

Relevant files:

- `routes/api.php`
- `app/Http/Middleware/AdminAccess.php`

Why this matters:

- owner and merchant responsibilities are not fully locked yet
- legacy admin endpoints can keep the old mixed model alive
- owner-only platform control is not guaranteed if the old admin surface remains the main workspace

### Risk 2: owner and merchant web domains are only scaffold-level

The domain-specific web route files for owner and merchant currently expose only `/up`.

Relevant files:

- `routes/web/platform.php`
- `routes/web/merchant.php`

Why this matters:

- the API split is stronger than the web experience split
- the platform still behaves like one shared SPA shell instead of clearly separated owner and merchant applications

### Risk 3: the main SPA router still centers everything around `/admin`

The frontend router still uses one central admin-style application and routes most operational flows through `/admin/*`.

Relevant files:

- `resources/js/router/index.js`
- `resources/js/components/frontend/auth/MerchantRegisterComponent.vue`
- `resources/js/components/frontend/auth/LoginComponent.vue`

Why this matters:

- merchant registration still pushes into `admin.dashboard`
- owner and merchant UX separation is not fully reflected in the router
- the user experience still carries legacy admin assumptions

### Risk 4: first-party cross-subdomain cookie auth is not production-ready yet

Current configuration shows:

- no `statefulApi()` middleware setup in `bootstrap/app.php`
- Sanctum stateful domains are still default/local-oriented
- session cookie domain is not explicitly set
- CORS credentials support is disabled

Relevant files:

- `bootstrap/app.php`
- `config/sanctum.php`
- `config/session.php`
- `config/cors.php`

Why this matters:

- if you want first-party session/cookie auth across `owner.company.com`, `merchant.company.com`, and storefront subdomains, this is not fully hardened yet
- if you stay token-only, you still need to finalize that decision and test it deliberately

### Risk 5: storefront auth still bridges through the legacy `users` flow

The storefront auth controller still delegates login, signup, and reset to legacy auth controllers and then syncs a shadow `customers` record.

Relevant files:

- `app/Http/Controllers/Saas/StorefrontAuthController.php`
- `routes/api/storefront-auth.php`

Why this matters:

- the customer model split exists, but the live auth path is still transitional
- this is acceptable for staged migration, but it is not the cleanest end-state yet

### Risk 6: scheduler and recurring operations are not built yet

`routes/console.php` currently contains only the default inspire command.

Why this matters:

- no recurring billing jobs
- no failed-job pruning
- no token pruning
- no domain recheck loop
- no backup audit or scheduled operational tasks

### Risk 7: deploy automation exists but production operations are incomplete

Deploy workflow and deploy script exist, but they currently stop at build, migrate, storage link, and caches.

Relevant files:

- `.github/workflows/deploy.yml`
- `scripts/deploy-live.sh`
- `DEPLOYMENT_RUNBOOK.md`

Missing operational coverage:

- queue worker restart or supervisor integration
- scheduler verification
- smoke test after deploy
- rollback path
- backup and restore verification
- failed job handling and alerting

### Risk 8: Laravel support window is close

The app currently requires Laravel 12.
As of July 17, 2026, Laravel 12 bug fix support ends on August 13, 2026.

Why this matters:

- a launch on Laravel 12 is still possible now
- but the upgrade plan to Laravel 13 should already be scheduled

## 4. Bucket A: Must Before Live

These items should be completed before public launch.

## 4.1 Ownership and scope lock

- Lock the final rule: Owner = platform operator, Merchant = store operator, Customer = shopper.
- Restrict or retire legacy `api/admin/*` store-operation access paths that violate the new ownership model.
- Ensure owner workflows only manage tenants, plans, subscriptions, providers, domains, support, and platform reporting.
- Ensure merchant workflows own products, inventory, orders, returns, staff, store settings, and daily operations.
- Remove any owner-facing shortcuts that still behave like store admin functions.

## 4.2 Owner and merchant application split

- Replace the practical dependence on `/admin/*` as the default workspace.
- Give `owner.company.com` a real owner workspace shell.
- Give `merchant.company.com` a real merchant workspace shell.
- Make merchant registration land inside merchant onboarding, not the generic admin dashboard.
- Make login redirects surface-aware and role-aware.

## 4.3 Authentication and host safety

- Finalize owner login on `owner.company.com` only.
- Finalize merchant login and registration on `merchant.company.com` only.
- Finalize storefront customer login on tenant storefront domains only.
- Verify wrong-host protection for all auth endpoints.
- Decide clearly between:
  - first-party cookie/session auth across subdomains, or
  - bearer token auth as the primary production model
- If using first-party cookie auth, finish Sanctum stateful domain, CORS credentials, session domain, and secure cookie setup.
- If using bearer tokens, test refresh, expiry, logout, and multi-tab behavior on all three surfaces.

## 4.4 Customer identity and account completion

- Finalize customer signup/login/logout flow.
- Finalize forgot password and reset flow.
- Finalize customer account dashboard, addresses, orders, and return request access.
- Verify customer can only see records for the current tenant storefront.
- Decide whether to keep the transitional shadow-customer bridge for V1 or complete a cleaner direct `Customer` auth model before launch.

## 4.5 Merchant core store operations

- Full product create, edit, delete, show, and search usability.
- Product image upload and gallery reliability.
- Categories, brands, units, attributes, options, and variations full flow.
- Stock visibility and stock update flow.
- Supplier create, edit, list, and delete flow.
- Purchase create, edit, payment history, and attachment flow.
- Damage flow if it is part of launch scope.
- Order list, order detail, status update, and payment status update.
- Return order and return/refund basic merchant handling.
- Merchant customer list and order history views.

## 4.6 Merchant store settings needed for launch

- Store profile data
- logo and favicon
- business contact info
- policy content
- shipping and delivery settings
- safe payment method selection
- domain settings
- basic billing and plan visibility

If merchants cannot configure these safely themselves, V1 live will feel unfinished.

## 4.7 Storefront and checkout completion

- Tenant/domain storefront resolution
- homepage data rendering
- category browsing
- product details
- cart add/update/remove
- checkout completion
- address selection
- order confirmation
- order tracking
- customer order history
- wishlist only if you want it in V1

At minimum, browse -> cart -> checkout -> order success -> account order history must be dependable.

## 4.8 Platform control tower minimum

- tenant list and detail
- approve, suspend, reactivate
- plan assignment
- subscription summary
- invoice mark-paid flow
- provider configuration
- domain verify and primary selection
- platform overview dashboard

These are the minimum platform controls already supported at the API level and must be production-usable in UI and permissions.

## 4.9 Billing and commercial safety

- Confirm default plan seeding and assignment
- Confirm plan limit enforcement
- Confirm merchant billing summary correctness
- Confirm invoice lifecycle used in V1
- Define suspension rules for unpaid merchants
- Add owner override path for billing issues

V1 does not need perfect finance automation, but it must not allow accidental overuse, silent expiry, or ambiguous merchant status.

## 4.10 Production and DevOps hardening

- finalize production `.env`
- confirm `APP_DEBUG=false`
- confirm queue connection choice
- confirm cache/session/queue drivers
- confirm storage write permissions
- confirm file upload storage path
- confirm DB migration on production
- confirm SSL and domain routing
- confirm queue worker process
- confirm scheduler cron
- confirm failed job persistence
- confirm deploy user permissions
- confirm deploy path and ownership

## 4.11 Scheduler and operational jobs

Before launch, add at least the operational jobs that matter for V1:

- invoice or subscription state checks
- failed job pruning
- expired Sanctum token pruning if token expiry is enabled
- domain verification rechecks if using manual/async verification
- backup audit command or backup status task

## 4.12 End-to-end launch testing

Current feature tests are valuable, but they are not enough for go-live.

Before live, complete:

- owner login manual test
- merchant register test
- merchant login test
- merchant store settings test
- merchant product CRUD test
- merchant image upload test
- merchant order lifecycle test
- merchant return/refund test
- merchant domain request test
- storefront browse test
- cart test
- checkout test
- customer signup/login test
- customer order history test
- wrong-host protection test
- cross-tenant isolation test
- production deploy test
- backup restore drill
- rollback drill

Browser-level smoke tests are strongly recommended because current automated coverage is API-heavy, not browser-heavy.

## 5. Bucket B: Recommended Before Live

These are not absolute blockers, but finishing them before launch would make the product meaningfully stronger.

## 5.1 Merchant UX polish

- dashboard summary polish
- onboarding checklist polish
- cleaner product gallery UX
- variation UX polish
- category/brand/unit screens polish
- order detail UX polish
- better empty states and validation states

## 5.2 Merchant growth basics

- coupons
- promotions
- product sections
- banners
- reviews moderation
- subscribers/newsletter
- custom pages/content blocks

If your launch story depends on "full merchant marketing control", these move closer to Must.

## 5.3 Owner operational polish

- tenant filter/search polish
- subscription assignment UX polish
- provider config UX polish
- support notes and audit log UI
- merchant health monitoring
- domain verification workflow polish

## 5.4 Monitoring and observability

- basic application monitoring
- error alerts
- failed job visibility
- deploy notifications
- queue backlog visibility
- domain verification failure visibility

## 5.5 Safer deployment workflow

- post-deploy smoke command
- health checks beyond `/up`
- written rollback runbook
- staged pre-live checklist
- seed strategy for live/staging

## 5.6 Laravel upgrade planning

- schedule Laravel 13 upgrade after launch stabilization
- avoid launching into an upgrade panic window

## 6. Bucket C: Post-Launch / Phase 2

These can safely move after V1 if launch scope is controlled.

- Flutter customer app live dependency
- advanced campaigns
- push notification campaign center
- deep analytics dashboards
- theme selection/customization studio
- homepage section visual builder
- full SEO/meta management layer
- advanced staff/sub-role management
- preset manager polish
- fraud/risk automation
- advanced inventory workflows beyond the core launch scope
- multi-store per merchant
- automation and loyalty features

## 7. Recommended V1 Live Scope

The safest V1 launch scope is:

- owner platform control
- merchant full store admin for core operations
- customer storefront web experience
- basic domain flow
- basic billing visibility and quota logic
- no Flutter dependency
- no advanced growth automation dependency

This scope is strong enough to look professional without forcing phase-2 complexity into launch.

## 8. Exact Go-Live Ready Definition

The project should only be called live-ready when all of the following are true:

- owner can only operate the platform, not daily store operations
- merchant can fully run one store without developer help
- customer can browse, checkout, sign in, and see account history
- wrong-host auth is blocked
- no cross-tenant data leakage exists
- merchant custom domain changes do not affect merchant admin access
- production deploy is repeatable
- queue worker and scheduler are verified
- backup and rollback are documented and tested
- all critical paths pass end-to-end testing on a production-like environment

## 9. Best Execution Order From Here

This is the cleanest order to finish the project.

1. Finalize owner vs merchant scope lock.
2. Reduce or fence off legacy admin store-operation routes.
3. Finish owner and merchant web workspace separation.
4. Finalize auth/session strategy across owner, merchant, and storefront.
5. Finish merchant core store operations and settings.
6. Finish storefront/customer account and checkout flow.
7. Finish billing, subscription, and domain operational paths.
8. Add scheduler jobs, queue operations, backup checks, and rollback procedure.
9. Run browser-level smoke tests plus production-like staging verification.
10. Soft launch with controlled merchants.

## 10. Suggested Delivery Phases

### Phase A: Launch blocker sprint

- scope cleanup
- auth hardening
- merchant core completion
- storefront checkout completion

### Phase B: Production readiness sprint

- deploy hardening
- queue/scheduler
- backups and rollback
- domain and billing operational checks

### Phase C: Launch confidence sprint

- end-to-end testing
- onboarding polish
- support workflows
- controlled soft launch

## 11. Final Recommendation

This project is close enough that a disciplined V1 launch is realistic.

Do not treat it like a greenfield build anymore.
Treat it like a platform finishing project.

The highest-value work now is:

- remove ownership ambiguity
- finish the merchant and customer critical paths
- harden production operations
- test the real launch paths end to end

If those four areas are completed properly, the product can launch with confidence.
