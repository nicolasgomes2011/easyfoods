# ETA Engine

**Status:** 🔴 Not Started
**Phase:** 7 — ETA Intelligence

## Description
The ETA engine produces time estimates for two things:
1. How long until an order is ready (kitchen prep time)
2. How long until a delivery arrives (transit time)

Both estimates start from a base value but adapt using historical data.
The static number a restaurant enters when creating a product is only the starting point.

## Prep Time Estimation

### Inputs
- Base prep time per product (set in catalog)
- Number of items in the order
- Current kitchen load (how many orders are actively being prepared)
- Historical average completion time for this product (rolling window)

### Calculation
- `estimated_prep = historical_avg * kitchen_load_factor`
- If no history yet, fall back to base prep time
- Kitchen load factor increases as more active orders pile up
- Example: base 10 min, 3 concurrent orders → estimated 13 min

### History Collection
- Every time an order moves from `in_preparation` to `ready`, record actual duration
- Store per product, per time-of-day band (breakfast, lunch, dinner), per day-of-week
- Use rolling 30-day average, weighted toward recent data

## Delivery Time Estimation

### Inputs
- Distance from restaurant to delivery address (calculated via routing)
- Current number of active deliveries (demand)
- Time of day
- Future: weather signal (rainy = longer ETAs)

### Calculation
- `delivery_eta = base_transit_time * demand_factor * conditions_factor`
- Base transit time derived from historical deliveries on the same route or distance bucket

### Estimate Updates
- ETA is recalculated whenever order status changes
- ETA is recalculated if kitchen load changes significantly after an order is already in queue
- Customer always sees the most recent estimate

## Tasks

### Prep Time Engine
- [ ] Collect actual prep durations per order per product
- [ ] Compute rolling historical average per product, per time band
- [ ] Apply kitchen load factor (count of concurrent `in_preparation` orders)
- [ ] Return adjusted estimate at order creation time
- [ ] Recalculate if load changes while order is pending

### Delivery Time Engine
- [ ] Calculate or estimate distance per delivery order
- [ ] Track historical delivery durations by distance bucket
- [ ] Apply demand factor based on concurrent active deliveries
- [ ] Return estimated delivery ETA at dispatch

### ETA Exposure
- [ ] ETA is available to the kitchen panel (countdown target)
- [ ] ETA is available to the customer tracking page (countdown target)
- [ ] ETA is recalculated and pushed on significant changes

## Acceptance Criteria
- ETA adapts: same product at lunch peak is estimated longer than at off-peak
- After 30 days of real orders, ETA accuracy should exceed base-time-only estimation
- ETA changes never cause the customer timer to jump forward (only small corrections allowed)
- If no history exists, system falls back gracefully to the base prep time

## Dependencies
- Product Catalog (base prep times)
- Order Management (Restaurant) (actual completion timestamps)
- Kitchen Panel (kitchen load data)
- Delivery Driver Panel (actual delivery durations)
