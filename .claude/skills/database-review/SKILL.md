---
name: database-review
description: >
  Review database schema, migrations, models, and relationships for the EasyFoods ordering platform. Use
  when creating or changing migrations, adding entities/columns/indexes, defining Eloquent relationships,
  or when the user says "migration", "cria tabela", "modela X", "schema", "índice", "relacionamento",
  "constraint". Checks reversibility, indexing of query paths, foreign keys, restaurant_id presence, enum
  casts, frozen-snapshot columns on orders, append-only history tables, and SQLite/MySQL portability.
---

# database-review

Keeps the schema stable, portable, and integrity-safe.

## When to use
- Any migration or schema change; new entity/column/index; relationship changes.
- Inside `implement-feature` when tables change.

## When NOT to use
- Pure UI/logic changes that don't touch the schema.

## Execution workflow
1. Read `docs/memory/business-rules/data-integrity.md` + `architecture/domain-map.md` for where the entity fits.
2. **Reversibility:** migration has a correct `down()` (or is safely reversible). No data-destroying change
   without a guard.
3. **Portability:** no MySQL-only SQL; use the schema builder (dev is SQLite). Avoid raw `ALTER` that SQLite
   can't run.
4. **Tenancy:** tenant-bound table includes `restaurant_id` (FK + index). Defer to `multi-tenant-review`.
5. **Indexing:** index the columns used in real query paths (status, restaurant_id, created_at, foreign keys,
   `whereDate` columns). Check the actual queries (Dashboard/kitchen filter by status + dates).
6. **Integrity & snapshots:** order item tables carry frozen columns (`product_name`, `unit_price`,
   `addon_price_delta`, `total_item_price`); history tables are append-only (no updates). Enum-backed columns
   use string values matching the enum; cast in the model.
7. **Money:** decimals as `decimal(x,2)`, cast `decimal:2`.
8. **FKs & cascades:** define foreign keys; choose cascade vs restrict deliberately (e.g. can't delete a
   product referenced by open orders → restrict / soft handling).
9. **Naming:** snake_case tables (plural), follow existing names (`order_status_histories`, `addon_groups`).

## Architecture concerns
- Prefer additive migrations; avoid editing historical migrations already run elsewhere.
- Large backfills run in a job/command, not inline in the migration.
- Keep models thin: relationships + casts + scopes (see conventions).

## Anti-patterns
- Migration with no/incorrect `down()`.
- Missing index on a frequently filtered column.
- Tenant-bound table without `restaurant_id`.
- Storing recalculable order totals as live FKs instead of frozen snapshots.
- `float` for money.
- MySQL-specific SQL that breaks SQLite dev.

## Expected output
Table-by-table / migration-by-migration verdict: columns, types, FKs, indexes, casts, integrity notes, and
explicit reversibility check. Concrete corrections.

## Validation checklist
- [ ] Reversible (`down()` correct)
- [ ] SQLite/MySQL portable
- [ ] restaurant_id present + indexed (if tenant-bound)
- [ ] Real query-path columns indexed
- [ ] Frozen snapshots / append-only respected
- [ ] Money as decimal; enums cast
- [ ] FKs + cascade policy deliberate
- [ ] Naming matches existing schema

## EasyFoods example
Adding cancellation reason: prefer a column on the existing `order_status_histories` row
(`reason nullable`) over a new table — it's append-only and already records actor + `changed_at`. If instead
adding `orders.cancellation_reason`, write a reversible migration, no index needed (not a query path), and
ensure the `CancelOrder` action populates it. Confirm SQLite can run the `ALTER` (adding a nullable column is fine).
