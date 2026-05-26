---
name: multi-tenant-review
description: >
  Review any query, model, migration, or component for multi-tenant isolation in EasyFoods — i.e. that
  data is correctly scoped by restaurant_id and cannot leak across restaurants. Use when the work adds or
  changes Eloquent queries, Livewire computed properties, reports, dashboards, or any new table, and when
  the user says "corrige tenant leakage", "vazamento de dados", "escopo de restaurante", "isolamento",
  "multi-loja", or "restaurant_id". Flags unscoped queries, missing restaurant_id columns/foreign keys,
  and cross-tenant exposure; recommends a global-scope trait or explicit forRestaurant scoping.
---

# multi-tenant-review

Ensures one restaurant can never see another's data. Critical before a second restaurant exists.

## When to use
- New/changed queries, components, reports, dashboards, or migrations.
- Any request mentioning leakage, scoping, isolation, or multi-restaurant.

## When NOT to use
- Truly global/system tables with no restaurant dimension (e.g. framework tables, jobs). Note why it's exempt.

## Execution workflow
1. Read `docs/memory/business-rules/tenant-isolation.md`. Verify current reality against the code
   (the gap may have been fixed — re-check whether a global scope now exists).
2. For each query touched, ask: **is it scoped to `auth()->user()->restaurant_id`?** Either via a global
   scope, `->forRestaurant($id)`, or an explicit `where('restaurant_id', …)`. If not → finding.
3. For each new table/migration: does it carry `restaurant_id` (FK, indexed) where it belongs to a restaurant?
4. For each new model: should it use the `BelongsToRestaurant` trait (global scope + auto-fill on create)?
5. Check writes too: on create, is `restaurant_id` set automatically (not trusted from client input)?
6. Check escape hatches: any intentional cross-tenant query (admin/superadmin) must be explicit and authorized.
7. Report findings ranked by exposure (read leak > stale count > cosmetic).

## Architecture concerns
- Prefer a **global scope trait** so isolation is the default and can't be forgotten; document the
  `withoutGlobalScope` escape hatch for legitimate cross-tenant reads.
- Raw `DB::table()->join()` (e.g. Dashboard aggregates) bypasses Eloquent scopes → must add explicit
  `where('orders.restaurant_id', …)`.
- Tenancy is a security boundary, not a convenience — treat leaks as high severity.

## Anti-patterns
- `Model::all()` / `Model::query()` with no restaurant filter in tenant-bound data.
- Trusting a `restaurant_id` coming from the request/client.
- Raw query builder joins without an explicit restaurant filter.
- Relying on "there's only one restaurant" as a permanent excuse.

## Expected output
A list of unscoped access points with file/line, severity, and the precise fix (trait, scope call, or
migration column). A one-line overall verdict: safe / leaks-found.

## Validation checklist
- [ ] Every tenant-bound read scoped by restaurant_id
- [ ] Every tenant-bound table has restaurant_id (FK + index)
- [ ] Writes set restaurant_id server-side (not from client)
- [ ] Raw DB::table joins filtered explicitly
- [ ] Cross-tenant reads are explicit + authorized

## EasyFoods example
Reviewing the kitchen Volt: `Order::whereIn('status', […])->with('items')->get()` has no restaurant filter
❌. With one restaurant it "works", but it's a latent cross-tenant leak. Fix now or track: add
`->forRestaurant(auth()->user()->restaurant_id)` (or adopt the global-scope trait across order/catalog
models). Same finding applies to `Dashboard` computeds and the raw `DB::table('order_items')->join('orders')`
aggregates (filter `orders.restaurant_id`).
