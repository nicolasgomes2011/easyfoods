---
name: state-machine-review
description: >
  Review any change that touches the order lifecycle / status for correctness against the EasyFoods order
  state machine. Use when the work involves order status, transitions, confirm/cancel/reject, mark
  in_preparation / ready / out_for_delivery / delivered / completed, kitchen actions, status history, or
  the OrderStatus enum. Triggers on phrases like "muda o status", "transição de pedido", "cancelar",
  "confirmar pedido", "marcar como pronto", "fluxo do pedido". Checks that transitions are valid in the
  enum, that history is appended, milestone timestamps are set, actor permissions hold, and payment state
  stays independent.
---

# state-machine-review

Guards the integrity of the order lifecycle. The `OrderStatus` enum is the source of truth.

## When to use
- Implementing/reviewing any order status change, kitchen action, cancellation, or status-driven UI.
- Inside `implement-feature` whenever the touched domain is Orders/Kitchen.

## When NOT to use
- Changes with no order-status dimension (e.g. catalog image upload). Use the relevant review instead.

## Execution workflow
1. Read `docs/memory/business-rules/order-state-machine.md` and verify it against
   `app/Enums/OrderStatus.php` (authority: code — re-check the enum; it may have changed).
2. For each transition in the change, confirm it's allowed: `$from->canTransitionTo($to)` must be true.
   If the desired transition isn't in `allowedTransitions()`, the fix is to add it to the **enum first**,
   not to bypass the check.
3. Confirm side effects are complete for every transition:
   - Append a row to `order_status_histories` (actor + `changed_at`) — append-only.
   - Set the milestone timestamp (`confirmed_at`, `ready_at`, `delivered_at`, `completed_at`, `canceled_at`).
   - Cancellation captures a **mandatory reason**.
4. Confirm **actor permissions** (who may trigger it) via `OrderPolicy` / role middleware.
5. Confirm **payment independence**: order transition must not mutate `payment_status` implicitly.
6. Confirm the transition logic lives in an Action (`app/Actions/Orders/...`), not in Blade/Livewire hooks.
7. Watch the known divergences (`ready_for_pickup` vs `ready`; no `ready→completed`) — flag if relevant.

## Architecture concerns
- Single source of truth = enum. No magic status strings scattered around.
- Idempotency: re-triggering a transition that already happened shouldn't double-write history.
- Concurrency: two staff acting on the same order — guard with a fresh status check inside the Action
  (re-read status in a transaction before transitioning).

## Anti-patterns
- `$order->status = OrderStatus::X` without `canTransitionTo`.
- Transition without a history row or milestone timestamp.
- Cancel without a reason.
- Coupling payment status to order status.
- Adding a new status path only in code/UI but not in the enum.

## Expected output
A verdict per transition: ✅ valid + complete, or ❌ with the exact missing piece (enum entry, history
write, timestamp, permission, reason). Concrete file/line references.

## Validation checklist
- [ ] Every transition allowed by the enum
- [ ] History appended (actor + changed_at)
- [ ] Milestone timestamp set
- [ ] Cancellation reason captured
- [ ] Actor authorized via Policy/role
- [ ] Payment state untouched
- [ ] Logic in an Action, not the UI
- [ ] Idempotent + concurrency-safe

## EasyFoods example
Reviewing "marcar pedido como pronto" in the kitchen: transition `InPreparation → ReadyForPickup` is valid
in the enum ✅; but the draft set `ready_at` and forgot the `order_status_histories` row ❌ and ran
`Order::find($id)` without restaurant scope ❌ (defer to `multi-tenant-review`). Fix: wrap in
`app/Actions/Orders/MarkOrderReady` that validates the transition, writes history, sets `ready_at`, scoped
by restaurant; authorize via `OrderPolicy`.
