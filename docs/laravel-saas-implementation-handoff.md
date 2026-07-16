# Laravel SaaS Implementation Handoff

Date: July 16, 2026
Status: Implementation planning document
Companion docs:

- `docs/multitenant-saas-blueprint.md`
- `docs/saas-product-roadmap.md`

## 1. Purpose

This document translates the product roadmap and multi-tenant blueprint into Laravel implementation steps.

It covers:

- migration plan
- route and controller split
- middleware flow
- authentication flow
- rollout and cutover sequence

The goal is to move the current single-store Laravel app into a true multi-tenant SaaS without breaking live data.

## 2. Current Project Reality

Today the project behaves like this:

- one shared `routes/api.php` handles admin and frontend APIs together
- one shared `routes/web.php` serves install, payment, and SPA catch-all
- one `users` table mixes admin/staff/customer identities
- current auth uses Sanctum bearer tokens
- `adminAccess` separates admin routes by role
- many business settings are global rather than tenant-scoped

That means the safest path is a staged migration, not a full rewrite in one release.

## 3. Target Laravel Structure

## 3.1 Controller namespaces

Create these namespaces:

- `App\Http\Controllers\Platform\*`
- `App\Http\Controllers\Merchant\*`
- `App\Http\Controllers\Storefront\*`
- `App\Http\Controllers\System\*`

Keep these existing namespaces only during transition:

- `App\Http\Controllers\Admin\*`
- `App\Http\Controllers\Frontend\*`
- `App\Http\Controllers\Auth\*`

End-state rule:

- `Admin` becomes `Platform` plus `Merchant`
- `Frontend` becomes `Storefront`
- shared auth controllers are replaced by surface-specific auth controllers

## 3.2 Suggested route file structure

Keep `routes/api.php` and `routes/web.php` as aggregators only.

Create:

- `routes/api/platform-auth.php`
- `routes/api/platform.php`
- `routes/api/merchant-auth.php`
- `routes/api/merchant.php`
- `routes/api/storefront-auth.php`
- `routes/api/storefront.php`
- `routes/api/system.php`

Create:

- `routes/web/install.php`
- `routes/web/marketing.php`
- `routes/web/platform.php`
- `routes/web/merchant.php`
- `routes/web/storefront.php`
- `routes/web/payment.php`

## 3.3 Suggested service namespaces

Create:

- `App\Services\Platform\*`
- `App\Services\Merchant\*`
- `App\Services\Storefront\*`
- `App\Services\Tenancy\*`
- `App\Services\Domain\*`
- `App\Services\Auth\*`

## 3.4 Suggested config files

Create:

- `config/saas.php`
- `config/tenancy.php`
- `config/platform.php`

Suggested keys:

```php
return [
    'root_domain' => env('SAAS_ROOT_DOMAIN', 'company.com'),
    'marketing_host' => env('SAAS_MARKETING_HOST', 'company.com'),
    'owner_host' => env('SAAS_OWNER_HOST', 'owner.company.com'),
    'merchant_host' => env('SAAS_MERCHANT_HOST', 'merchant.company.com'),
    'fallback_subdomain_suffix' => env('SAAS_STOREFRONT_SUFFIX', 'company.com'),
    'require_owner_2fa' => env('SAAS_REQUIRE_OWNER_2FA', true),
];
```

## 4. Migration Strategy

## 4.1 Migration principles

- never break current production data in one deploy
- add first, backfill second, enforce third, remove old code last
- create one bootstrap tenant from the current single-store database
- preserve current routes until new flows are verified

## 4.2 Migration waves

### Wave 0: Pre-migration safeguards

Before schema changes:

- full database backup
- snapshot media storage
- export current admin users
- export current customers
- export current products and orders
- freeze direct writes to critical global settings during cutover window

Create commands:

- `php artisan saas:preflight-check`
- `php artisan saas:backup-audit`

### Wave 1: Core SaaS tables

Create new tables first.

Suggested migration files:

- `2026_07_16_000001_create_platform_roles_table.php`
- `2026_07_16_000002_create_platform_permissions_table.php`
- `2026_07_16_000003_create_platform_role_permissions_table.php`
- `2026_07_16_000004_create_user_platform_roles_table.php`
- `2026_07_16_000005_create_tenants_table.php`
- `2026_07_16_000006_create_tenant_domains_table.php`
- `2026_07_16_000007_create_tenant_members_table.php`
- `2026_07_16_000008_create_tenant_invites_table.php`
- `2026_07_16_000009_create_customers_table.php`
- `2026_07_16_000010_create_customer_addresses_table.php`
- `2026_07_16_000011_create_tenant_settings_table.php`
- `2026_07_16_000012_create_tenant_feature_flags_table.php`
- `2026_07_16_000013_create_tenant_payment_methods_table.php`
- `2026_07_16_000014_create_tenant_notification_channels_table.php`
- `2026_07_16_000015_create_tenant_theme_versions_table.php`
- `2026_07_16_000016_create_tenant_navigation_menus_table.php`
- `2026_07_16_000017_create_tenant_navigation_items_table.php`
- `2026_07_16_000018_create_platform_providers_table.php`
- `2026_07_16_000019_create_platform_theme_presets_table.php`
- `2026_07_16_000020_create_platform_audit_logs_table.php`
- `2026_07_16_000021_create_domain_verification_logs_table.php`

### Wave 2: Add `tenant_id` to existing business tables

Add nullable `tenant_id` first.

Suggested migration files:

- `2026_07_16_000101_add_tenant_id_to_products_table.php`
- `2026_07_16_000102_add_tenant_id_to_product_categories_table.php`
- `2026_07_16_000103_add_tenant_id_to_product_brands_table.php`
- `2026_07_16_000104_add_tenant_id_to_product_variations_table.php`
- `2026_07_16_000105_add_tenant_id_to_pages_table.php`
- `2026_07_16_000106_add_tenant_id_to_sliders_table.php`
- `2026_07_16_000107_add_tenant_id_to_coupons_table.php`
- `2026_07_16_000108_add_tenant_id_to_promotions_table.php`
- `2026_07_16_000109_add_tenant_id_to_product_sections_table.php`
- `2026_07_16_000110_add_tenant_id_to_orders_table.php`
- `2026_07_16_000111_add_tenant_id_to_order_addresses_table.php`
- `2026_07_16_000112_add_tenant_id_to_product_reviews_table.php`
- `2026_07_16_000113_add_tenant_id_to_subscribers_table.php`
- `2026_07_16_000114_add_tenant_id_to_order_areas_table.php`
- `2026_07_16_000115_add_tenant_id_to_return_reasons_table.php`
- `2026_07_16_000116_add_tenant_id_to_outlets_table.php`
- `2026_07_16_000117_add_tenant_id_to_suppliers_table.php`
- `2026_07_16_000118_add_tenant_id_to_purchases_table.php`
- `2026_07_16_000119_add_tenant_id_to_stocks_table.php`
- `2026_07_16_000120_add_tenant_id_to_damages_table.php`
- `2026_07_16_000121_add_tenant_id_to_return_orders_table.php`
- `2026_07_16_000122_add_tenant_id_to_return_and_refunds_table.php`

### Wave 3: Bootstrap tenant and data backfill

Create one migration-safe command sequence:

- `php artisan saas:create-bootstrap-tenant`
- `php artisan saas:backfill-single-store-data`
- `php artisan saas:backfill-customers`
- `php artisan saas:backfill-roles`
- `php artisan saas:verify-backfill`

Bootstrap rules:

- create tenant `id=1` from current store settings
- reserve current storefront as tenant fallback subdomain
- assign existing store settings into `tenant_settings`
- map existing `ADMIN` user to `platform_owner`
- map existing store managers and staff into `tenant_members`
- copy current customer users into `customers`
- do not delete old customer user rows yet

### Wave 4: Constraint and uniqueness refactor

After successful backfill:

- make `tenant_id` non-null on tenant-owned tables
- replace global unique indexes with tenant-aware unique indexes

Suggested refactor migrations:

- `2026_07_16_000201_refactor_product_slug_unique_to_tenant_scope.php`
- `2026_07_16_000202_refactor_product_sku_unique_to_tenant_scope.php`
- `2026_07_16_000203_refactor_category_slug_unique_to_tenant_scope.php`
- `2026_07_16_000204_refactor_page_slug_unique_to_tenant_scope.php`
- `2026_07_16_000205_refactor_promotion_slug_unique_to_tenant_scope.php`
- `2026_07_16_000206_refactor_product_section_slug_unique_to_tenant_scope.php`
- `2026_07_16_000207_make_orders_tenant_id_required.php`

### Wave 5: Customer identity separation

Transition customer runtime from `users` table to `customers` table.

Implementation steps:

1. add `HasApiTokens` to `Customer` model
2. create storefront auth controllers using `customers`
3. move address, wishlist, order-account, and review auth to customer auth
4. stop issuing customer tokens from `users`
5. only after validation, retire customer login from shared auth

### Wave 6: Settings migration

Move merchant-facing global settings into `tenant_settings`.

Migrate:

- theme settings
- site identity settings
- shipping settings
- tax settings
- social media
- notification toggles
- store pages
- homepage content configuration

Do not migrate to tenant:

- master payment gateway credentials
- master SMS gateway credentials
- master mail credentials
- owner-level analytics presets
- Cloudflare credentials

### Wave 7: Route cutover

Replace shared auth and shared route surface logic.

Implementation order:

1. introduce new route files
2. create new controllers
3. wire middleware
4. switch frontends one surface at a time
5. keep old routes in compatibility mode for a short window

### Wave 8: Old-code cleanup

After cutover:

- retire shared customer login logic from `Auth\LoginController`
- retire merchant-facing writes to global settings
- remove old route groups no longer used
- archive transitional commands

## 5. Exact Table Ownership Rules

## 5.1 Global tables

These are platform-owned:

- `users`
- `platform_roles`
- `platform_permissions`
- `platform_role_permissions`
- `user_platform_roles`
- `platform_providers`
- `platform_theme_presets`
- `platform_audit_logs`

## 5.2 Tenant tables

These are tenant-scoped:

- `tenants`
- `tenant_domains`
- `tenant_members`
- `tenant_invites`
- `tenant_settings`
- `tenant_feature_flags`
- `tenant_payment_methods`
- `tenant_notification_channels`
- `tenant_theme_versions`
- `tenant_navigation_menus`
- `tenant_navigation_items`

## 5.3 Customer tables

These are storefront-customer-scoped:

- `customers`
- `customer_addresses`
- `customer_wishlists`

## 5.4 Existing business tables that become tenant-scoped

- products and all product support tables
- pages and content tables
- coupons and promotions
- orders and order support tables
- subscribers
- reviews
- inventory support tables

## 6. API Route Map

## 6.1 End-state API surface

There should be 4 API lanes:

- `platform`
- `merchant`
- `storefront`
- `system`

## 6.2 Aggregator pattern for `routes/api.php`

Recommended `routes/api.php`:

```php
require __DIR__.'/api/platform-auth.php';
require __DIR__.'/api/platform.php';
require __DIR__.'/api/merchant-auth.php';
require __DIR__.'/api/merchant.php';
require __DIR__.'/api/storefront-auth.php';
require __DIR__.'/api/storefront.php';
require __DIR__.'/api/system.php';
```

## 6.3 Platform auth routes

File: `routes/api/platform-auth.php`

Prefix:

- `/api/platform/auth/*`

Endpoints:

- `POST /login`
- `POST /logout`
- `POST /forgot-password`
- `POST /reset-password`
- `POST /2fa/challenge`
- `POST /2fa/verify`
- `GET /me`

Controllers:

- `Platform\Auth\LoginController`
- `Platform\Auth\PasswordController`
- `Platform\Auth\TwoFactorController`
- `Platform\Auth\ProfileController`

## 6.4 Platform routes

File: `routes/api/platform.php`

Prefix:

- `/api/platform/*`

Main groups:

- `/dashboard`
- `/tenants`
- `/tenant-memberships`
- `/domains`
- `/providers`
- `/plans`
- `/features`
- `/theme-presets`
- `/support`
- `/reports`
- `/audit-logs`
- `/extensions`

Examples:

- `GET /api/platform/dashboard/overview`
- `GET /api/platform/tenants`
- `POST /api/platform/tenants`
- `POST /api/platform/tenants/{tenant}/suspend`
- `POST /api/platform/tenants/{tenant}/impersonate`
- `GET /api/platform/domains`
- `POST /api/platform/domains/{domain}/verify`
- `PUT /api/platform/providers/payment/{provider}`

## 6.5 Merchant auth routes

File: `routes/api/merchant-auth.php`

Prefix:

- `/api/merchant/auth/*`

Endpoints:

- `POST /register`
- `POST /login`
- `POST /logout`
- `POST /forgot-password`
- `POST /reset-password`
- `POST /otp/send`
- `POST /otp/verify`
- `GET /me`

Controllers:

- `Merchant\Auth\RegisterController`
- `Merchant\Auth\LoginController`
- `Merchant\Auth\PasswordController`
- `Merchant\Auth\OtpController`
- `Merchant\Auth\ProfileController`

## 6.6 Merchant routes

File: `routes/api/merchant.php`

Prefix:

- `/api/merchant/*`

Main groups:

- `/dashboard`
- `/onboarding`
- `/store`
- `/team`
- `/catalog/products`
- `/catalog/categories`
- `/catalog/brands`
- `/orders`
- `/customers`
- `/content/pages`
- `/content/sliders`
- `/content/menus`
- `/marketing/coupons`
- `/marketing/promotions`
- `/marketing/subscribers`
- `/domain`
- `/reports`

Examples:

- `GET /api/merchant/dashboard/overview`
- `GET /api/merchant/onboarding/checklist`
- `PUT /api/merchant/store/profile`
- `PUT /api/merchant/store/theme`
- `GET /api/merchant/catalog/products`
- `POST /api/merchant/catalog/products`
- `POST /api/merchant/orders/{order}/status`
- `GET /api/merchant/domain`
- `POST /api/merchant/domain/custom`
- `POST /api/merchant/team/invite`

Advanced feature groups behind flag middleware:

- `/inventory/suppliers`
- `/inventory/purchases`
- `/inventory/stocks`
- `/inventory/damages`
- `/operations/outlets`
- `/operations/pos`

## 6.7 Storefront auth routes

File: `routes/api/storefront-auth.php`

Prefix:

- `/api/storefront/auth/*`

Endpoints:

- `POST /register`
- `POST /login`
- `POST /logout`
- `POST /forgot-password`
- `POST /reset-password`
- `POST /otp/send`
- `POST /otp/verify`
- `GET /me`

Controllers:

- `Storefront\Auth\RegisterController`
- `Storefront\Auth\LoginController`
- `Storefront\Auth\PasswordController`
- `Storefront\Auth\OtpController`
- `Storefront\Auth\ProfileController`

## 6.8 Storefront routes

File: `routes/api/storefront.php`

Prefix:

- `/api/storefront/*`

Public groups:

- `/settings/public`
- `/home`
- `/collections`
- `/products`
- `/pages`
- `/search`
- `/cart`
- `/checkout`
- `/payment-methods`

Authenticated customer groups:

- `/account`
- `/orders`
- `/addresses`
- `/wishlist`
- `/reviews`
- `/returns`

Examples:

- `GET /api/storefront/settings/public`
- `GET /api/storefront/products`
- `GET /api/storefront/products/{slug}`
- `POST /api/storefront/cart/items`
- `POST /api/storefront/checkout`
- `GET /api/storefront/orders`
- `POST /api/storefront/wishlist/toggle`
- `POST /api/storefront/reviews`

## 6.9 System routes

File: `routes/api/system.php`

Prefix:

- `/api/system/*`

Main groups:

- `/payments/webhooks/*`
- `/domains/*`
- `/internal/*`

Examples:

- `POST /api/system/payments/webhooks/sslcommerz`
- `POST /api/system/payments/webhooks/bkash`
- `POST /api/system/domains/cloudflare/callback`
- `POST /api/system/internal/rebuild-tenant-cache`

## 7. Web Route Map

## 7.1 Aggregator pattern for `routes/web.php`

Recommended `routes/web.php`:

```php
require __DIR__.'/web/install.php';
require __DIR__.'/web/marketing.php';
require __DIR__.'/web/platform.php';
require __DIR__.'/web/merchant.php';
require __DIR__.'/web/storefront.php';
require __DIR__.'/web/payment.php';
```

## 7.2 Marketing web routes

File: `routes/web/marketing.php`

Domain:

- `company.com`

Routes:

- `/`
- `/pricing`
- `/features`
- `/templates`
- `/contact`
- `/create-store`

Catch-all:

- only for marketing SPA if needed

## 7.3 Platform web routes

File: `routes/web/platform.php`

Domain:

- `owner.company.com`

Routes:

- `/login`
- `/reset-password`
- `/2fa`
- `/`
- `/{any}`

Controller:

- `Platform\RootController`

Purpose:

- serve owner SPA shell only

## 7.4 Merchant web routes

File: `routes/web/merchant.php`

Domain:

- `merchant.company.com`

Routes:

- `/login`
- `/register`
- `/forgot-password`
- `/`
- `/{any}`

Controller:

- `Merchant\RootController`

Purpose:

- serve merchant SPA shell only

## 7.5 Storefront web routes

File: `routes/web/storefront.php`

Domains:

- wildcard `*.company.com` except owner and merchant
- custom tenant domains

Routes:

- `/`
- `/{any}`

Controller:

- `Storefront\RootController`

Rule:

- all storefront web requests must pass through tenant resolution middleware

## 7.6 Payment web routes

File: `routes/web/payment.php`

Purpose:

- preserve or adapt payment redirect and callback pages that must remain web-accessible

Rule:

- these routes must become tenant-aware by order lookup

## 8. Middleware Plan

## 8.1 Keep these existing middlewares

- `installed`
- `localization`
- `auth:sanctum`

Possibly keep temporarily:

- `apiKey`
- `adminAccess`

## 8.2 Replace or phase out

- phase out `adminAccess` in favor of surface-aware platform and merchant access middleware
- phase out customer use of shared auth context
- phase out merchant-facing reliance on global settings

## 8.3 New middleware classes to add

Create:

- `IdentifyRequestSurface`
- `EnsurePlatformHost`
- `EnsureMerchantHost`
- `ResolveTenantFromHost`
- `EnsureTenantResolved`
- `EnsureTenantActive`
- `SetTenantContext`
- `SetPlatformContext`
- `SetMerchantContext`
- `EnsurePlatformUser`
- `EnsureOwnerRole`
- `EnsureMerchantUser`
- `EnsureMerchantMembership`
- `EnsureStorefrontCustomer`
- `EnsureTokenSurfaceMatchesRequest`
- `EnsureFeatureEnabled`
- `ApplyContextLocalization`

## 8.4 Middleware responsibilities

### `IdentifyRequestSurface`

Determines one of:

- `marketing`
- `platform`
- `merchant`
- `storefront`
- `system`

Based on:

- hostname
- route prefix

### `ResolveTenantFromHost`

Runs only for storefront host traffic.

Responsibilities:

- look up host in `tenant_domains`
- attach `tenant` to request container
- mark current domain
- cache result

### `EnsureTenantActive`

Blocks requests when:

- tenant suspended
- domain inactive
- verification failed and not fallback

### `EnsureMerchantMembership`

Checks:

- authenticated `user`
- user has active `tenant_members` record
- membership role allows requested action

### `EnsureStorefrontCustomer`

Checks:

- authenticated model is `Customer`
- customer belongs to resolved tenant

### `EnsureFeatureEnabled`

Example checks:

- `custom_domain`
- `promotions`
- `reviews`
- `pos`

## 8.5 Middleware chains by surface

### Marketing web

```text
web
-> installed
-> IdentifyRequestSurface(marketing)
```

### Platform web

```text
web
-> installed
-> IdentifyRequestSurface(platform)
-> EnsurePlatformHost
```

### Platform API

```text
api
-> installed
-> IdentifyRequestSurface(platform)
-> auth:sanctum
-> EnsureTokenSurfaceMatchesRequest(platform)
-> EnsurePlatformUser
-> EnsureOwnerRole or permission middleware
-> ApplyContextLocalization
```

### Merchant web

```text
web
-> installed
-> IdentifyRequestSurface(merchant)
-> EnsureMerchantHost
```

### Merchant API

```text
api
-> installed
-> IdentifyRequestSurface(merchant)
-> auth:sanctum
-> EnsureTokenSurfaceMatchesRequest(merchant)
-> EnsureMerchantUser
-> EnsureMerchantMembership
-> ApplyContextLocalization
```

### Storefront web

```text
web
-> installed
-> IdentifyRequestSurface(storefront)
-> ResolveTenantFromHost
-> EnsureTenantResolved
-> EnsureTenantActive
-> SetTenantContext
-> ApplyContextLocalization
```

### Storefront API public

```text
api
-> installed
-> IdentifyRequestSurface(storefront)
-> ResolveTenantFromHost
-> EnsureTenantResolved
-> EnsureTenantActive
-> SetTenantContext
-> ApplyContextLocalization
```

### Storefront API authenticated

```text
api
-> installed
-> IdentifyRequestSurface(storefront)
-> ResolveTenantFromHost
-> EnsureTenantResolved
-> EnsureTenantActive
-> SetTenantContext
-> auth:sanctum
-> EnsureTokenSurfaceMatchesRequest(storefront)
-> EnsureStorefrontCustomer
-> ApplyContextLocalization
```

## 9. Authentication Plan

## 9.1 Auth model decision

Use Sanctum for all API authentication in phase 1.

Reason:

- current project already uses Sanctum bearer tokens
- easier transition for Vue SPA architecture
- avoids cross-domain cookie complexity early
- supports separate authenticated models for `User` and `Customer`

## 9.2 Authenticatable models

Use:

- `User` for platform and merchant identities
- `Customer` for storefront customer identities

Both should use:

- `Laravel\Sanctum\HasApiTokens`

## 9.3 Token surface rules

Issue tokens with surface-aware abilities.

Recommended abilities:

- `surface:platform`
- `surface:merchant`
- `surface:storefront`
- `tenant:{id}`
- `permission:{code}`

Examples:

Platform owner token:

- `surface:platform`
- `permission:platform.*`

Merchant token for tenant 15:

- `surface:merchant`
- `tenant:15`
- `permission:merchant.orders.write`
- `permission:merchant.products.write`

Storefront customer token for tenant 15:

- `surface:storefront`
- `tenant:15`

## 9.4 Platform auth flow

Login source:

- `owner.company.com/login`

Credential source:

- `users` table

Sequence:

1. submit email and password
2. validate `platform` role eligibility
3. if 2FA required, issue challenge
4. verify 2FA
5. create Sanctum token with `surface:platform`
6. return profile, roles, permissions, dashboard bootstrap payload

## 9.5 Merchant auth flow

Login source:

- `merchant.company.com/login`

Credential source:

- `users` table

Sequence:

1. submit email or phone and password
2. validate user exists and is active
3. fetch active `tenant_members`
4. if user has one tenant, issue token directly
5. if user has multiple tenants, let user select tenant
6. create token with `surface:merchant` and selected `tenant:{id}`
7. return tenant context, membership role, permissions, menu bootstrap data

## 9.6 Merchant registration flow

Source:

- `merchant.company.com/register`

Sequence:

1. validate email, phone, slug
2. create `users` record
3. create `tenant`
4. create fallback subdomain entry in `tenant_domains`
5. create `tenant_members` with `merchant_owner`
6. seed feature flags and starter settings
7. create onboarding checklist
8. issue merchant token with that tenant context

## 9.7 Storefront customer auth flow

Source:

- `shopslug.company.com/account/login`
- `customdomain.com/account/login`

Credential source:

- `customers` table

Sequence:

1. resolve tenant from host
2. validate customer inside resolved tenant only
3. create token with `surface:storefront` and `tenant:{id}`
4. return storefront account bootstrap payload

## 9.8 Logout rule

Logout should revoke only current token, not all device sessions by default.

Add optional endpoint later:

- `POST /logout-all`

## 9.9 Password reset rule

Password resets must be separated:

- platform password reset for `User`
- merchant password reset for `User`
- storefront password reset for `Customer`

Even if platform and merchant share `users`, they should use separate controllers and UX flows.

## 10. Data Backfill Mapping

## 10.1 Role mapping

Map current roles like this:

| Current role | New model |
| --- | --- |
| `ADMIN` | `platform_owner` |
| `MANAGER` | `merchant_manager` under bootstrap tenant |
| `POS_OPERATOR` | `merchant_staff` or `merchant_order_manager` under bootstrap tenant |
| `STUFF` | `merchant_staff` under bootstrap tenant |
| `CUSTOMER` | migrate to `customers` table under bootstrap tenant |

## 10.2 Settings mapping

Current groups to platform-only:

- `mail`
- payment provider master settings
- SMS provider master settings
- `license`
- extension control

Current groups to tenant settings:

- `company`
- `site`
- `theme`
- `shipping_setup`
- `social_media`
- most merchant-facing notification toggles

## 10.3 Customer backfill rule

Do not hard-delete customer rows from `users` until:

1. customer data copied to `customers`
2. storefront login switched to `customers`
3. order relationships updated or safely aliased
4. production validation completed

## 11. Release Sequence

## Release 1

- add SaaS core tables
- no runtime cutover yet

## Release 2

- add `tenant_id` nullable columns
- create bootstrap tenant
- backfill catalog and order data

## Release 3

- deploy tenant resolution middleware
- introduce new storefront host-aware reads
- keep old auth active

## Release 4

- deploy merchant auth split
- deploy platform auth split
- deploy new route files

## Release 5

- deploy storefront customer auth on `customers`
- migrate account APIs

## Release 6

- enforce tenant-aware unique indexes
- make `tenant_id` required
- hide old routes

## Release 7

- clean old shared auth code
- clean old global merchant settings writes

## 12. Acceptance Criteria

The migration is successful when all of these are true:

- `merchant.company.com` is the only merchant admin entry point
- `owner.company.com` is the only owner panel entry point
- storefront host is resolved from `tenant_domains`
- changing custom domain never affects merchant admin login
- customer auth is tenant-scoped
- merchant settings no longer depend on global store settings
- current single-store data lives safely inside one bootstrap tenant
- every tenant-owned table enforces `tenant_id`

## 13. Recommended Next Build Order

If implementation starts immediately, build in this order:

1. core migrations
2. bootstrap tenant commands
3. tenant resolution middleware
4. new auth controllers
5. route file split
6. merchant settings service split
7. customer model migration
8. feature flag middleware
9. owner support and audit tooling

## 14. Final Recommendation

Do not start by changing Vue pages first.

Start with:

- schema
- tenancy context
- host resolution
- auth separation

Once those are correct, route and UI refactoring becomes much safer.
