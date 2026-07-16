# Managed Commerce SaaS Product Roadmap

Date: July 16, 2026
Status: Product strategy and execution roadmap
Companion doc: `docs/multitenant-saas-blueprint.md`

## 1. Product Thesis

This product should not be positioned as just another ecommerce builder.

It should be positioned as a managed commerce operating system:

- the platform team owns the technical complexity
- the merchant focuses on selling
- the customer gets a clean storefront experience
- every store runs on one shared, centrally updated platform

The biggest promise is not "build your own store".

The biggest promise is:

"Launch your ecommerce business fast, run it simply, and let the platform handle the hard parts."

## 2. What Makes This Product Unique

Most ecommerce tools put too much responsibility on the merchant.

This product should feel different in 6 ways:

### 2.1 Managed-by-platform model

- payment providers are configured by owner
- SMS, email, push, analytics, and domain systems are managed by owner
- merchant only turns approved features on or off
- merchant never touches technical credentials

### 2.2 Fixed admin, flexible storefront

- admin never depends on storefront domain
- merchant panel always stays on `merchant.company.com`
- customer storefront can move between `shopslug.company.com` and `customdomain.com`
- fallback subdomain always exists

### 2.3 Simplicity-first merchant experience

- new merchant sees only essential modules
- advanced inventory, supplier, purchase, and POS remain hidden by default
- owner can unlock advanced modules later

### 2.4 Centralized live update engine

- platform code is deployed once
- every tenant receives updates automatically
- no per-store deployment
- theme presets and module rules are centrally managed

### 2.5 Operator-grade owner panel

- owner panel is not a cosmetic admin
- it is the control tower for tenants, domains, providers, presets, support, security, and rollout

### 2.6 Local-market readiness

Recommended launch priorities for Bangladesh-first commerce:

- Bangla + English UI
- COD support
- bKash, Nagad, SSLCommerz, card gateway presets
- delivery area rules
- Facebook Pixel and Google Analytics presets
- WhatsApp-friendly customer communication

## 3. Product Surfaces

There should be 4 clearly separate surfaces.

### 3.1 Marketing site

Host: `company.com`

Purpose:

- explain the product
- show pricing and features
- show templates and examples
- convert visitors into merchants

Primary CTA:

- `Create Store`

### 3.2 Merchant app

Host: `merchant.company.com`

Purpose:

- merchant registration
- merchant login
- merchant onboarding
- merchant business operations

This is the only merchant admin entry point.

### 3.3 Owner app

Host: `owner.company.com`

Purpose:

- platform control
- tenant lifecycle
- provider management
- support and monitoring

This is invite-only and should have no public signup.

### 3.4 Storefront app

Host:

- `shopslug.company.com`
- `customdomain.com`

Purpose:

- customer browsing
- cart and checkout
- account and order tracking

## 4. Separate Login Strategy

Yes, every audience should have a separate login surface.

That separation will make the platform feel cleaner, safer, and more premium.

## 4.1 Owner login

URL:

- `owner.company.com/login`

Who uses it:

- founder
- platform admin
- ops team
- support team
- finance team

Rules:

- no public registration
- invite-only user creation
- mandatory 2FA
- suspicious-login alerts
- IP and activity logs

Page content:

- email
- password
- 2FA code or passkey
- forgot password
- security notice

## 4.2 Merchant login

URL:

- `merchant.company.com/login`

Who uses it:

- merchant owner
- merchant manager
- merchant staff

Rules:

- public registration is allowed only here
- login by email or phone
- optional OTP login later
- merchant session is isolated from storefront customer session

Page content:

- email or phone
- password
- remember me
- forgot password
- create store
- support link

## 4.3 Merchant registration

URL:

- `merchant.company.com/register`

Fields:

- merchant name
- business or store name
- email
- phone
- password
- preferred store slug
- country
- business category

Post-registration system actions:

- create merchant user
- create tenant
- reserve slug
- create default subdomain
- apply starter theme preset
- apply starter feature preset
- create onboarding checklist

## 4.4 Customer login

URL:

- `shopslug.company.com/account/login`
- `customdomain.com/account/login`

Who uses it:

- store customer

Rules:

- customer account belongs to one store
- customer session stays on storefront domain
- owner and merchant users never log in here

Page content:

- email or phone
- password or OTP
- register
- forgot password
- order tracking shortcut

## 5. Surface-by-Surface Module Plan

## 5.1 Marketing site modules

Must include:

- hero and conversion funnel
- pricing
- features
- templates
- vertical use cases
- testimonials
- FAQ
- blog or resources
- contact and demo request

Nice-to-have:

- live demo stores
- ROI calculator
- migration offer page
- partner or agency page

Must not include:

- merchant runtime logic
- owner dashboards
- tenant-specific data

## 5.2 Owner app modules

Owner app should feel like a serious business operating console.

### Core owner modules

- platform dashboard
- merchant approvals
- tenant management
- domain center
- provider center
- plan and package manager
- feature flag manager
- theme preset studio
- onboarding preset manager
- support desk
- audit and security center
- reports and finance
- release and rollout center

### Owner dashboard should show

- total active tenants
- new signups today
- tenants in onboarding
- tenants live on custom domains
- GMV today
- order count today
- failed payment events
- failed domain verification events
- support queue
- suspended tenants

### Tenant management should include

- create tenant manually
- approve or reject merchant registration
- suspend tenant
- archive tenant
- reset store slug
- reset onboarding
- inspect merchant account
- impersonate tenant for support

### Domain center should include

- subdomain registry
- custom domain verification
- SSL status
- DNS issue detection
- fallback domain restore
- Cloudflare integration health

### Provider center should include

- payment providers
- SMS providers
- email providers
- push providers
- analytics providers
- domain providers

### Theme preset studio should include

- starter theme versions
- business-category presets
- homepage block presets
- global typography and color tokens
- safe update controls

### Support desk should include

- merchant timeline
- login history
- domain history
- payment incidents
- support notes
- impersonation audit trail

## 5.3 Merchant app modules

Merchant app should feel calm, focused, and beginner-friendly.

### Merchant core navigation

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

### Merchant dashboard should show

- sales today
- orders today
- pending orders
- low stock
- top products
- conversion snapshot
- onboarding progress
- domain status
- payment status

### Merchant onboarding flow

Step 1:

- business name
- logo
- category
- phone and email

Step 2:

- store language
- currency
- delivery areas
- payment methods

Step 3:

- first 3 products
- hero banner
- homepage content

Step 4:

- preview store
- connect custom domain optional
- go live checklist

### Merchant modules by area

Catalog:

- products
- categories
- brands
- inventory summary
- bulk import later

Orders:

- online orders
- order details
- order status change
- return request review
- payment status review

Customers:

- customer list
- customer profile
- address summary
- order history
- segment basics

Content:

- homepage sections
- pages
- banners
- navigation menus
- footer blocks
- theme settings

Marketing:

- coupons
- promotions
- announcement bar
- subscriber list
- abandoned cart later
- basic analytics

Store settings:

- business info
- shipping rules
- tax rules
- payment toggles
- notification toggles
- SEO basics
- social links

Domain:

- current subdomain
- custom domain request
- DNS instructions
- verification state
- fallback status

Team:

- invite staff
- assign merchant roles
- suspend staff

Reports:

- sales summary
- order summary
- top products
- top customers

## 5.4 Customer storefront modules

Customer experience should be fast, clean, and trust-building.

### Public storefront modules

- homepage
- category and collection pages
- product details
- search
- cart
- checkout
- order confirmation
- static pages
- contact

### Customer account modules

- login and register
- profile
- saved addresses
- order history
- order tracking
- wishlist
- review submission
- return request
- support contact

## 6. Who Gets What

## 6.1 Owner gets

- full platform authority
- all tenant visibility
- provider control
- feature control
- domain control
- support control
- release control
- security control

Owner should control:

- who can sign up
- which modules a merchant can access
- which payment methods a merchant can enable
- which theme preset a merchant receives
- whether a tenant stays active or suspended

## 6.2 Merchant gets

- one admin account system
- one or more staff users inside their tenant
- storefront management
- product and order management
- customer management
- safe feature toggles

Merchant should never control:

- platform credentials
- Cloudflare credentials
- SMTP master credentials
- SMS API master credentials
- platform release settings
- environment variables

## 6.3 Customer gets

- store-specific account
- secure checkout
- order visibility
- address management
- return and review access

Customer should never see:

- merchant back office
- owner controls
- platform service complexity

## 7. Simple Mode vs Advanced Mode

This is one of the strongest possible product decisions.

By default, merchants should enter `Simple Mode`.

### Simple Mode

Shown by default:

- dashboard
- products
- orders
- customers
- coupons
- promotions
- pages
- homepage blocks
- domain
- payments
- notifications

Hidden by default:

- suppliers
- purchases
- damages
- outlets
- POS
- advanced reports
- advanced stock workflows

### Advanced Mode

Owner can unlock:

- supplier management
- purchase management
- warehouse logic
- stock damage tracking
- multi-outlet support
- POS
- advanced return and refund rules

## 8. Premium Experience Principles

If you want the platform to feel more powerful and more dedicated, follow these principles.

### 8.1 Fewer choices, better defaults

- launch with one excellent theme
- launch with one excellent onboarding path
- launch with predefined business presets

### 8.2 Guided setup, not empty dashboards

New merchants should see:

- setup score
- next best action
- missing requirements
- "go live" progress

### 8.3 Strong trust indicators

Storefront should show:

- secure checkout badge
- payment method icons
- return policy
- delivery details
- contact details

### 8.4 Clean language separation

- owner language: operational
- merchant language: business-friendly
- customer language: shopping-friendly

### 8.5 Platform-assisted success

Owner app should support:

- store review before launch
- growth prompts
- issue alerts
- policy reminders

## 9. Merchant Preset Strategy

The fastest route to a premium product is presets.

Recommended preset families:

- Fashion Store
- Grocery Store
- Electronics Store
- Cosmetics Store
- Pharmacy Store
- Local Boutique

Each preset should define:

- homepage section order
- banner style
- default page set
- recommended modules
- default tax and shipping suggestions
- recommended theme tokens

## 10. End-to-End Journey

## 10.1 Visitor to merchant journey

1. Visitor lands on `company.com`.
2. Reads features, pricing, and sample stores.
3. Clicks `Create Store`.
4. Moves to `merchant.company.com/register`.
5. Creates account and chooses store slug.
6. Tenant is created automatically.
7. Merchant enters onboarding.
8. Merchant uploads logo, adds first products, enables payment methods.
9. Merchant previews `shopslug.company.com`.
10. Merchant optionally connects custom domain.
11. Store goes live.

## 10.2 Merchant to live store journey

1. Merchant logs in at `merchant.company.com/login`.
2. Dashboard shows onboarding progress.
3. Merchant adds products and content.
4. Merchant checks shipping and payment setup.
5. Merchant previews store.
6. Merchant clicks publish.
7. Store becomes live on fallback subdomain.
8. Merchant later connects custom domain.

## 10.3 Customer shopping journey

1. Customer visits `shopslug.company.com` or `customdomain.com`.
2. Browses products.
3. Adds to cart.
4. Completes checkout.
5. Receives order confirmation.
6. Tracks order from account page.
7. Later submits review or return request if needed.

## 11. Roadmap by Phase

## Phase 0: Strategy freeze

Objective:

- lock product rules before implementation

Deliverables:

- audience model
- domain model
- module model
- permission model
- onboarding model
- theme preset strategy

Success criteria:

- no unresolved ambiguity around owner, merchant, customer surfaces

## Phase 1: SaaS foundation

Objective:

- build tenant core

Deliverables:

- tenants
- tenant domains
- tenant members
- customers
- tenant settings
- feature flags
- storefront host resolver

Success criteria:

- one platform can safely host many stores

## Phase 2: Merchant-first MVP

Objective:

- make merchants able to create and run stores

Deliverables:

- merchant registration
- merchant login
- onboarding
- product management
- order management
- customer management
- content blocks
- basic reports
- default subdomain launch

Success criteria:

- a non-technical merchant can create a store and sell

## Phase 3: Owner control tower

Objective:

- give platform team complete operational control

Deliverables:

- owner dashboard
- tenant management
- provider center
- domain center
- support impersonation
- audit logs
- rollout center

Success criteria:

- platform team can manage growth without manual chaos

## Phase 4: Custom domains and managed services

Objective:

- make the platform feel premium and scalable

Deliverables:

- Cloudflare integration
- custom hostname verification
- SSL automation
- fallback domain guarantee
- provider presets

Success criteria:

- custom domains work without affecting merchant admin access

## Phase 5: Experience excellence

Objective:

- make the platform feel unique and polished

Deliverables:

- category presets
- setup score
- go-live checklist
- template refinements
- performance optimization
- analytics refinement

Success criteria:

- merchants can succeed with less effort than on generic builders

## Phase 6: Scale and defensibility

Objective:

- prepare for thousands of stores

Deliverables:

- queue scaling
- caching strategy
- search indexing
- media optimization
- monitoring and alerts
- tenant-safe backups
- disaster recovery
- event pipeline

Success criteria:

- platform remains stable under large tenant growth

## 12. What Not To Do

Avoid these mistakes:

- do not mix owner login and merchant login
- do not let custom domain drive admin authentication
- do not expose master provider credentials to merchants
- do not launch with too many themes
- do not show advanced modules to every merchant
- do not allow merchant settings to write directly to `.env`
- do not make every tenant deploy separately
- do not keep customer and merchant identities in one undifferentiated flow

## 13. Best MVP Scope

If you want the strongest first version, launch with this scope:

### Launch now

- owner panel
- merchant panel
- customer storefront
- merchant registration
- merchant onboarding
- product management
- order management
- customer accounts
- theme preset v1
- coupons and promotions
- custom domain support
- payment toggles
- delivery areas
- basic reports

### Launch later

- POS
- suppliers
- purchases
- damages
- multi-outlet
- app marketplace
- open developer extensions
- multi-theme marketplace
- affiliate system

## 14. Final Recommended Positioning

The best positioning line for this product is:

"A managed ecommerce platform where merchants sell, customers shop, and the platform handles the heavy technical operations."

The strongest execution formula is:

- separate surfaces
- separate logins
- centralized operations
- tenant-safe architecture
- simple merchant experience
- owner-controlled complexity
- storefront-only domain customization

## 15. Reference Patterns

These current official docs support the core strategy of a permanent platform-controlled identifier or admin surface plus optional custom storefront domains:

- Shopify Help Center: `myshopify.com` remains a permanent store identifier even when customers see a custom domain.
- Shopify Help Center: domain and storefront settings are managed from Shopify admin.
- Ecwid Help Center: custom domains are connected from admin and the default site remains available as fallback after disconnect.
- Cloudflare Docs: wildcard DNS supports many subdomains on shared infrastructure.
- Cloudflare for SaaS: custom hostnames and SSL can be provisioned at scale for customer-owned domains.
