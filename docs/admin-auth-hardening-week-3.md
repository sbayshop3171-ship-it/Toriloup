# Week 3 Admin Auth Hardening

Date: July 17, 2026
Scope: owner and merchant admin surfaces only

## Final strategy

- owner auth uses `owner.company.com` only
- merchant auth uses `merchant.company.com` only
- admin surfaces use bearer tokens, not shared cross-subdomain cookies
- each admin token must carry a surface ability:
  - `surface:platform`
  - `surface:merchant`
- legacy `/api/auth/*` endpoints are fenced off on admin hosts
- password reset must complete on the same intended surface

## Why token-first is the right V1 choice

- it avoids fragile shared-cookie behavior across `owner.company.com` and `merchant.company.com`
- it keeps owner and merchant sessions intentionally separate
- it matches the current SPA auth architecture already used in the repo
- it makes wrong-host and wrong-surface protection easier to enforce

## Required behavior

- owner accounts cannot log in or reset password through merchant auth routes
- merchant accounts cannot log in or reset password through owner auth routes
- customer/storefront auth remains separate from admin auth
- reset completion must issue a fresh surface-specific token
- verified reset challenges must exist before password update when verification is enabled

## Operational rule

Do not introduce cross-subdomain shared admin cookies unless the full auth model is redesigned intentionally.
