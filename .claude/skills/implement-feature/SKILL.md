---
name: implement-feature
description: >
  End-to-end orchestrator for building a feature in EasyFoods. Use for any "implementa X", "cria X",
  "adiciona X", "constrói X", "faz o X funcionar" request that involves real code — e.g. "implementa order
  cancellation", "cria employee management", "adiciona toggle de disponibilidade do produto". Composes the
  whole pipeline: loads context (bootstrap + memory), runs the relevant architectural reviews
  (state-machine, multi-tenant, database, eta, ux), proposes migrations / Actions / Services / components,
  and produces a plan. PLAN-AND-APPROVE: it always presents the plan and STOPS for user approval before
  writing any code. After approval and implementation, it saves new decisions to memory.
---

# implement-feature

The master workflow that turns a feature request into reviewed, tested code — gated by user approval.

> **Autonomy mode: PLAN-AND-APPROVE (locked).** Always present the plan and wait for explicit approval
> before writing code. Never start editing files in the planning phase. (Decision AD-007.)

## When to use
- Any request to build/add/implement real functionality in the codebase.

## When NOT to use
- Design-only questions → `feature-planning`. Bug fixing → `bug-investigation`. Refactor → `refactor-review`.
- Trivial one-liners → just edit directly.
- Config/settings.json changes → `update-config`.

## Execution workflow

### Phase A — Context (no code)
1. Run **`bootstrap-project`** (if not already done this session): phase, WIP, architecture, risks.
2. Run **`load-memory`** for the touched domain: pull `business-rules/*` and `architecture/domain-map.md`
   slices relevant to the feature. Keep it lean (context economy).

### Phase B — Impact analysis & reviews (no code)
3. **Detect domain & entities** via `docs/memory/architecture/domain-map.md`. List models/tables touched + new.
4. Run the relevant reviews and fold their findings into the plan:
   - Order status involved → **`state-machine-review`** (enum transition exists? history write? milestone ts?).
   - New/changed queries or tables → **`multi-tenant-review`** (scoped by `restaurant_id`?).
   - Migrations/schema → **`database-review`** (reversible, indexed, snapshot/integrity safe).
   - Prep/transit/countdown → **`eta-engine-review`**.
   - Kitchen/counter/operational UI → **`ux-operational-review`**.
   - Cross-domain touch → **`domain-consistency-review`**.
5. **Security pass.** Tenant isolation, authorization (Policy), server-side validation (never trust client
   totals), frozen snapshots, no secrets in code. For a full diff-level audit, recommend the built-in
   `/security-review` after coding.

### Phase C — Plan (STOP HERE)
6. Produce the plan using `feature-planning`'s template: scope+acceptance, impact, proposed structure
   (migrations / enums / `app/Actions/` — create the layer if absent / policy / components / routes /
   events-jobs), ordered testable steps, test strategy + Definition of Done, open questions.
7. **Present the plan and ask for approval. Do not write any code yet.** If the plan touches schema, the
   order state machine, or tenant scoping, call those out explicitly as the riskiest steps.

### Phase D — Implement (only after approval)
8. Execute the steps in small increments, following `patterns/laravel-livewire-conventions.md`:
   - Migrations first (reversible). Enums next. Then `app/Actions/` for business logic. Then Policy.
   - Then Livewire/Volt component + route in `default_routes_web.php`. Events/Jobs if needed.
   - Every order transition: validate via `OrderStatus::canTransitionTo`, append `order_status_histories`,
     set milestone timestamp, scope by restaurant.
9. **Test for real** (Definition of Done from `coding-standards`): `php artisan view:clear`, hit the real
   route, assert 200 + expected content + no `Undefined`/exceptions. Add feature + component tests.

### Phase E — Persist
10. Run **`save-memory`**: record any new architectural decision (ADR), new business rule, resolved/!new
    bug, and update `active-work/current.md`. Update `bugs/known-issues.md` if you fixed or found one.

## Architecture concerns (carry through every feature)
Tenant isolation · order state machine via enum · frozen price/product snapshots · append-only history ·
payment/order independence · Actions over fat components · reversible migrations.

## Anti-patterns
- Writing code before approval (violates the locked mode).
- Adding a transition in code that isn't in the `OrderStatus` enum.
- New queries/tables without `restaurant_id` scoping.
- Recalculating order totals from live product prices (must use frozen snapshots).
- Skipping the real-URL test and declaring "pronto".
- Building beyond the request ("while I'm here…").

## Expected outputs
1. A context briefing (Phase A). 2. A reviewed plan that STOPS for approval (Phase C). 3. After approval:
incremental diffs + tests (Phase D). 4. Memory updates (Phase E).

## Validation checklist
- [ ] Context loaded (bootstrap + relevant memory)
- [ ] State-machine / tenant / database / (eta|ux) reviews run as applicable
- [ ] Plan presented and **explicitly approved** before any edit
- [ ] Transitions validated via enum + history + milestone + restaurant scope
- [ ] Server-side validation + Policy authorization in place
- [ ] Real-URL test passed; feature + component tests added
- [ ] Memory updated (decisions / bugs / active-work)

## EasyFoods example — "implementa order cancellation"
- **A:** bootstrap → Fase 4; no `app/Actions/` yet; load `order-state-machine` + `data-integrity` memory.
- **B:** state-machine-review → `Confirmed/Pending/InPreparation/ReadyForPickup/OutForDelivery →
  Canceled` are valid in the enum; cancellation needs a **mandatory reason** + `order_status_histories`
  row + `canceled_at`. multi-tenant-review → scope the order lookup by restaurant. database-review → add a
  `cancellation_reason` column (or a reason on the history row); reversible migration. security → only
  manager/admin via `OrderPolicy`.
- **C:** Plan: migration (reason) → `app/Actions/Orders/CancelOrder` (validate transition, write history,
  set `canceled_at`, store reason) → `OrderPolicy::cancel` → wire button in `orders/show` + kitchen →
  tests. **Present, await approval.**
- **D (post-approval):** implement + test the real `/admin/orders/{id}` action.
- **E:** save-memory → ADR "CancelOrder action establishes the app/Actions/Orders pattern"; update active-work.
