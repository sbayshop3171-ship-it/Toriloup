# Release Safety Playbook

Use this for every update before live traffic.

## Rules

- Never edit production code by hand on the server.
- Every change must land through git commit, CI, and deploy.
- Never ship destructive migrations with the same release unless rollback-safe.
- Use feature flags for risky or incomplete modules.
- Keep one backup and one rollback point for every deploy.
- Never treat a green build as enough without smoke and tenant-leak checks.

## Safe release order

1. Merge to `main`.
2. Run full CI.
3. Tag the release commit.
4. Take a database backup.
5. Deploy.
6. Run smoke tests.
7. Verify owner, merchant, storefront, billing, and domain flows.
8. Keep rollback ready until the first live session is stable.

## Migration rule

- Expand first.
- Backfill second.
- Switch reads third.
- Contract last.

## Recovery rule

- If a release breaks live traffic, rollback code first.
- Restore the backup only if schema or data changed unsafely.
- Log the commit SHA, backup file, and smoke result for every release.
