# ETA Intelligence Engine — Scope

How EasyFoods produces time estimates — and why they are accurate over time.

---

## The Problem with Static Times

Most systems ask the restaurant to type "30 minutes" per product and display that number forever.
This is wrong because:

- A burger takes 8 minutes at 3pm and 22 minutes at 12:30pm on a Saturday
- When the kitchen has 12 simultaneous orders, every estimate is optimistic
- No restaurant can maintain accurate manual estimates as demand changes

EasyFoods treats time estimates as a live calculation, not a hardcoded value.

---

## Two Estimates

### 1. Kitchen Prep Time
How long until the order is ready for pickup or delivery.

### 2. Delivery Transit Time
How long until the order reaches the customer after leaving the restaurant.

---

## Prep Time Calculation

**Inputs:**
- `base_prep_time`: the static number set by the restaurant per product (starting point only)
- `historical_avg_prep_time`: rolling average of actual prep durations per product
- `time_band`: what part of the day it is (e.g., breakfast, lunch, dinner, late night)
- `kitchen_load`: how many orders are currently `in_preparation`

**Formula (conceptual):**
```
adjusted_prep = historical_avg * kitchen_load_factor(kitchen_load) * time_band_factor(time_band)
```

**Kitchen load factor:**
| Active Orders | Factor |
|--------------|--------|
| 0–2 | 1.0 (no adjustment) |
| 3–5 | 1.2 |
| 6–9 | 1.5 |
| 10+ | 1.8 |

**Data collection:**
- Every time an order transitions from `in_preparation` to `ready`, the actual duration is recorded
- Stored per: product, time band, day of week
- 30-day rolling window, recent data weighted more heavily
- After 7+ data points per product per band, the historical average replaces the base time as primary input

---

## Delivery Time Calculation

**Inputs:**
- `distance_km`: estimated distance from restaurant to delivery address
- `active_deliveries`: how many deliveries are currently in progress (demand)
- `time_of_day`: rush hour vs off-peak
- `historical_transit_avg`: rolling average of actual delivery durations by distance bucket

**Formula (conceptual):**
```
delivery_eta = historical_transit_avg(distance_bucket) * demand_factor * time_factor
```

**Distance buckets:**
- 0–2km
- 2–5km
- 5–10km
- 10km+

**Future signal: weather**
When external weather data is integrated, a rain condition multiplier is applied.

---

## ETA in the Customer Experience

**What the customer sees:**
- Estimated completion time shown as a clock time: "Pronto às 12:34"
- A countdown that always moves forward in real time
- If the estimate changes, the timer adjusts — with a human-readable explanation

**Rules for customer-facing ETA:**
- The timer must NEVER freeze
- The timer must NEVER jump backward (earlier) without explanation
- Adjustments forward (later) are allowed — shown as "levando mais tempo que o esperado"
- Adjustments should not happen more than twice per order (to avoid distrust)

---

## System Requirements

- Every order completion records actual prep duration to the history table
- Every delivery completion records actual transit duration to the history table
- ETA recalculation is triggered on: order confirmed, order enters in_preparation, kitchen load changes significantly
- ETA data is never exposed raw to customers — only the resulting time shown as a clock value
