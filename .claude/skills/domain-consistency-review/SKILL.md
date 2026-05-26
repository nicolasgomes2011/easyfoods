---
name: domain-consistency-review
description: >
  Review changes that span multiple domains or could drift out of sync with the rest of EasyFoods — checking
  that enums, scope docs, memory, and code agree, and that a change in one place doesn't break an assumption
  elsewhere. Use when a change touches several models/components at once, when scope and code seem to
  disagree, when adding a status/enum value, or when the user says "isso bate com o resto?", "tá consistente?",
  "alinha com o escopo", "não quebra nada?". Detects divergences (e.g. ready_for_pickup vs ready), orphaned
  references, and assumptions duplicated across components.
---

# domain-consistency-review

Catches drift: code vs scope vs memory, and cross-domain ripple effects.

## When to use
- A change touches multiple domains/components, or adds/renames an enum value or shared concept.
- Scope docs and code appear to disagree.
- Before closing a feature that other domains depend on.

## When NOT to use
- A fully local change confined to one component with no shared concepts.

## Execution workflow
1. Use `docs/memory/architecture/domain-map.md` to list every place the changed concept appears
   (models, enums, components, scope docs, memory).
2. **Source-of-truth check:** for each shared concept, identify the authority (enum for status, scope for
   product intent, code for current behavior) and confirm everything else agrees with it.
3. **Ripple check:** if you changed X, what reads X? (e.g. adding an `OrderStatus` case → kitchen filters,
   dashboard counts, labels/colors, allowedTransitions, status badges, reports).
4. **Scope ↔ code reconciliation:** flag divergences and decide which wins; record the decision (don't
   silently pick). Known example: `ready_for_pickup` vs scope's `ready`; no `ready→completed` path.
5. **Duplicated assumptions:** the same rule re-implemented in multiple components (smell → candidate for
   `extract-pattern` / an Action).
6. **Memory freshness:** update any memory file the change makes stale (`last_verified`, content).

## Architecture concerns
- One source of truth per concept; everything else derives from it.
- Shared rules belong in one place (enum/Action/Service), not copy-pasted.

## Anti-patterns
- Adding an enum case but not updating the consumers (filters, labels, transitions).
- "Fixing" code to match stale scope (or vice-versa) without recording the decision.
- Leaving memory describing the old behavior.

## Expected output
A consistency matrix: concept → authority → consumers → status (✅ aligned / ❌ drift) with the exact files
to update, plus any decision that needs the user's call.

## Validation checklist
- [ ] All consumers of the changed concept enumerated
- [ ] Each agrees with its source of truth
- [ ] Scope/code divergences flagged + decided (not silent)
- [ ] Duplicated rules noted for extraction
- [ ] Stale memory updated

## EasyFoods example
Adding a `Rejected` order status: consumers = `OrderStatus` (case + label + color + allowedTransitions),
kitchen/orders filters, dashboard counts, `orders/show` badge, `OrderPolicy`, scope docs, and
`order-state-machine.md` memory. Review confirms all updated, and asks the user whether "reject" is distinct
from "cancel at pending" (scope currently models rejection as `pending → canceled`). Decision recorded as an ADR.
