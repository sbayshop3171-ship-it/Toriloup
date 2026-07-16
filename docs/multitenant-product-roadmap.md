# Dedicated Multi-Tenant SaaS Product Roadmap

Date: July 16, 2026
Status: Product strategy and execution roadmap
Companion document: `docs/multitenant-saas-blueprint.md`

## 1. Product North Star

We are not building only another ecommerce script.

We are building a managed commerce platform where:

- the platform owner controls infrastructure, integrations, risk, and quality
- the merchant launches fast without technical burden
- the customer gets a fast, trustworthy, localized storefront

The product must feel:

- premium
- guided
- low-friction
- scalable
- operator-managed
- brandable

The biggest difference from ordinary ecommerce software:

- merchants should not need to understand servers, DNS, payment credentials, SMS setup, mail setup, or technical theme work
- the owner panel should carry the heavy complexity
- the merchant panel should feel simple, focused, and business-friendly

## 2. Strategic Positioning

Recommended positioning:

`A managed ecommerce operating system for merchants who want to sell, not configure software.`

This positioning is stronger than a normal website builder because it gives:

- instant launch with owner-managed infrastructure
- built-in admin panel and storefront in one package
- platform-curated payments, SMS, notifications, and growth tools
- one-click central updates across all stores
- custom domain support without breaking merchant operations

## 3. Product Doctrine

These rules should guide every future decision.

### 3.1 Control doctrine

- owner controls platform-level power
- merchant controls store-level business
- customer controls only shopping and account actions

### 3.2 Domain doctrine

- domains are only for storefront delivery
- merchant admin never depends on storefront domain
- every merchant always has a permanent fallback subdomain

### 3.3 Simplicity doctrine

- simple merchants should see a simple system
- advanced modules should stay hidden until needed
- the default experience should launch a store with minimal decisions

### 3.4 Scale doctrine

- all tenants use one codebase
- all product releases are centrally deployed
- no per-merchant code forks
- per-tenant customization must be data-driven, not code-driven

### 3.5 Trust doctrine

- owner handles risky infrastructure actions
- merchant gets safe toggles, not raw credentials
- all sensitive actions must be auditable

## 4. Surface Architecture

## 4.1 Public and private surfaces

| Surface | URL | User type | Job to be done |
| --- | --- | --- | --- |
| Marketing site | `company.com` | visitor | understand product, trust brand, create store |
| Owner panel | `owner.company.com` | platform owner team | manage the whole ecosystem |
| Merchant panel | `merchant.company.com` | merchant and merchant staff | operate one store |
| Storefront | `shopslug.company.com` | customer | browse and buy |
| Custom storefront | `customdomain.com` | customer | browse and buy |

## 4.2 Separate login pages

All important audiences should have separate login experiences.

### Owner login

- URL: `owner.company.com/login`
- audience: owner, operations, finance, support, developer
- purpose: platform control
- branding: serious, operational, secure, audit-focused

### Merchant login

- URL: `merchant.company.com/login`
- audience: merchant owner, store managers, merchant staff
- purpose: store management
- branding: business-friendly, optimistic, launch-focused

### Merchant registration

- URL: `merchant.company.com/register`
- audience: new merchant
- purpose: create merchant account and store

### Customer login

Recommended MVP URL:

- `shopslug.company.com/account/login`
- `customdomain.com/account/login`

Recommended phase 2 premium URL:

- `account.shopslug.company.com/login`
- `account.customdomain.com/login`

Reason:

- customer account should live inside storefront identity
- owner login should never be mixed with customer login
- merchant login should never be mixed with customer login

### Password reset pages

- owner: `owner.company.com/forgot-password`
- merchant: `merchant.company.com/forgot-password`
- customer: `storefrontdomain.com/account/forgot-password`

## 5. Experience by Persona

## 5.1 Owner experience

The owner experience should feel like a command center.

Owner goals:

- launch and govern thousands of stores
- approve and support merchants
- manage providers and risk
- monitor growth and health
- push updates centrally

Owner dashboard should show:

- total active tenants
- new stores today
- suspended stores
- stores pending approval
- stores pending domain verification
- GMV today, this week, this month
- payment success rate
- failed webhook count
- top growth merchants
- support queue and alerts

Owner panel modules:

- platform overview
- merchant approval queue
- tenant list and tenant profile
- tenant health score
- domain operations
- theme preset manager
- provider manager
- feature preset manager
- support tools
- billing and commissions
- audit logs
- release center
- extension and add-on manager

## 5.2 Merchant experience

The merchant experience should feel like:

- simple to start
- focused on sales
- visually clear
- guided
- low-technical

Merchant goals:

- create store fast
- upload products fast
- receive orders fast
- go live without technical confusion

Merchant dashboard should show:

- sales today
- orders today
- pending orders
- delivered orders
- abandoned cart trend
- top products
- stock alerts
- store readiness score
- domain status
- payment status
- storefront live status

Merchant panel design philosophy:

- no raw SMTP
- no raw gateway secrets
- no raw DNS complexity unless necessary
- no unnecessary enterprise screens on day one

## 5.3 Customer experience

The customer experience should feel:

- fast
- trusted
- local
- mobile-first
- brand-consistent

Customer goals:

- discover products
- trust the store
- checkout easily
- track order
- contact store
- reorder later

Customer account pages:

- login
- register
- forgot password
- account dashboard
- profile
- address book
- order history
- order details
- wishlist
- reviews
- returns

## 6. Role Boundaries

## 6.1 What owner controls

Owner should control:

- platform branding defaults
- new merchant approval
- tenant suspension and reactivation
- payment provider master accounts
- SMS provider master accounts
- email provider master accounts
- push provider master accounts
- analytics provider master accounts
- Cloudflare and domain automation
- theme presets
- extension marketplace
- feature exposure policy
- staff permission models
- support impersonation
- audit trails
- release rollout

Owner should not do daily merchant business work except in support mode.

## 6.2 What merchant controls

Merchant should control:

- store name and logo
- product catalog
- pricing
- inventory basics
- order fulfillment
- coupons
- promotions
- content pages
- homepage sections
- theme choices within allowed preset
- domain request and primary domain choice
- enabling pre-approved payment methods
- enabling pre-approved notifications
- staff accounts inside own store

Merchant should not control:

- server config
- platform provider master credentials
- Cloudflare master settings
- code deployment
- platform audit retention

## 6.3 What customer controls

Customer should control:

- own account
- own addresses
- own cart
- own orders
- own wishlist
- own reviews
- return requests where allowed

Customer should not see:

- merchant operations
- backend integrations
- platform-owned settings

## 7. Module Ownership Matrix

| Module | Owner | Merchant | Customer |
| --- | --- | --- | --- |
| Platform dashboard | Full | No | No |
| Tenant creation | Full | No | No |
| Merchant registration review | Full | No | No |
| Domain verification engine | Full | Request only | No |
| Theme preset publishing | Full | No | No |
| Store theme assignment | Full | Own | No |
| Product management | No | Own | No |
| Category and brand management | No | Own | No |
| Orders and fulfillment | Limited support | Own | Order tracking only |
| Customer account management | No | View own customers | Own only |
| Coupons and promotions | No | Own | Use only |
| Pages and content blocks | No | Own | View only |
| Subscribers | No | Own | Opt-in or opt-out |
| Reviews | Moderate | Own | Write own |
| Return policies | Full defaults | Own within limits | Request only |
| Payment provider network | Full | Toggle only | Pay only |
| SMS and notifications | Full | Toggle only | Receive only |
| Feature flags | Full | No | No |
| Merchant staff roles | Policy | Own | No |
| Platform billing | Full | View own charges | No |
| Audit logs | Full | Own tenant | No |
| Support impersonation | Full | No | No |

## 8. Merchant Module Strategy

## 8.1 Default modules for every merchant

Every merchant should get these by default:

- dashboard
- products
- categories
- brands
- orders
- customers
- coupons
- promotions
- pages
- homepage content
- theme settings
- domain manager
- payment methods
- notifications
- reports
- staff

## 8.2 Hidden advanced modules

These should exist, but stay hidden until owner enables them:

- suppliers
- purchases
- damages
- stock reconciliation
- outlets
- POS
- branch-level inventory
- advanced accounting exports

Why:

- most new merchants want to sell quickly, not manage warehouse complexity
- too many modules make the product feel heavy
- hiding complexity is part of the premium experience

## 8.3 Merchant dashboard sidebar recommendation

### Core section

- dashboard
- products
- orders
- customers
- marketing

### Store section

- website content
- theme
- domain
- payments
- notifications

### Business section

- reports
- staff
- settings

### Advanced section

- inventory advanced
- suppliers
- purchases
- damages
- outlets
- POS

This section appears only when enabled.

## 9. Customer-Facing Storefront Strategy

Each storefront should have:

- one starter theme
- clean mobile layout
- fast home page
- category navigation
- product search
- cart
- checkout
- trust blocks
- customer account
- order tracking

Recommended homepage structure:

1. hero banner
2. trust strip
3. category highlights
4. featured products
5. promotions
6. best sellers
7. store benefits
8. FAQ
9. newsletter
10. footer

## 10. What Makes the Product Feel Unique

To make the platform feel more powerful and more dedicated than a generic script, build these product ideas into the roadmap.

## 10.1 Guided launch mode

After merchant registration, show a launch checklist:

- add logo
- add first 5 products
- set shipping
- enable payment
- connect domain
- publish storefront

Display a readiness score like:

- `35% ready`
- `70% ready`
- `100% live-ready`

## 10.2 Owner-curated growth packs

Instead of overwhelming merchants with raw settings, offer presets:

- starter pack
- fashion pack
- grocery pack
- electronics pack
- restaurant lite pack

Each pack can configure:

- homepage layout
- default pages
- notification copy
- coupon templates
- feature toggles

## 10.3 Domain shield

Even after custom domain goes live:

- fallback subdomain always stays active
- owner can instantly switch traffic back
- domain failures do not block merchant admin

## 10.4 Store health score

Owner and merchant both should see store health:

- domain connected
- payment enabled
- low stock issues
- broken images
- missing policy pages
- checkout abandonment spike
- pending order delay

## 10.5 Support mode

Owner support team can:

- impersonate merchant safely
- view domain logs
- view payment errors
- view notification failures
- fix configuration without asking merchant to do technical work

## 10.6 Central release center

Owner should publish updates once and roll them across all stores:

- theme update
- payment provider update
- security patch
- checkout improvement
- analytics update

## 11. Full User Journey

## 11.1 Visitor to merchant journey

1. Visitor lands on `company.com`
2. Visitor clicks `Create Store`
3. Visitor goes to `merchant.company.com/register`
4. Merchant creates account
5. Merchant enters store name and desired slug
6. System creates tenant
7. System assigns `shopslug.company.com`
8. System attaches starter theme and default content
9. System lands merchant into onboarding wizard
10. Merchant uploads logo and first products
11. Merchant configures business basics
12. Merchant enables available payment methods
13. Merchant enables notifications
14. Merchant previews storefront
15. Merchant publishes store
16. Merchant optionally connects custom domain
17. Owner or system verifies domain
18. Store runs on custom domain
19. Merchant still logs in only at `merchant.company.com`

## 11.2 Merchant to staff journey

1. Merchant owner opens staff section
2. Merchant invites manager or staff
3. Staff receives invite
4. Staff logs in at `merchant.company.com/login`
5. Staff only sees permitted modules

## 11.3 Customer journey

1. Customer visits `shopslug.company.com` or `customdomain.com`
2. Customer browses and adds items
3. Customer creates account or checks out as guest
4. Customer places order
5. Customer receives notifications
6. Customer logs in at `storefrontdomain.com/account/login`
7. Customer sees order history and status

## 12. Login and Registration Map

| Audience | URL | Registration | Password reset | Post-login destination |
| --- | --- | --- | --- | --- |
| Owner | `owner.company.com/login` | by owner invite only | yes | owner dashboard |
| Merchant owner | `merchant.company.com/register` and `/login` | public or approved | yes | merchant onboarding or dashboard |
| Merchant staff | `merchant.company.com/login` | by invite only | yes | merchant dashboard with limited access |
| Customer | `storefrontdomain.com/account/register` and `/account/login` | tenant storefront only | yes | customer account |

## 13. Recommended Product Screens

## 13.1 Marketing

- homepage
- features
- pricing
- templates
- merchant stories
- create store
- help center

## 13.2 Owner panel

- owner login
- overview dashboard
- tenant list
- tenant details
- merchant profile
- domain operations
- provider manager
- feature presets
- theme presets
- audit center
- support tools
- billing and revenue

## 13.3 Merchant panel

- login
- register
- onboarding wizard
- dashboard
- products
- product create and edit
- categories
- orders
- order details
- customers
- coupons
- promotions
- pages
- theme editor lite
- domain manager
- payment methods
- notifications
- staff manager
- reports
- settings

## 13.4 Customer storefront

- home
- category page
- search page
- product detail
- cart
- checkout
- thank you page
- login
- register
- order history
- order details
- wishlist
- profile

## 14. Priority Roadmap

## Phase 0: Product freeze

Goal:

- finalize doctrine, roles, and surface split

Deliverables:

- blueprint
- roadmap
- module map
- permission policy
- URL policy

## Phase 1: Platform foundation

Goal:

- build true tenant core

Deliverables:

- tenants
- tenant domains
- tenant members
- customers
- tenant settings
- tenant feature flags
- tenant-aware middleware

## Phase 2: Split authentication surfaces

Goal:

- separate owner, merchant, and customer authentication

Deliverables:

- owner login and owner guards
- merchant login and merchant guards
- storefront customer login and customer guards
- invite flows
- forgot password flows

## Phase 3: Merchant MVP

Goal:

- let merchants launch stores fast

Deliverables:

- merchant registration
- onboarding wizard
- starter theme
- catalog
- orders
- pages
- coupons
- promotions
- domain manager
- readiness score

## Phase 4: Owner operating system

Goal:

- let owner control quality and scale

Deliverables:

- tenant operations center
- provider manager
- feature presets
- support impersonation
- audit logs
- health score

## Phase 5: Domain and automation

Goal:

- professional scale with safe domain handling

Deliverables:

- wildcard subdomain routing
- custom domain verification
- SSL automation
- fallback switch logic
- domain status logs

## Phase 6: Premium merchant experience

Goal:

- make the product feel polished and differentiated

Deliverables:

- business presets
- content presets
- analytics tiles
- low-stock intelligence
- marketing suggestions

## Phase 7: Advanced commerce modules

Goal:

- serve larger merchants without burdening smaller ones

Deliverables:

- suppliers
- purchases
- damages
- outlets
- POS
- advanced inventory

## Phase 8: Scale hardening

Goal:

- support high tenant count and high order volume

Deliverables:

- caching strategy
- queues
- search indexing
- async media processing
- reporting snapshots
- webhook retries
- observability

## 15. Success Metrics

Track these metrics from day one:

- time from registration to first live store
- time from registration to first product
- percentage of stores that complete onboarding
- percentage of stores that connect a custom domain
- order success rate
- payment success rate
- merchant weekly active rate
- customer repeat purchase rate
- support tickets per 100 stores

Target examples:

- first live store in less than 30 minutes
- first product in less than 10 minutes
- onboarding completion above 65%
- payment success above 95%

## 16. Final Recommendation

The strongest version of this product is not:

- a raw ecommerce installer
- a crowded admin panel
- a merchant-do-everything control panel

The strongest version is:

- owner-heavy operations
- merchant-light setup
- customer-clean storefront
- domain-safe architecture
- modular complexity
- centralized release and support

If you follow this roadmap, the platform will feel:

- more premium than generic ecommerce scripts
- more manageable than fully self-service builders
- more scalable for you as the owner
- more comfortable for non-technical merchants
