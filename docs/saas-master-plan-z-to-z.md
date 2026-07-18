# SaaS Master Plan Z to Z

Date: July 17, 2026
Status: Unified master planning document
Priority: Single source of truth for strategy, product, architecture, implementation, and UI delivery

Source documents consolidated into this master:

- `docs/saas-product-roadmap.md`
- `docs/multitenant-product-roadmap.md`
- `docs/multitenant-saas-blueprint.md`
- `docs/laravel-saas-implementation-handoff.md`
- `docs/saas-simple-ui-execution-plan.md`

## 1. Executive Summary

This product should be built as a managed multi-tenant ecommerce SaaS, not as a simple website builder.

The winning formula is:

- one central platform
- one shared codebase
- one owner control tower
- one merchant control app
- one public storefront layer
- one permanent fallback subdomain per store
- optional custom domains only for storefronts

The most important rule is fixed:

- merchant admin never changes
- storefront domain can change
- admin domain can never depend on DNS or custom domain

That means:

- `company.com` = marketing website
- `merchant.company.com` = merchant control panel
- `owner.company.com` = platform owner control panel
- `shopslug.company.com` = merchant default storefront
- `customdomain.com` = merchant optional storefront alias

## 2. Product Positioning

This should be positioned as:

"A managed commerce operating system for modern merchants."

Not:

- just another ecommerce CMS
- just another theme marketplace
- just another plugin-heavy store builder

The core promise should be:

"You focus on business. The platform handles the technical operations."

## 3. Non-Negotiable Rules

1. Every merchant gets a permanent fallback storefront subdomain.
2. Every merchant logs in only through `merchant.company.com`.
3. Every owner logs in only through `owner.company.com`.
4. Customer storefront is the only place where subdomain or custom domain matters.
5. Platform credentials remain owner-controlled only.
6. Merchant should never edit platform infrastructure settings.
7. One main deployment updates all merchants together.
8. Owner can hide or reveal advanced modules by merchant profile.
9. Customer, merchant, and owner must have separate auth flows.
10. Simplicity is the default. Complexity is opt-in.

## 4. End-State Experience

## 4.1 Visitor journey

1. Visitor lands on `company.com`.
2. Visitor sees features, templates, pricing, and sample stores.
3. Visitor clicks `Create Store`.
4. Visitor goes to `merchant.company.com/register`.
5. Merchant account and store are created.
6. Merchant is dropped into onboarding.
7. Merchant launches store on `shopslug.company.com`.
8. Merchant later connects `customdomain.com` if needed.

## 4.2 Merchant journey

1. Merchant signs in at `merchant.company.com/login`.
2. Merchant sees onboarding score and business dashboard.
3. Merchant adds products, content, pricing, and delivery settings.
4. Merchant enables platform-provided payment methods.
5. Merchant previews the store.
6. Merchant goes live.
7. Merchant later grows by enabling advanced modules if needed.

## 4.3 Customer journey

1. Customer enters `shopslug.company.com` or `customdomain.com`.
2. Customer browses products and content.
3. Customer adds items to cart.
4. Customer completes checkout.
5. Customer tracks order, reviews products, and requests returns if allowed.

## 4.4 Owner journey

1. Owner signs in at `owner.company.com/login`.
2. Owner sees platform health, growth, incidents, and approvals.
3. Owner controls providers, domains, themes, merchants, and support.
4. Owner launches updates once and every tenant receives them.

## 5. Audience Model

There are 3 core user groups.

## 5.1 Owner

Who:

- founder
- platform admin
- support manager
- finance lead
- operations team
- developer ops team

Owns:

- tenants
- domains
- providers
- security
- support
- rollout
- pricing and packaging
- feature exposure

## 5.2 Merchant

Who:

- merchant owner
- merchant manager
- merchant staff

Owns:

- products
- orders
- customers
- content
- pricing
- store operations

Does not own:

- SMTP master credentials
- SMS master credentials
- payment gateway master credentials
- domain infrastructure credentials
- platform release control

## 5.3 Customer

Who:

- buyer
- returning shopper
- guest shopper

Uses:

- storefront
- checkout
- account pages
- order tracking
- wishlist
- review and return flow

## 6. Surface and Login Plan

## 6.1 Marketing site

Host:

- `company.com`

Pages:

- `/`
- `/pricing`
- `/features`
- `/templates`
- `/solutions`
- `/faq`
- `/contact`
- `/create-store`

No login required.

## 6.2 Owner login

Host:

- `owner.company.com/login`

Characteristics:

- invite-only
- no public signup
- strong password policy
- mandatory 2FA
- audit logs
- suspicious login monitoring

## 6.3 Merchant login

Host:

- `merchant.company.com/login`

Characteristics:

- public signup allowed
- email or phone login
- password reset
- future OTP login option
- merchant app session only

## 6.4 Merchant registration

Host:

- `merchant.company.com/register`

Fields:

- merchant full name
- store name
- email
- phone
- password
- preferred slug
- country
- business category

## 6.5 Customer login

Host:

- `shopslug.company.com/account/login`
- `customdomain.com/account/login`

Characteristics:

- tenant-scoped
- customer-only
- account belongs to one store
- separate from merchant and owner identity

## 7. Full Ownership Split

## 7.1 Owner side gets

- full tenant control
- merchant approval
- provider control
- domain control
- plan control
- feature flag control
- theme preset publishing
- release management
- audit logs
- support impersonation
- finance and platform reporting

## 7.2 Merchant side gets

- own store operations
- own staff management
- own catalog
- own orders
- own content
- own store-level settings
- own domain request flow
- own reporting

## 7.3 Customer side gets

- storefront browsing
- cart and checkout
- account and address book
- order visibility
- review and return requests

## 8. Module Plan by Surface

## 8.1 Marketing modules

- landing page
- pricing
- feature comparison
- template gallery
- vertical use cases
- testimonials
- blog and knowledge center
- contact and demo request
- store creation funnel

## 8.2 Owner modules

- platform dashboard
- merchant approvals
- tenant management
- domain center
- provider center
- plan and package manager
- feature preset manager
- theme preset studio
- support desk
- audit center
- security center
- finance reporting
- extension and integration center
- rollout and release center

## 8.3 Merchant modules

Core default modules:

- dashboard
- onboarding
- products
- categories
- brands
- orders
- customers
- pages
- banners
- navigation menus
- coupons
- promotions
- store settings
- domain settings
- payment toggles
- notification toggles
- reports
- staff/team

Advanced optional modules:

- suppliers
- purchases
- damages
- advanced stock
- outlets
- POS
- multi-location support
- advanced returns

## 8.4 Customer modules

Public modules:

- homepage
- collections
- products
- offers
- pages
- search
- cart
- checkout
- contact

Account modules:

- profile
- addresses
- orders
- order tracking
- wishlist
- review
- return requests

## 9. Merchant Experience Design

Merchant UX must feel calm and guided.

## 9.1 Default merchant navigation

- Home
- Products
- Orders
- Customers
- Content
- Marketing
- Store Settings
- Domain
- Team
- Reports

## 9.2 Dashboard should show

- sales today
- orders today
- pending orders
- top products
- customer growth
- low stock alert
- setup score
- domain status
- payment readiness

## 9.3 Onboarding checklist

Step 1:

- business info
- store identity
- logo and favicon

Step 2:

- language
- currency
- delivery areas
- payment methods

Step 3:

- add first products
- set homepage hero
- create key pages

Step 4:

- preview store
- launch on subdomain
- connect custom domain optional

## 9.4 Merchant mode strategy

Simple Mode:

- clean dashboard
- only essential modules
- ideal for non-technical sellers

Advanced Mode:

- inventory workflows
- suppliers
- purchases
- damages
- POS
- multi-outlet support

Owner decides who gets Advanced Mode by default.

## 10. Owner Experience Design

Owner panel must feel like a real control tower.

## 10.1 Owner dashboard should show

- total tenants
- active tenants
- new merchants today
- merchants in onboarding
- merchants live on custom domains
- total GMV today
- total orders today
- payment incident count
- domain verification failures
- support queue
- suspended merchants

## 10.2 Tenant profile should show

- merchant info
- plan and status
- domain status
- launch stage
- feature flags
- theme preset
- payment method status
- store health
- support history

## 10.3 Support tools should include

- view merchant timeline
- view last login
- view domain changes
- view order issues
- impersonate merchant
- add support notes
- lock risky actions

## 11. Customer Experience Design

Customer storefront must feel fast, trustworthy, and clean.

## 11.1 Trust elements

- secure checkout indicators
- payment method visibility
- delivery info
- clear return policy
- clear business contact

## 11.2 Local-market strengths

- Bangla-first storefront capability
- COD option
- local payment badges
- mobile-first checkout
- WhatsApp contact entry points

## 12. Domain and Brand Plan

## 12.1 Domain model

- every tenant gets one permanent fallback subdomain
- every tenant may connect multiple custom domains later
- only one primary public domain at a time
- fallback subdomain is never removed

## 12.2 Admin safety rule

Even if:

- DNS fails
- custom domain is deleted
- SSL expires
- Cloudflare verification breaks

Merchant still uses:

- `merchant.company.com`

Owner still uses:

- `owner.company.com`

## 12.3 Brand architecture

- `company.com` brand = platform brand
- storefront brand = merchant brand
- merchant app should subtly carry platform brand
- storefront should fully carry merchant brand

## 13. Business Presets

The fastest path to premium feel is preset-driven setup.

Recommended presets:

- fashion
- grocery
- electronics
- cosmetics
- pharmacy
- boutique
- restaurant pickup later

Preset should include:

- homepage section order
- starter page set
- color token suggestions
- delivery settings suggestion
- marketing block suggestions
- recommended modules

## 14. Feature Strategy

## 14.1 Phase 1 launch features

- owner panel
- merchant panel
- storefront
- merchant registration
- merchant onboarding
- products
- orders
- customers
- pages
- banners
- promotions
- coupons
- reports
- custom domains
- payment toggles
- notification toggles

## 14.2 Phase 2 features

- suppliers
- purchases
- stock workflows
- damages
- outlets
- POS
- advanced reporting

## 14.3 Phase 3 features

- theme marketplace
- extension marketplace
- multi-store per merchant
- automation
- abandoned cart campaigns
- loyalty system
- affiliate system

## 15. Data and Architecture Plan

## 15.1 Core identity model

- `users` = owner and merchant identities
- `customers` = storefront identities
- `tenant_members` = merchant-to-store relationship

## 15.2 Tenant model

- `tenants`
- `tenant_domains`
- `tenant_settings`
- `tenant_feature_flags`
- `tenant_payment_methods`
- `tenant_notification_channels`

## 15.3 Commerce ownership rule

These become tenant-scoped:

- products
- categories
- brands
- pages
- sliders
- coupons
- promotions
- orders
- reviews
- subscribers
- inventory support tables

## 16. Security and Governance Plan

## 16.1 Owner security

- mandatory 2FA
- audit logs
- suspicious login alerts
- action logging
- impersonation logging

## 16.2 Merchant security

- safe permission roles
- tenant-bound access
- store-scoped tokens
- staff invitation controls

## 16.3 Customer security

- tenant-bound account auth
- tenant-bound order access
- token surface validation

## 17. Operations Model

Platform operations should own:

- provider setup
- gateway maintenance
- SMS maintenance
- email maintenance
- push notifications
- analytics presets
- domain verification
- theme release
- feature rollouts
- incident response

Merchant operations should own:

- products
- pricing
- order handling
- homepage content
- campaign setup
- team access

## 18. Monetization and Packaging

Recommended packaging:

## Starter

- one store
- one theme preset
- essential modules
- default subdomain

## Growth

- custom domain
- marketing modules
- better reporting
- extra staff accounts

## Pro

- advanced inventory
- outlets
- POS
- priority support
- advanced analytics

Owner can still override module access manually if needed.

## 19. Metrics That Matter

Track these from day one:

Platform KPIs:

- signups
- merchant activation rate
- time to first product
- time to first published store
- time to first order
- active merchants
- custom domain adoption
- GMV
- support ticket volume

Merchant KPIs:

- product count
- order volume
- repeat customer rate
- coupon usage
- conversion trends

Customer KPIs:

- session to cart
- cart to checkout
- checkout to paid order
- return request ratio

## 20. Z to Z Roadmap

## Phase 0: Strategy freeze

Lock:

- domain rules
- user model
- role model
- module plan
- auth split
- preset strategy

## Phase 1: SaaS foundation

Build:

- tenants
- tenant domains
- tenant members
- customers
- tenant settings
- tenant feature flags

## Phase 2: Bootstrap migration

Build:

- bootstrap tenant
- data backfill
- old data mapping
- verification commands

## Phase 3: Auth split

Build:

- owner auth
- merchant auth
- storefront auth
- separate token surfaces

## Phase 4: Route and middleware split

Build:

- platform routes
- merchant routes
- storefront routes
- system routes
- host resolution middleware

## Phase 5: Merchant MVP

Build:

- dashboard
- onboarding
- products
- orders
- customers
- content
- store settings

## Phase 6: Owner control tower

Build:

- tenant management
- domain center
- provider center
- support desk
- audit center

## Phase 7: Domain automation

Build:

- Cloudflare integration
- SSL automation
- verification logs
- fallback restore tools

## Phase 8: Advanced merchant modes

Build:

- suppliers
- purchases
- damages
- outlets
- POS

## Phase 9: Platform excellence

Build:

- presets
- growth analytics
- automation
- scaling and monitoring

## 21. Launch Checklist

Before launch, confirm all of these:

- merchant registration works
- owner login works
- customer login works
- fallback storefront domain works
- custom domain verification works
- admin is not affected by storefront DNS
- one merchant can launch a store without developer help
- owner can suspend a merchant
- owner can impersonate a merchant
- payment methods can be enabled per tenant
- merchant cannot access platform credentials
- customer cannot access other tenant data

## 22. What Must Never Happen

Avoid these mistakes:

- mixing owner and merchant login
- mixing merchant and customer login
- using custom domain for admin app
- exposing platform provider credentials
- shipping too many themes at launch
- forcing all merchants into advanced workflows
- keeping customer auth mixed inside shared `users` forever
- writing merchant settings directly to `.env`

## 23. Recommended Team Build Order

If the team starts execution now, do it like this:

1. finalize master plan
2. lock database design
3. create core migrations
4. create bootstrap tenant scripts
5. build host resolution middleware
6. split auth
7. split routes
8. build merchant MVP
9. build owner control tower
10. build domain automation
11. run QA on fallback and custom domain flows
12. soft launch with controlled merchants

## 24. Final Z to Z Formula

The strongest possible version of this product is:

- one platform brand
- one owner control tower
- one merchant control app
- one storefront engine
- one permanent fallback subdomain
- optional custom storefront domain
- simple-first merchant UX
- owner-controlled technical complexity
- centrally updated architecture
- tenant-safe data model

## 25. Final Recommendation

Use this document as the master reference.

The other planning docs should now be treated as supporting working references only.

This file should carry the final decision when product, architecture, Laravel execution, or UI delivery questions conflict.

## 26. Unified Architecture Baseline

This section absorbs the critical architecture rules from the blueprint and roadmap documents.

## 26.1 Surface map

- `company.com` = marketing site
- `owner.company.com` = owner control tower
- `merchant.company.com` = merchant control app
- `shopslug.company.com` = default storefront
- `customdomain.com` = optional storefront alias

Rule:

- admin surfaces never depend on merchant DNS
- only storefront traffic is domain-resolved by tenant domain mapping

## 26.2 Identity model

- `users` remains the global identity table for owner and merchant users
- `customers` becomes the tenant-scoped identity table for storefront shoppers
- `tenant_members` controls merchant access to tenants
- `user_platform_roles` is used only for owner-side global roles

## 26.3 Tenant model

- every store is one `tenant`
- every tenant has one permanent slug-backed fallback subdomain
- every tenant can have multiple domains
- only one domain is primary at a time
- fallback subdomain is never removed even if custom domain is active

## 26.4 Settings and credentials model

Platform-owned only:

- payment gateway master credentials
- SMS master credentials
- mail master credentials
- Cloudflare and DNS credentials
- license, release, and infrastructure controls
- platform theme presets and provider presets

Tenant-owned:

- brand identity
- theme selection and layout data
- shipping and tax business rules
- social and notification toggles
- merchant-visible payment method enablement
- feature flags and onboarding state
- storefront content, menus, and pages

## 26.5 Minimum database groups

Global platform groups:

- `users`
- `platform_roles`
- `platform_permissions`
- `platform_role_permissions`
- `user_platform_roles`
- `platform_providers`
- `platform_theme_presets`
- `platform_audit_logs`

Tenant groups:

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

Customer groups:

- `customers`
- `customer_addresses`
- `customer_wishlists`

Existing commerce tables that become tenant-scoped:

- products and product support tables
- pages and storefront content tables
- coupons and promotions
- orders and order support tables
- reviews, subscribers, carts, and inventory support tables

## 27. Unified Laravel Implementation Baseline

This section absorbs the key build rules from the Laravel implementation handoff.

## 27.1 Laravel namespace split

Controllers:

- `App\Http\Controllers\Platform\*`
- `App\Http\Controllers\Merchant\*`
- `App\Http\Controllers\Storefront\*`
- `App\Http\Controllers\System\*`

Services:

- `App\Services\Platform\*`
- `App\Services\Merchant\*`
- `App\Services\Storefront\*`
- `App\Services\Tenancy\*`
- `App\Services\Domain\*`
- `App\Services\Auth\*`

Configs:

- `config/saas.php`
- `config/tenancy.php`
- `config/platform.php`

## 27.2 Route file split

API route files:

- `routes/api/platform-auth.php`
- `routes/api/platform.php`
- `routes/api/merchant-auth.php`
- `routes/api/merchant.php`
- `routes/api/storefront-auth.php`
- `routes/api/storefront.php`
- `routes/api/system.php`

Web route files:

- `routes/web/install.php`
- `routes/web/marketing.php`
- `routes/web/platform.php`
- `routes/web/merchant.php`
- `routes/web/storefront.php`
- `routes/web/payment.php`

Rule:

- `routes/api.php` and `routes/web.php` stay as aggregators only

## 27.3 Middleware baseline

Core new middleware:

- `IdentifyRequestSurface`
- `EnsurePlatformHost`
- `EnsureMerchantHost`
- `ResolveTenantFromHost`
- `EnsureTenantResolved`
- `EnsureTenantActive`
- `SetTenantContext`
- `EnsurePlatformUser`
- `EnsureOwnerRole`
- `EnsureMerchantUser`
- `EnsureMerchantMembership`
- `EnsureStorefrontCustomer`
- `EnsureTokenSurfaceMatchesRequest`
- `EnsureFeatureEnabled`
- `ApplyContextLocalization`

## 27.4 Authentication baseline

Phase 1 auth rule:

- use Sanctum for all API authentication

Authenticatable models:

- `User` for owner and merchant identities
- `Customer` for storefront identities

Token ability model:

- `surface:platform`
- `surface:merchant`
- `surface:storefront`
- `tenant:{id}`
- `permission:{code}`

## 27.5 Coding start order

If coding starts immediately, build in this order:

1. core migrations
2. bootstrap tenant commands
3. tenant resolution middleware
4. new auth controllers
5. route file split
6. merchant settings service split
7. customer model migration
8. feature flag middleware
9. owner support and audit tooling

## 28. Unified Migration and Release Plan

This section condenses the execution-safe rollout plan.

## 28.1 Migration waves

Wave 0:

- preflight checks
- backups and storage snapshot
- critical settings write freeze during cutover

Wave 1:

- create SaaS core tables

Wave 2:

- add nullable `tenant_id` to tenant-owned business tables

Wave 3:

- create bootstrap tenant from the current single-store live business
- backfill catalog, orders, settings, customers, and roles

Wave 4:

- enforce tenant-aware unique indexes
- make `tenant_id` required where backfill is complete

Wave 5:

- separate storefront customer auth to `customers`

Wave 6:

- move merchant-facing global settings into `tenant_settings`

Wave 7:

- cut over shared routes into surface-specific routes and middleware

Wave 8:

- retire old shared auth and global merchant setting write paths

## 28.2 Release sequence

- Release 1 = core SaaS tables only
- Release 2 = `tenant_id` columns plus bootstrap and backfill
- Release 3 = tenant resolution runtime reads
- Release 4 = merchant and owner auth split
- Release 5 = storefront customer auth split
- Release 6 = required tenant constraints and hidden old routes
- Release 7 = transitional cleanup

## 28.3 Acceptance criteria

The migration is only considered complete when:

- `merchant.company.com` is the only merchant admin entry point
- `owner.company.com` is the only owner entry point
- storefront host is resolved from `tenant_domains`
- custom domain changes never break merchant admin access
- customer auth is tenant-scoped
- merchant settings no longer depend on global store settings
- current single-store production data safely lives in one bootstrap tenant
- every tenant-owned table enforces `tenant_id`

## 29. Unified UI and Delivery Plan

This section absorbs the UI execution plan into the master.

## 29.1 Visual direction

The product should feel:

- clear
- trustworthy
- easy to operate
- premium through structure, not decoration

UI rules:

- use light backgrounds and white cards
- keep borders thin and shadows very soft
- use one stable primary color
- avoid heavy gradients, glassmorphism, and flashy effects
- keep typography simple and highly readable

Recommended base colors:

- background: `#F7F8FA`
- card: `#FFFFFF`
- border: `#E5E7EB`
- primary: `#2563EB`
- success: `#16A34A`
- warning: `#D97706`
- danger: `#DC2626`
- text strong: `#111827`
- text muted: `#6B7280`

## 29.2 Delivery order

The safest UI and product delivery order is:

1. owner web UI
2. merchant web UI
3. storefront checkout flow
4. Flutter customer app tenant-aware integration

## 29.3 Owner UI scope

Owner UI must include:

- login with 2FA
- dashboard
- tenants
- domains
- plans and subscriptions
- providers
- presets
- support
- audit logs
- platform settings

Owner UI should feel like:

- a control tower
- operational
- auditable
- clear under pressure

## 29.4 Merchant UI scope

Merchant UI must include:

- login
- registration
- onboarding wizard
- dashboard
- products
- orders
- customers
- domain management
- billing
- settings

Merchant UI should feel:

- simple to start
- guided
- business-first
- low technical

## 29.5 Storefront and Flutter scope

Storefront checkout scope:

- home
- category and search
- product detail
- cart
- checkout
- order success
- customer account

Flutter scope:

- tenant-aware app bootstrap
- tenant branding and public settings fetch
- customer auth against tenant-scoped storefront APIs
- order and account views consistent with web storefront rules

## 30. Single Source of Truth Policy

From now on, this file is the top-level master plan.

Working rule:

- if this file conflicts with another planning doc, this file wins
- product changes must be reflected here first
- architecture changes must be reflected here first
- Laravel rollout changes must be reflected here first
- UI priority changes must be reflected here first

The other planning docs should be used only as:

- deeper working notes
- implementation scratchpads
- extraction references for focused tasks

## 31. Final Consolidated Recommendation

Use `saas-master-plan-z-to-z.md` as the only master planning file.

Use the rest of the planning docs only when you need extra detail for one area, but do not let them redefine the master decisions.

The final operating rule is simple:

- one master plan
- one codebase
- one owner control tower
- one merchant control app
- one storefront engine
- one tenant-safe data model
- one staged Laravel migration path
- one simple and trustworthy UI direction
