# SaaS Simple UI Execution Plan

Date: July 16, 2026
Status: UI and product execution plan
Scope: Owner web UI, merchant web UI, storefront checkout flow, Flutter customer app

## 1. Goal

The next smart delivery order should be:

1. owner web UI complete
2. merchant web UI complete
3. storefront checkout flow complete
4. Flutter customer app tenant-aware integration complete

This document defines the simplest correct design direction:

- no flashy lighting
- no heavy gradients
- no glassmorphism
- no dark dramatic hero effects
- no overloaded cards
- clean, normal, business-style interface

The product should feel:

- clear
- trustworthy
- easy to operate
- premium through structure, not decoration

## 2. Visual Direction

## 2.1 General style

Use a flat and simple admin/product style:

- background: soft white or very light gray
- cards: white with thin border
- shadow: very light or none
- primary color: one stable brand color only
- accent color: one support color for success states
- danger color: one clear red
- text: dark gray, not pure black

Recommended base direction:

- page background: `#F7F8FA`
- card background: `#FFFFFF`
- border: `#E5E7EB`
- primary: `#2563EB`
- success: `#16A34A`
- warning: `#D97706`
- danger: `#DC2626`
- text strong: `#111827`
- text muted: `#6B7280`

## 2.2 Typography

Keep typography simple and readable:

- heading font: current system-safe or platform default
- body font: current system-safe or platform default
- no decorative type
- title weight: 600
- section title: 500
- body: 400

## 2.3 Spacing

Use one spacing rhythm:

- page padding: `24px`
- card padding: `20px`
- form gap: `16px`
- section gap: `24px`
- small gap: `8px`

## 2.4 Components

Every surface should share these base components:

- app shell
- page header
- section card
- stat card
- data table
- search/filter bar
- right-side drawer
- modal confirm dialog
- empty state
- loading skeleton
- error state
- success toast

## 3. Product Surface Rule

Non-negotiable routing remains:

- `company.com` = marketing
- `merchant.company.com` = merchant admin
- `owner.company.com` = owner admin
- `shopslug.company.com` = storefront
- `customdomain.com` = storefront alias only

Separate login remains:

- `owner.company.com/login`
- `merchant.company.com/login`
- `shopslug.company.com/account/login`

## 4. Owner Web UI

## 4.1 Owner design objective

Owner panel should feel like a control tower.

It should answer these questions fast:

- how many stores are active
- which stores need approval
- which domains are pending
- which plans are in use
- which merchants are blocked
- which providers are healthy

## 4.2 Owner app shell

Layout:

- left sidebar
- top header
- center content
- optional right drawer for quick detail

Sidebar menu:

- Dashboard
- Tenants
- Domains
- Plans
- Subscriptions
- Providers
- Presets
- Support
- Audit Logs
- Settings

Header actions:

- global search
- notification bell
- environment badge
- user menu

## 4.3 Owner pages

### A. Login

Fields:

- email
- password
- 2FA code

Actions:

- login
- forgot password

### B. Dashboard

Blocks:

- total tenants
- active tenants
- suspended tenants
- pending approvals
- verified domains
- pending domain requests
- monthly GMV
- active subscriptions

Charts:

- tenant growth
- subscription distribution
- order volume trend

Tables:

- latest merchants
- pending approvals
- latest incidents

### C. Tenant list

Columns:

- store name
- slug
- owner name
- plan
- status
- onboarding status
- primary domain
- created date

Row actions:

- view
- approve
- suspend
- reactivate
- assign plan

### D. Tenant detail

Sections:

- profile
- domains
- members
- billing
- feature flags
- payment methods
- activity timeline

Quick actions:

- approve merchant
- suspend store
- change plan
- verify domain

### E. Domain management

Tabs:

- pending
- verified
- failed

Columns:

- hostname
- tenant
- type
- verification status
- SSL status
- provider
- last checked

Actions:

- verify
- mark primary
- reject

### F. Plans

List:

- plan name
- code
- monthly price
- yearly price
- product limit
- domain limit
- staff limit
- status

Form:

- name
- code
- description
- monthly price
- yearly price
- trial days
- transaction fee type
- transaction fee value
- limits
- active or draft status

### G. Subscriptions

Columns:

- tenant
- plan
- interval
- price
- status
- period start
- period end
- latest invoice

Actions:

- view
- mark invoice paid
- reassign plan

### H. Providers

Sections:

- payment providers
- SMS providers
- email providers
- push providers

Each provider card shows:

- provider name
- status
- environment
- last updated

### I. Presets

Preset groups:

- storefront theme preset
- onboarding preset
- shipping preset
- payment preset
- legal page preset

### J. Audit logs

Columns:

- actor
- action
- resource
- tenant
- IP
- created at

## 4.4 Owner implementation order

1. owner app shell
2. login page
3. dashboard
4. tenant list and detail
5. domain management
6. plans and subscriptions
7. providers
8. audit logs

## 5. Merchant Web UI

## 5.1 Merchant design objective

Merchant panel should feel calm and task-based.

The merchant should quickly do:

- create store
- upload products
- see orders
- manage customers
- request custom domain
- check billing

The merchant should not feel platform complexity.

## 5.2 Merchant app shell

Layout:

- left sidebar
- top header
- content area

Sidebar simple mode:

- Home
- Products
- Orders
- Customers
- Storefront
- Domain
- Billing
- Settings

Sidebar advanced mode:

- Home
- Products
- Categories
- Brands
- Attributes
- Inventory
- Suppliers
- Purchases
- Stock
- POS
- Orders
- Return Orders
- Returns and Refunds
- Customers
- Promotions
- Content
- Theme
- Domain
- Billing
- Reports
- Staff
- Settings

## 5.3 Merchant pages

### A. Merchant login

Fields:

- email or phone
- password

Actions:

- login
- create store
- forgot password

### B. Merchant registration

Fields:

- merchant name
- store name
- email
- phone
- password
- preferred slug
- country

### C. Onboarding wizard

Steps:

1. business info
2. logo and brand name
3. first product
4. payment method enable
5. shipping setup
6. storefront preview
7. launch

Right sidebar checklist:

- profile complete
- first product uploaded
- payment active
- shipping active
- domain ready

### D. Merchant dashboard

Top stats:

- total sales
- total orders
- pending orders
- total customers
- total products

Middle blocks:

- onboarding progress
- recent orders
- top products
- store health

Bottom blocks:

- billing summary
- current plan
- domain status

### E. Products

Screens:

- product list
- create product
- edit product
- product detail

List filters:

- status
- category
- stock
- search

### F. Orders

Screens:

- all orders
- order detail
- status update
- payment status update

### G. Customers

Screens:

- customer list
- customer detail
- order history

### H. Domain

Sections:

- default subdomain
- custom domain request
- verification guide
- primary domain status

### I. Billing

Sections:

- current plan
- usage summary
- invoices
- upgrade request

### J. Settings

Simple settings:

- store info
- contact info
- social links
- delivery note
- return policy

Advanced settings:

- staff roles
- advanced modules
- notification preferences

## 5.4 Merchant implementation order

1. merchant app shell
2. login and registration UI
3. onboarding wizard
4. dashboard
5. products
6. orders
7. customers
8. domain
9. billing
10. settings

## 6. Storefront Checkout Flow

## 6.1 Storefront design objective

Storefront should feel:

- simple
- fast
- clean
- mobile-friendly

No visual overload.

The customer should complete checkout with minimum friction.

## 6.2 Storefront pages

### A. Home

Sections:

- hero banner
- featured categories
- featured products
- offer banner
- new arrivals
- footer

### B. Category and search

Left or top filters:

- price
- category
- brand
- stock

### C. Product detail

Blocks:

- product image gallery
- title
- price
- offer
- stock
- variation selector
- quantity selector
- add to cart
- buy now
- description
- reviews

### D. Cart

Blocks:

- cart items
- coupon apply
- subtotal
- shipping
- total
- checkout button

### E. Checkout

Single clear page:

- contact info
- address
- delivery method
- payment method
- order summary
- place order

Rules:

- no unnecessary popups
- show validation under each field
- mobile bottom summary sticky allowed

### F. Order success

Blocks:

- thank you message
- order number
- payment status
- continue shopping
- track order

### G. Customer account

Tabs:

- profile
- addresses
- orders
- wishlist
- returns

## 6.3 Checkout implementation order

1. cart cleanup
2. checkout form cleanup
3. order success page
4. customer account orders
5. return request flow

## 7. Flutter Customer App

## 7.1 Flutter design objective

Flutter app should not try to do owner or merchant work.

It should only do customer commerce:

- browse
- cart
- checkout
- account
- order tracking

## 7.2 Flutter app structure

Tabs:

- Home
- Categories
- Cart
- Orders
- Account

Core screens:

- splash
- store bootstrap
- home
- category listing
- product detail
- cart
- checkout
- login/signup
- order history
- order detail
- profile
- address book

## 7.3 Flutter tenant rules

App must load tenant context first:

- tenant name
- logo
- currency
- payment methods
- active features

The app should never use owner or merchant API.

It should only use:

- `/api/storefront/*`

## 7.4 Flutter implementation order

1. tenant bootstrap and branding
2. auth
3. home and product browsing
4. cart
5. checkout
6. orders
7. push notifications

## 8. Shared UI Rules

These rules should apply everywhere:

- one primary action per section
- form labels always visible
- empty states must explain next action
- error states must show human message
- destructive actions need confirmation
- tables must support mobile fallback
- long forms must be sectioned

## 9. Reuse Strategy

Current codebase already has many reusable modules under:

- `resources/js/components/admin/*`
- `resources/js/components/frontend/*`
- `resources/js/components/layouts/*`

So next UI implementation should not rebuild everything from zero.

Reuse and refactor in this order:

1. keep current table patterns
2. keep current form patterns
3. separate owner routes from legacy admin routes
4. separate merchant routes from legacy admin routes
5. clean frontend checkout and account flow

## 10. Delivery Milestones

## Milestone 1: Owner UI

Done when:

- owner login works
- owner dashboard works
- tenant list/detail works
- plan list/edit works
- subscription list works
- domain approval works

## Milestone 2: Merchant UI

Done when:

- merchant login/register works
- onboarding works
- dashboard works
- products and orders work
- domain page works
- billing page works

## Milestone 3: Storefront Checkout

Done when:

- cart works
- checkout works
- order success works
- account order history works

## Milestone 4: Flutter

Done when:

- tenant bootstrap works
- customer login works
- browse works
- cart works
- checkout works
- order history works

## 11. Final Rule

The design should stay simple.

Do not try to impress with effects.

Impress with:

- clarity
- speed
- consistency
- clean flow
- correct separation of owner, merchant, and customer surfaces

That is the best design direction for this product.
