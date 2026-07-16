# Multi-Tenant Ecommerce SaaS Blueprint

Date: July 16, 2026
Status: Architecture baseline for implementation

## 1. Non-Negotiable Rules

1. `company.com` is the marketing and landing website.
2. `merchant.company.com` is the only merchant admin login and control panel.
3. `owner.company.com` is the only super admin and platform operations panel.
4. `shopslug.company.com` is the default public storefront for each merchant.
5. `customdomain.com` is an optional merchant storefront alias.
6. Changing DNS or custom domain must never change merchant admin access.
7. Merchant users always log in through `merchant.company.com`.
8. Storefront domain resolution applies only to public customer traffic.
9. One codebase and one deployment update every tenant at once.

## 2. URL and Surface Map

| Surface | Host | Audience | Purpose |
| --- | --- | --- | --- |
| Marketing | `company.com` | visitor | landing, pricing, features, create store |
| Merchant app | `merchant.company.com` | merchant | products, orders, settings, theme, domain |
| Owner app | `owner.company.com` | platform team | tenant ops, billing, integrations, support |
| Default storefront | `shopslug.company.com` | customer | public ecommerce site |
| Custom storefront | `customdomain.com` | customer | public ecommerce site |

Recommended route policy:

- Marketing web: `/`, `/pricing`, `/features`, `/contact`, `/create-store`
- Merchant app: `/login`, `/register`, `/dashboard`, `/products`, `/orders`, `/settings`
- Owner app: `/login`, `/dashboard`, `/tenants`, `/domains`, `/providers`, `/support`
- Storefront: `/`, `/product/:slug`, `/cart`, `/checkout`, `/account`

Recommended API namespaces:

- `/api/platform/*` for owner actions
- `/api/merchant/*` for merchant actions
- `/api/storefront/*` for customer-facing actions
- `/api/system/*` for internal callbacks, domain verification, webhooks

## 3. Core Data Model Decisions

### 3.1 Identity model

- Platform users are global identities.
- Owner and merchant users live in one shared `users` table.
- Storefront customers are tenant-scoped and live in a separate `customers` table.
- Merchant access to stores is controlled by `tenant_members`.
- A single merchant user may belong to multiple tenants in the future.

### 3.2 Tenant model

- Every store is a `tenant`.
- Every tenant has exactly one permanent platform slug used for default subdomain.
- Every tenant can have multiple domains, but only one active primary domain at a time.
- `shopslug.company.com` always remains reserved as permanent fallback.

### 3.3 Settings model

- Global platform settings stay in owner scope only.
- Merchant-configurable settings must move from global settings to tenant-scoped settings tables.
- No merchant setting should write to `.env`.
- Payment, SMS, email, push, Cloudflare, and third-party provider master credentials stay platform-owned.
- Merchant only enables or disables already-provisioned integrations for their tenant.

## 4. Exact Database Schema

## 4.1 Global identity and access tables

### `users`

Purpose: global identity for owner and merchant users.

Columns:

- `id` bigint primary key
- `uuid` char(36) unique
- `name` varchar(160)
- `email` varchar(190) nullable unique
- `phone` varchar(30) nullable
- `country_code` varchar(10) nullable
- `username` varchar(120) nullable unique
- `password` varchar(255)
- `email_verified_at` timestamp nullable
- `phone_verified_at` timestamp nullable
- `status` tinyint default 1
- `avatar_path` varchar(255) nullable
- `last_login_at` timestamp nullable
- `last_login_ip` varchar(45) nullable
- `created_at` timestamp
- `updated_at` timestamp
- `deleted_at` timestamp nullable

Indexes:

- unique `email`
- unique `username`
- index `(phone, country_code)`
- index `status`

### `platform_roles`

Purpose: global roles for owner-side and merchant-side identities.

Columns:

- `id` bigint primary key
- `code` varchar(60) unique
- `name` varchar(120)
- `scope` enum `platform`, `merchant`
- `is_system` boolean default true
- `created_at`
- `updated_at`

Seed roles:

- `platform_owner`
- `platform_ops`
- `platform_support`
- `platform_finance`
- `platform_developer`
- `merchant_owner`
- `merchant_manager`
- `merchant_catalog_manager`
- `merchant_order_manager`
- `merchant_marketing_manager`
- `merchant_staff`
- `merchant_analyst`

### `platform_permissions`

Columns:

- `id` bigint primary key
- `code` varchar(120) unique
- `name` varchar(160)
- `scope` enum `platform`, `merchant`
- `module` varchar(80)
- `created_at`
- `updated_at`

### `platform_role_permissions`

Columns:

- `role_id` bigint foreign key
- `permission_id` bigint foreign key
- unique `(role_id, permission_id)`

### `user_platform_roles`

Purpose: platform-level roles not bound to a merchant tenant.

Columns:

- `id` bigint primary key
- `user_id` bigint foreign key
- `role_id` bigint foreign key
- unique `(user_id, role_id)`

Use only for owner-side roles.

## 4.2 Tenant registry and routing tables

### `tenants`

Purpose: one merchant store per row.

Columns:

- `id` bigint primary key
- `uuid` char(36) unique
- `name` varchar(160)
- `legal_name` varchar(190) nullable
- `slug` varchar(120) unique
- `store_code` varchar(40) unique
- `status` enum `draft`, `active`, `suspended`, `archived`
- `plan_code` varchar(60) nullable
- `onboarding_status` enum `pending`, `basic_complete`, `catalog_started`, `live`
- `primary_locale` varchar(10) default `en`
- `primary_currency_code` varchar(10) default `USD`
- `timezone` varchar(60) default `UTC`
- `country_code` varchar(10) nullable
- `contact_email` varchar(190) nullable
- `contact_phone` varchar(30) nullable
- `logo_media_id` bigint nullable
- `favicon_media_id` bigint nullable
- `created_by_user_id` bigint foreign key nullable
- `approved_by_user_id` bigint foreign key nullable
- `approved_at` timestamp nullable
- `launched_at` timestamp nullable
- `suspended_at` timestamp nullable
- `created_at`
- `updated_at`
- `deleted_at` nullable

Indexes:

- unique `slug`
- unique `store_code`
- index `status`
- index `created_by_user_id`

### `tenant_domains`

Purpose: domain and subdomain routing registry.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `hostname` varchar(255) unique
- `domain_type` enum `subdomain`, `custom`
- `is_primary` boolean default false
- `is_fallback` boolean default false
- `ssl_status` enum `pending`, `active`, `failed`
- `verification_status` enum `pending`, `verified`, `failed`
- `dns_provider` varchar(50) nullable
- `cloudflare_zone_id` varchar(80) nullable
- `cloudflare_hostname_id` varchar(120) nullable
- `verification_token` varchar(120) nullable
- `verified_at` timestamp nullable
- `last_checked_at` timestamp nullable
- `created_at`
- `updated_at`

Constraints:

- one permanent row per tenant where `domain_type=subdomain` and `is_fallback=true`
- only one active `is_primary=true` per tenant

Indexes:

- unique `hostname`
- index `(tenant_id, domain_type)`
- index `(tenant_id, is_primary)`

### `tenant_members`

Purpose: merchant access membership to tenant.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `user_id` bigint foreign key
- `role_id` bigint foreign key references `platform_roles`
- `status` enum `invited`, `active`, `suspended`
- `invited_by_user_id` bigint foreign key nullable
- `joined_at` timestamp nullable
- `last_seen_at` timestamp nullable
- `created_at`
- `updated_at`

Constraints:

- unique `(tenant_id, user_id)`
- role must be merchant-scoped

### `tenant_invites`

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `email` varchar(190)
- `role_id` bigint foreign key
- `invite_token` varchar(120) unique
- `status` enum `pending`, `accepted`, `expired`, `revoked`
- `expires_at` timestamp
- `created_by_user_id` bigint foreign key
- `accepted_by_user_id` bigint nullable
- `accepted_at` timestamp nullable
- `created_at`
- `updated_at`

## 4.3 Tenant configuration tables

### `tenant_settings`

Purpose: generic key-value settings for merchant-configurable store values.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `group_key` varchar(80)
- `setting_key` varchar(120)
- `setting_value` longtext nullable
- `value_type` enum `string`, `integer`, `decimal`, `boolean`, `json`, `text`
- `is_encrypted` boolean default false
- `updated_by_user_id` bigint nullable
- `created_at`
- `updated_at`

Constraint:

- unique `(tenant_id, group_key, setting_key)`

Groups:

- `store`
- `checkout`
- `shipping`
- `tax`
- `theme`
- `seo`
- `contact`
- `social`
- `notification`
- `analytics`

### `tenant_feature_flags`

Purpose: enable or disable optional modules for each tenant.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `feature_code` varchar(80)
- `status` boolean default false
- `source` enum `platform_default`, `owner_override`, `merchant_choice`
- `updated_by_user_id` bigint nullable
- `created_at`
- `updated_at`

Constraint:

- unique `(tenant_id, feature_code)`

Initial features:

- `catalog`
- `orders`
- `coupons`
- `promotions`
- `reviews`
- `subscribers`
- `custom_domain`
- `analytics`
- `pages`
- `theme_editor`
- `cash_on_delivery`
- `online_payment`
- `return_requests`
- `inventory_advanced`
- `suppliers`
- `purchases`
- `damages`
- `outlets`
- `pos`

### `tenant_payment_methods`

Purpose: tenant-level payment method toggles built on platform providers.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `provider_code` varchar(60)
- `display_name` varchar(120)
- `status` boolean default false
- `checkout_label` varchar(120) nullable
- `fee_type` enum `none`, `fixed`, `percent`
- `fee_value` decimal(12,4) nullable
- `sort_order` int default 0
- `config_json` json nullable
- `created_at`
- `updated_at`

Constraint:

- unique `(tenant_id, provider_code)`

### `tenant_notification_channels`

Purpose: tenant-level notification toggles on top of platform providers.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `channel_code` varchar(60)
- `status` boolean default false
- `config_json` json nullable
- `created_at`
- `updated_at`

Values:

- `email_order`
- `sms_order`
- `push_order`
- `email_marketing`

### `tenant_theme_versions`

Purpose: link tenant to a platform-shipped theme preset version.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `theme_code` varchar(80)
- `theme_version` varchar(30)
- `status` enum `draft`, `published`
- `assigned_by_user_id` bigint nullable
- `published_at` timestamp nullable
- `created_at`
- `updated_at`

### `tenant_navigation_menus`

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `name` varchar(120)
- `location_code` varchar(60)
- `status` boolean default true
- `created_at`
- `updated_at`

### `tenant_navigation_items`

Columns:

- `id` bigint primary key
- `menu_id` bigint foreign key
- `parent_id` bigint nullable
- `label` varchar(120)
- `target_type` enum `page`, `collection`, `product`, `url`
- `target_id` bigint nullable
- `target_url` varchar(255) nullable
- `sort_order` int default 0
- `created_at`
- `updated_at`

## 4.4 Storefront customer tables

### `customers`

Purpose: customer account belongs to one tenant only.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `uuid` char(36) unique
- `name` varchar(160)
- `email` varchar(190) nullable
- `phone` varchar(30) nullable
- `country_code` varchar(10) nullable
- `password` varchar(255) nullable
- `status` tinyint default 1
- `is_guest` boolean default false
- `email_verified_at` timestamp nullable
- `phone_verified_at` timestamp nullable
- `last_login_at` timestamp nullable
- `created_at`
- `updated_at`
- `deleted_at` nullable

Constraints:

- unique `(tenant_id, email)`
- index `(tenant_id, phone, country_code)`

### `customer_addresses`

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `customer_id` bigint foreign key
- `label` varchar(60) nullable
- `recipient_name` varchar(160)
- `recipient_phone` varchar(30)
- `country_id` bigint nullable
- `state_id` bigint nullable
- `city_id` bigint nullable
- `address_line_1` varchar(255)
- `address_line_2` varchar(255) nullable
- `postal_code` varchar(30) nullable
- `is_default_shipping` boolean default false
- `is_default_billing` boolean default false
- `created_at`
- `updated_at`

### `customer_wishlists`

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `customer_id` bigint foreign key
- `product_id` bigint foreign key
- `created_at`

Constraint:

- unique `(tenant_id, customer_id, product_id)`

## 4.5 Commerce catalog tables

The current single-store commerce tables should become tenant-scoped.

### Existing tables that must add `tenant_id`

- `product_categories`
- `product_brands`
- `products`
- `product_attributes`
- `product_attribute_options`
- `product_variations`
- `product_tags`
- `product_taxes`
- `product_sections`
- `product_section_products`
- `product_seos`
- `product_seo_meta_tags`
- `pages`
- `sliders`
- `benefits`
- `coupons`
- `promotions`
- `promotion_products`
- `subscribers`
- `outlets`
- `taxes`
- `units`
- `order_areas`
- `return_reasons`
- `product_reviews`

### Required uniqueness refactors

- `products.slug`: change from global unique to unique `(tenant_id, slug)`
- `products.sku`: unique `(tenant_id, sku)`
- `product_categories.slug`: unique `(tenant_id, slug)`
- `pages.slug`: unique `(tenant_id, slug)`
- `promotions.slug`: unique `(tenant_id, slug)`
- `product_sections.slug`: unique `(tenant_id, slug)`

### `products`

Keep current fields, but add:

- `tenant_id` bigint foreign key
- `created_by_user_id` bigint nullable
- `updated_by_user_id` bigint nullable
- `published_at` timestamp nullable
- `visibility` enum `draft`, `active`, `hidden`, `archived`

### `product_categories`

Add:

- `tenant_id` bigint foreign key
- unique `(tenant_id, slug)`
- unique `(tenant_id, name, parent_id)`

### `pages`

Add:

- `tenant_id` bigint foreign key
- `page_type` enum `system`, `custom`
- `is_homepage` boolean default false
- unique `(tenant_id, slug)`

### `sliders`

Add:

- `tenant_id` bigint foreign key
- `placement` varchar(60) nullable
- `starts_at` timestamp nullable
- `ends_at` timestamp nullable

## 4.6 Orders, checkout, and payment tables

### `orders`

Refactor current table.

Add or change:

- `tenant_id` bigint foreign key
- `customer_id` bigint foreign key nullable
- `domain_id` bigint foreign key nullable
- `order_channel` enum `web`, `pos`, `manual`
- `currency_code` varchar(10)
- `currency_symbol` varchar(10)
- `exchange_rate` decimal(18,8) default 1
- `subtotal`, `discount`, `tax`, `shipping_charge`, `total` remain
- `payment_method_code` varchar(60)
- `payment_status` enum `unpaid`, `paid`, `failed`, `refunded`, `partial`
- `fulfillment_status` enum `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled`, `returned`, `rejected`
- `customer_name_snapshot` varchar(160)
- `customer_email_snapshot` varchar(190) nullable
- `customer_phone_snapshot` varchar(30) nullable
- `note` text nullable
- `placed_at` timestamp

Indexes:

- unique `(tenant_id, order_serial_no)`
- index `(tenant_id, payment_status)`
- index `(tenant_id, fulfillment_status)`
- index `(tenant_id, placed_at)`

### `order_addresses`

Add:

- `tenant_id` bigint foreign key
- `order_id` bigint foreign key
- `address_type` enum `shipping`, `billing`
- `recipient_name`
- `recipient_phone`
- `country_name`
- `state_name`
- `city_name`
- `address_line_1`
- `address_line_2`
- `postal_code`

### `order_items`

If current project stores items elsewhere, normalize them into this table.

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `order_id` bigint foreign key
- `product_id` bigint foreign key nullable
- `product_variation_id` bigint nullable
- `sku_snapshot` varchar(120)
- `name_snapshot` varchar(255)
- `unit_price` decimal(19,6)
- `qty` int
- `discount_total` decimal(19,6) default 0
- `tax_total` decimal(19,6) default 0
- `line_total` decimal(19,6)
- `created_at`
- `updated_at`

### `payment_transactions`

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `order_id` bigint foreign key
- `provider_code` varchar(60)
- `gateway_reference` varchar(120) nullable
- `transaction_reference` varchar(120) nullable
- `status` enum `initiated`, `pending`, `success`, `failed`, `cancelled`, `refunded`
- `amount` decimal(19,6)
- `currency_code` varchar(10)
- `payload_json` json nullable
- `processed_at` timestamp nullable
- `created_at`
- `updated_at`

Constraint:

- index `(tenant_id, provider_code, status)`

### `tenant_carts`

Columns:

- `id` bigint primary key
- `tenant_id` bigint foreign key
- `customer_id` bigint nullable
- `session_token` varchar(120) nullable
- `currency_code` varchar(10)
- `created_at`
- `updated_at`

### `tenant_cart_items`

Columns:

- `id` bigint primary key
- `cart_id` bigint foreign key
- `product_id` bigint foreign key
- `product_variation_id` bigint nullable
- `qty` int
- `unit_price_snapshot` decimal(19,6)
- `created_at`
- `updated_at`

## 4.7 Inventory and operations tables

These modules should exist, but several should be disabled by default for simple merchants.

### Optional tenant-scoped tables

Add `tenant_id` to:

- `suppliers`
- `purchases`
- `purchase_payments`
- `stocks`
- `damages`
- `outlets`
- `return_orders`
- `return_and_refunds`
- `return_and_refund_products`

Module default policy:

- Enabled by default: `products`, `orders`, `coupons`, `promotions`, `reviews`, `pages`, `theme`, `domain`
- Disabled by default: `suppliers`, `purchases`, `damages`, `outlets`, `pos`, `advanced_inventory`

## 4.8 Platform-owned governance tables

### `platform_providers`

Purpose: owner-managed external services registry.

Columns:

- `id` bigint primary key
- `provider_type` enum `payment`, `sms`, `mail`, `push`, `analytics`, `domain`
- `provider_code` varchar(60) unique
- `name` varchar(120)
- `status` boolean default true
- `config_json` json
- `created_at`
- `updated_at`

### `platform_theme_presets`

Columns:

- `id` bigint primary key
- `theme_code` varchar(80)
- `version` varchar(30)
- `name` varchar(120)
- `status` enum `draft`, `published`, `retired`
- `schema_json` json
- `assets_manifest_json` json nullable
- `created_at`
- `updated_at`

Constraint:

- unique `(theme_code, version)`

### `platform_audit_logs`

Columns:

- `id` bigint primary key
- `actor_user_id` bigint nullable
- `actor_scope` enum `platform`, `merchant`, `system`
- `tenant_id` bigint nullable
- `action_code` varchar(120)
- `entity_type` varchar(120)
- `entity_id` bigint nullable
- `old_values_json` json nullable
- `new_values_json` json nullable
- `ip_address` varchar(45) nullable
- `user_agent` varchar(255) nullable
- `created_at`

### `domain_verification_logs`

Columns:

- `id` bigint primary key
- `tenant_domain_id` bigint foreign key
- `check_status` enum `success`, `failed`
- `check_type` enum `dns`, `ssl`, `hostname`
- `message` text nullable
- `payload_json` json nullable
- `checked_at` timestamp

## 5. Existing Project Refactor Map

## 5.1 Move from global to platform-only

Current global settings/services that should remain owner-only:

- company settings
- platform site defaults
- payment provider master configs
- SMS provider master configs
- notification provider master configs
- language master pack management
- theme preset publishing
- analytics script templates
- license and extension management

## 5.2 Move from global to tenant-scoped

Current global settings/services that must become tenant data:

- store logo and favicon
- merchant store name and public info
- theme values
- social links
- shipping setup
- tax setup
- store pages
- slider content
- enabled payment methods
- enabled notifications
- default order area rules

## 5.3 Tables that need immediate `tenant_id`

Priority 1:

- `products`
- `product_categories`
- `product_brands`
- `product_variations`
- `orders`
- `order_addresses`
- `coupons`
- `promotions`
- `pages`
- `sliders`
- `product_reviews`
- `subscribers`

Priority 2:

- `suppliers`
- `purchases`
- `stocks`
- `damages`
- `outlets`
- `return_orders`
- `return_and_refunds`

Priority 3:

- analytics and reporting snapshots
- notification alert templates
- tenant menu templates

## 6. Module Split

## 6.1 Marketing app: `company.com`

Modules:

- landing pages
- feature pages
- pricing
- partner or agency pages
- help center
- create store CTA
- SEO pages

Must not include:

- merchant dashboard
- tenant-specific runtime
- owner tools

## 6.2 Merchant app: `merchant.company.com`

Core modules:

- merchant registration and login
- onboarding wizard
- dashboard
- catalog
- orders
- customers
- coupons
- promotions
- pages
- theme settings
- domain manager
- payment toggles
- notification toggles
- staff management
- analytics

Advanced modules behind feature flags:

- suppliers
- purchases
- damages
- stock reports
- outlets
- POS
- return workflow policy

## 6.3 Owner app: `owner.company.com`

Core modules:

- platform dashboard
- tenant list and tenant details
- tenant create, suspend, archive
- merchant approval
- domain verification and override
- provider configuration
- theme preset publishing
- feature preset management
- support impersonation
- audit logs
- billing and finance
- platform reports
- extension management

Must control:

- what merchants are allowed to see
- what providers merchants can enable
- what default template and modules a new tenant receives

## 6.4 Storefront app: `shopslug.company.com` or custom domain

Core modules:

- home
- product list
- product detail
- search
- cart
- checkout
- account
- orders
- review
- wishlist
- static pages
- newsletter

Runtime rule:

- host lookup resolves tenant from `tenant_domains`
- admin cookies and merchant sessions are never shared here

## 6.5 Background services

Services:

- queue workers
- payment webhook handlers
- DNS verification worker
- SSL status checker
- email and SMS dispatchers
- search indexing
- analytics aggregation
- media optimization

## 7. Owner vs Merchant Permission Matrix

Legend:

- `Full` = full access
- `Own` = own tenant only
- `Limited` = limited by role or feature flag
- `No` = no access

| Capability | Owner Panel | Merchant Panel |
| --- | --- | --- |
| Create tenant | Full | No |
| Approve merchant registration | Full | No |
| Suspend or archive tenant | Full | No |
| View all tenants | Full | No |
| View own tenant | Full | Own |
| Change tenant plan | Full | No |
| Assign default theme preset | Full | No |
| Publish platform theme version | Full | No |
| Choose active theme inside tenant | Full | Own |
| Manage platform providers | Full | No |
| Enable payment method for tenant | Full | Own |
| Enable SMS or email channel for tenant | Full | Own |
| Force-disable risky provider | Full | No |
| Verify custom domain | Full | Own request only |
| Override primary domain | Full | Own |
| Reset tenant storefront domain | Full | No |
| View domain verification logs | Full | Own |
| Create merchant staff | Full | Own |
| Assign merchant roles | Full | Own |
| Manage owner users | Full | No |
| Impersonate merchant for support | Full | No |
| View platform audit logs | Full | No |
| View tenant audit logs | Full | Own |
| Create products | No | Own |
| Manage categories and brands | No | Own |
| Manage pages and sliders | No | Own |
| Manage coupons and promotions | No | Own |
| Manage orders | Limited support | Own |
| Refund or cancel orders | Limited support | Own |
| Export tenant reports | Full | Own |
| Access global finance dashboards | Full | No |
| Access tenant sales analytics | Full | Own |
| Edit `.env` or server config | Full | No |
| Manage Cloudflare credentials | Full | No |
| Manage extension marketplace | Full | No |

## 8. Recommended Merchant Role Matrix

| Capability | Merchant Owner | Manager | Catalog Manager | Order Manager | Staff | Analyst |
| --- | --- | --- | --- | --- | --- | --- |
| Store settings | Full | Limited | No | No | No | No |
| Staff management | Full | Limited | No | No | No | No |
| Domain settings | Full | Limited | No | No | No | No |
| Products | Full | Full | Full | No | Limited | Read |
| Categories and brands | Full | Full | Full | No | Limited | Read |
| Orders | Full | Full | No | Full | Limited | Read |
| Customers | Full | Full | No | Full | Limited | Read |
| Coupons and promotions | Full | Full | Limited | Limited | No | Read |
| Pages and theme | Full | Limited | Limited | No | No | Read |
| Reports | Full | Full | Limited | Limited | No | Read |
| Inventory advanced modules | Full | Limited | Limited | Limited | No | Read |

## 9. Default Product Policy for MVP

New merchant should receive:

- one active starter theme
- default storefront subdomain
- core catalog module enabled
- orders enabled
- coupons enabled
- promotions enabled
- pages enabled
- reviews enabled
- custom domain module enabled

New merchant should not see on day one unless owner enables:

- suppliers
- purchases
- damages
- outlets
- POS
- advanced stock reconciliation

## 10. Implementation Order

1. Create `tenants`, `tenant_domains`, `tenant_members`, `customers`, and `tenant_settings`.
2. Backfill current single-store data into one default tenant.
3. Add `tenant_id` to catalog and order tables.
4. Replace global storefront settings reads with tenant-aware resolver.
5. Split auth and APIs into `platform`, `merchant`, and `storefront`.
6. Add domain resolution middleware for storefront hosts only.
7. Add owner domain management and Cloudflare integration.
8. Add feature flags and module visibility rules.
9. Add audit logs and support impersonation.
10. Remove remaining merchant-facing writes to global settings and `.env`.

## 11. Final Recommendation

Do not turn the current single admin panel directly into SaaS by only adding subdomains.

The correct implementation path is:

- keep one shared codebase
- create a real `tenant` layer
- move merchant settings into tenant-scoped storage
- keep merchant login centralized at `merchant.company.com`
- use domains only for storefront traffic
- let owner panel control all provider and platform complexity

This gives the cleanest merchant UX, the safest domain model, and the strongest path to scale.
