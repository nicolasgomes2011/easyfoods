---
name: eta-engine-review
description: >
  Review or design anything touching time estimates in EasyFoods — kitchen prep time, delivery transit
  time, countdowns, "Pronto às HH:MM", overdue indicators, and the ETA learning data. Use when the user
  says "melhora ETA", "tempo de preparo", "countdown", "estimativa", "previsão de entrega", "quando fica
  pronto", or touches prep/transit history. Verifies the learning data is captured correctly (append-only),
  the load/time-band factors are applied, and the customer-facing rules hold (timer never freezes or jumps
  backward, max 2 forward adjustments, clock-time display).
---

# eta-engine-review

Protects the honesty and correctness of time estimates. ETA is Phase 7 (not built) — this review keeps any
ETA-related work coherent with the target design and guards the data that will feed it.

## When to use
- Building/altering prep or delivery time logic, countdowns, or the history tables that feed ETA.
- Recording actual durations on status transitions (the raw signal).

## When NOT to use
- Order status mechanics unrelated to timing (use `state-machine-review`).

## Execution workflow
1. Read `docs/memory/business-rules/eta-engine.md` (authority: scope — not yet implemented).
2. **Data capture:** on `InPreparation → ReadyForPickup`, is the actual duration recorded to
   `product_prep_histories` (product, time_band, day_of_week, minutes, recorded_at), append-only? On delivery
   completion, `delivery_transit_histories` (distance_bucket, minutes)?
3. **Source signal:** durations derived from milestone timestamps (`created_at`/`confirmed_at` → `ready_at`;
   pickup → `delivered_at`), not from `updated_at` (that bug exists elsewhere — don't repeat it).
4. **Formula factors:** prep uses `historical_avg * kitchen_load_factor * time_band_factor` (load: 0–2 ×1.0,
   3–5 ×1.2, 6–9 ×1.5, 10+ ×1.8); delivery uses distance buckets (0–2/2–5/5–10/10+ km) × demand × time.
5. **Customer-facing rules:** clock-time display ("Pronto às 12:34"); timer never freezes; never jumps
   backward without explanation; forward adjustments allowed with human text; max 2 adjustments/order; raw
   inputs never exposed.
6. **Cold-start:** before 7+ data points per product/band, fall back to the product `base_prep_time`.

## Architecture concerns
- ETA recalculation triggers: order confirmed, enters in_preparation, significant kitchen-load change.
- Keep computation in a Service/Action, cache per order to avoid recompute storms.
- History is append-only (never update past rows).

## Anti-patterns
- Static "30 min" hardcoded estimates (the whole point is to avoid this).
- Deriving durations from `updated_at`.
- A timer that can freeze or jump earlier.
- Exposing raw factors/inputs to the customer.
- Updating historical rows instead of appending.

## Expected output
Verdict on data capture correctness + formula application + customer-rule compliance, with concrete fixes.
If ETA isn't built yet, confirm the change lays correct foundations (timestamps/history) rather than
shortcuts that will need ripping out.

## Validation checklist
- [ ] Durations captured append-only from milestone timestamps
- [ ] Load + time-band (or distance + demand) factors applied
- [ ] Cold-start fallback to base_prep_time
- [ ] Clock-time display; no freeze; no backward jump; ≤2 adjustments
- [ ] Raw inputs not exposed to customer
- [ ] Recompute triggers + caching considered

## EasyFoods example
"melhora ETA" today: the foundation is missing — `product_prep_histories` isn't written. First correct step
is to record actual prep duration when the kitchen marks ready (inside `MarkOrderReady`, append to the
history table using `created_at → ready_at`). Don't yet build the full adaptive formula; build the honest
data pipeline first, then layer the factors. Flag that the kitchen card's `diffInMinutes(now())` is elapsed
time, not an ETA.
