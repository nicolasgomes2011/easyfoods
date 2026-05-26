---
name: ux-operational-review
description: >
  Review operational UI for the restaurant's busiest users — kitchen and counter staff — and the customer's
  speed/clarity. Use when building or changing the kitchen panel, order queue, dashboard, table grid,
  checkout, or order tracking, and when the user says "melhora a UX", "deixa mais rápido pra cozinha",
  "tela da cozinha", "fluxo de balcão", "fácil de usar", "mobile". Checks action speed (1–2 taps), legibility
  under pressure, obvious status, empty/loading/error states, mobile-first layout, and honest realtime
  feedback — not visual polish for its own sake.
---

# ux-operational-review

Optimizes for real operational usage: kitchen/counter under pressure, customers with zero friction tolerance.

## When to use
- Kitchen panel, order queue, dashboard, table grid, checkout, tracking, or any staff-facing action UI.

## When NOT to use
- Pure backend/data changes with no UI surface.

## Execution workflow
1. Read `docs/scope/restaurant-side.md` / `customer-side.md` for the actor's needs + `patterns/laravel-livewire-conventions.md`.
2. **Action speed:** primary action reachable in 1–2 taps; no hunting. Kitchen "mark ready" / counter
   "confirm" must be one obvious control (today kitchen shows "ações em breve" — flag missing actions).
3. **Legibility under pressure:** large text, high contrast, obvious status colors (use `OrderStatus::color()`).
   Kitchen works in a loud, fast room.
4. **Status obviousness:** current state and urgency visible at a glance (e.g. order turns orange/red as it
   ages). No hidden state changes.
5. **State coverage:** empty, loading, and error states all designed (empty-state is a Phase 0 requirement).
6. **Mobile-first:** customer + driver are on phones; responsive grids; thumb-reachable actions.
7. **Honest realtime:** polling/broadcast feedback present ("atualiza a cada 30s"); a timer never silently
   freezes — show a pulsing indicator if unknown (see `eta-engine-review`).
8. **No friction for customers:** ≤ target taps to add a product; checkout minimal; totals always visible.

## Architecture concerns
- Keep UI state in Livewire/Alpine light; business rules stay in Actions.
- Optimistic UI must reconcile with server truth (don't show a transition that didn't persist).

## Anti-patterns
- Multi-step flows for a single operational action.
- Tiny text / low contrast on the kitchen screen.
- Missing empty/error states.
- A "save" button with no operational meaning instead of a named action ("Confirmar", "Marcar pronto").
- Freezing timers or stale counts with no refresh signal.

## Expected output
A prioritized UX punch list (blocking → nice-to-have) tied to the actor's real workflow, with concrete
component/markup suggestions consistent with the dark-theme Tailwind conventions.

## Validation checklist
- [ ] Primary action in 1–2 taps, obviously labeled
- [ ] Legible under pressure (size/contrast/status color)
- [ ] State visible; no hidden changes
- [ ] Empty + loading + error states present
- [ ] Mobile-first where the actor is on a phone
- [ ] Honest realtime feedback (no silent freeze)

## EasyFoods example
Kitchen panel review: queue cards, aging color, and 30s poll are good ✅; but the only control is "ações em
breve" ❌ — staff can't advance an order. Add one big primary button per card ("Iniciar preparo" /
"Marcar pronto") wired to the transition Action, with the button reflecting the allowed next state from
`OrderStatus::allowedTransitions()`. Keep it one tap; show a brief disabled/loading state during the request.
