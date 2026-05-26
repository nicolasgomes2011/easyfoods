---
name: refactor-review
description: >
  Review or guide a refactor in EasyFoods so behavior is preserved while structure improves. Use when the
  user says "refatora", "limpa esse código", "extrai isso", "reduz acoplamento", "tira lógica do componente",
  "deixa testável", or when moving business logic out of Livewire/Blade into Actions/Services. Checks that
  behavior is unchanged (tests/real-URL), coupling is reduced, no over-fetching, no premature abstraction,
  and that order/tenant/snapshot invariants still hold after the move.
---

# refactor-review

Keeps refactors safe: same behavior, better structure, invariants intact.

## When to use
- Restructuring existing code: extracting logic, reducing coupling, introducing the `app/Actions/` layer,
  splitting fat components, removing duplication.

## When NOT to use
- New behavior (that's `implement-feature`). Bug fixing (that's `bug-investigation`).

## Execution workflow
1. **Pin current behavior first.** Identify how it's verified (existing tests, or write a characterization
   test / capture the real-URL output) BEFORE changing anything. Per the Definition of Done, behavior is
   proven by hitting the real surface.
2. **Target structure.** Usually: move business logic out of Livewire/Blade into an Action/Service; keep the
   component thin (UI state + calling the action). Follow `patterns/laravel-livewire-conventions.md`.
3. **Invariants survive the move.** Order transitions still validate via the enum + write history; queries
   stay restaurant-scoped; frozen snapshots untouched; payment/order independence preserved.
4. **Over-fetching / N+1.** Eager-load what loops use (`->with(...)`); don't load columns/relations you
   don't need; don't replace a scoped query with an unscoped one.
5. **Coupling.** Fewer cross-component reach-ins; depend on Actions/Services, not other components' internals.
6. **No premature abstraction.** Don't introduce indirection for a single caller; 3 similar usages is the
   threshold (hand off to `extract-pattern` if a real pattern emerges).
7. **Re-test the real surface.** `php artisan view:clear`, hit the route, assert 200 + content unchanged.

## Architecture concerns
- Refactor in small, independently verifiable steps; commit-sized increments.
- Prefer moving logic toward the layer it belongs in over clever in-place tricks.

## Anti-patterns
- Refactoring without a behavior baseline.
- "While I'm here" scope creep / adding features.
- Turning a scoped query unscoped during the move (tenant regression).
- Premature generic abstractions, indirection, or config flags.
- Declaring done from green component tests alone (route untested).

## Expected output
A before/after structure summary, the behavior-preservation evidence (test or real-URL), and a verdict that
each invariant still holds. Concrete diffs in small steps.

## Validation checklist
- [ ] Behavior baseline captured before changes
- [ ] Logic moved to the right layer; component thinner
- [ ] Order/tenant/snapshot invariants intact
- [ ] No new N+1 / over-fetch / unscoped query
- [ ] No premature abstraction or scope creep
- [ ] Real-URL re-test passes (behavior unchanged)

## EasyFoods example
Refactoring `Dashboard`: its `alerts()`, raw joins, and counts are fine to extract into a
`DashboardMetrics` service for testability — but first capture the current `/dashboard` output. During the
move, also fix the latent issues only if intended (don't silently change `alerts()` behavior; that's a bug
fix → `bug-investigation`). Ensure the extracted queries gain restaurant scoping rather than staying
unscoped (coordinate with `multi-tenant-review`).
