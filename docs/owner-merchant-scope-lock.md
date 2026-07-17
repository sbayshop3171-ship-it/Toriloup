# Owner vs Merchant Scope Lock

Date: July 17, 2026
Status: Week 1 ownership freeze

## Final rule

- Owner = platform operator
- Merchant = store operator
- Customer = storefront shopper

## Surface ownership

### Owner-only surface

- Host: `owner.company.com`
- APIs: `api/platform/*`, `api/platform/auth/*`
- Purpose:
  - tenant oversight
  - plan and subscription control
  - provider control
  - domain verification and governance
  - platform KPI visibility

### Merchant-only surface

- Host: `merchant.company.com`
- APIs:
  - `api/merchant/*`
  - `api/merchant/auth/*`
  - transitional legacy workspace: `api/admin/*`
- Purpose:
  - products
  - catalog
  - stock
  - suppliers
  - purchases
  - orders
  - returns
  - daily store settings and operations

### Customer-only surface

- Host: tenant storefront domain or fallback subdomain
- APIs:
  - `api/storefront/*`
  - `api/storefront/auth/*`
  - storefront-facing `api/frontend/*` during transition
- Purpose:
  - browse
  - cart
  - checkout
  - account
  - order history

## Transitional rule for legacy admin routes

The legacy `api/admin/*` surface is still needed for merchant workspace continuity.

From this freeze onward:

- `api/admin/*` is merchant-host only
- owner host must not use `api/admin/*`
- owner workflows must use `api/platform/*`

This keeps merchant operations available while stopping owner-side store-operation overlap.

## Explicitly removed from owner scope

Owner must not use the owner host for:

- product CRUD
- stock updates
- supplier management
- purchase workflow
- coupon management
- promotion management
- daily order processing
- merchant store content editing

## Risk tags to finish later

These are still transitional and should be cleaned further in later phases:

- legacy `api/admin/*` still exists, even though it is now merchant-host fenced
- merchant SPA still contains legacy `/admin/*` route paths internally
- storefront customer auth still bridges through legacy user flows before customer-model end-state cleanup

## Week 1 exit state

This repository should now be interpreted with these rules:

- owner host = platform-only workspace
- merchant host = merchant-only operations workspace
- legacy admin APIs = merchant-host compatibility layer only
- storefront = buyer surface only
