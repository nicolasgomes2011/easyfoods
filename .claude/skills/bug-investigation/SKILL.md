---
name: bug-investigation
description: >
  Systematically investigate a defect in EasyFoods before fixing it — reproduce, locate root cause, and
  verify the fix on the real surface. Use when the user reports something broken: "tá quebrado", "deu erro",
  "não funciona", "bug em X", "undefined variable", "tela em branco", "500", "não salva", "comportamento
  estranho". Checks known issues first, reproduces against the real route, isolates root cause (not just
  symptom), and confirms order/tenant/snapshot invariants weren't the cause. Pairs with debug-livewire for
  Livewire-specific errors.
---

# bug-investigation

Find the real root cause, fix it once, prove it on the real surface. No symptom-patching.

## When to use
- Any reported defect, error, or unexpected behavior.

## When NOT to use
- New behavior requests (`implement-feature`). Pure structure changes (`refactor-review`).

## Execution workflow
1. **Check known issues.** Read `docs/memory/bugs/known-issues.md` — it may already be catalogued (e.g.
   BUG-001 alerts, DEBT-004 duplicate routes). Don't re-discover.
2. **Reproduce.** Establish the exact trigger: route, role/actor, data state, steps. Per the Definition of
   Done, reproduce on the **real surface** (`php artisan view:clear`, hit the URL with a session cookie /
   `actingAs`), not only `Livewire::test()`.
3. **Localize.** Read the involved component/model/route. For Livewire blank screens / undefined vars / stale
   state / polling issues, defer to `debug-livewire`.
4. **Root cause, not symptom.** Ask why it happens. Common EasyFoods traps:
   - Route not loaded (legacy `routes/web.php` vs `default_routes_web.php`).
   - `updated_at` used as a status-change signal (BUG-001 pattern).
   - Unscoped query returning unexpected rows (tenant) — but with one restaurant, more likely a status/enum mismatch.
   - Enum value mismatch (`ready` vs `ready_for_pickup`), missing cast, stale compiled blade.
5. **Assess blast radius.** Does the same root cause exist elsewhere? (e.g. the `updated_at` proxy may repeat.)
6. **Fix minimally** at the root. Don't refactor surrounding code (that's `refactor-review`).
7. **Verify on the real surface.** Re-run the reproduction; assert it's gone + no regression nearby.
8. **Record.** Update `bugs/known-issues.md` (move to Resolved with the commit) via `save-memory`.

## Architecture concerns
- A fix touching order status → run `state-machine-review`. Touching queries → `multi-tenant-review`.
- Don't bypass safety (no `--no-verify`, no disabling validation) to make an error "go away".

## Anti-patterns
- Fixing the symptom (hide the error) instead of the cause.
- Trusting `Livewire::test()` green while the real route still 500s.
- Skipping `view:clear` and chasing a stale-blade ghost.
- Silent broad refactor under the guise of a fix.

## Expected output
Reproduction steps → root cause (with file/line) → blast radius → minimal fix → real-surface verification →
memory update. State evidence, not confidence.

## Validation checklist
- [ ] Known-issues checked first
- [ ] Reproduced on the real surface
- [ ] Root cause identified (not symptom)
- [ ] Blast radius assessed
- [ ] Minimal fix at the root
- [ ] Verified on the real URL; no regression
- [ ] known-issues.md updated

## EasyFoods example
"Dashboard mostra pedido atrasado que não está atrasado." Known-issues → BUG-001. Root cause:
`alerts()` filters `where('updated_at', '<=', now()->subMinutes(30))`; `updated_at` bumps on any write, so a
recently-edited order falsely looks idle (or a truly stalled one is hidden after an unrelated write). Fix:
derive "minutes in preparation" from the `InPreparation` `order_status_histories` row (or a
`status_changed_at`). Blast radius: any other code using `updated_at` as a status proxy. Verify on
`/dashboard` with seeded states; update known-issues → Resolved.
