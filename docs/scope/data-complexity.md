# Data Complexity

This document identifies the areas of the data model that are inherently complex
and require careful design to avoid consistency bugs, data loss, or reporting failures.

---

## Why the Data Model Is Complex

EasyFoods manages:
- Live operational state (table status, kitchen queue, order status)
- Historical data for learning (prep times, delivery durations)
- Snapshot data for integrity (prices at order time, not current price)
- Multi-party coordination (restaurant + customer + driver in real time)

Simple CRUD is insufficient. The schema must handle state machines, temporal snapshots, and audit trails.

---

## Critical Design Areas

### 1. Price Snapshots on Orders

**Problem:** A product's price can change at any time.
If an order stores only a product ID, its total becomes wrong when the price is updated.

**Rule:** When an order is placed, the price of each item (including addon prices) is copied and frozen into the order item record.
The product's current price is irrelevant to past orders.

Fields to snapshot:
- `unit_price` at time of order
- `addon_price_delta` for each selected addon
- `total_item_price` (computed and stored, not recalculated)

---

### 2. Product Changes After Order Creation

**Problem:** A product can be archived or edited after an order containing it is placed.

**Rule:** Order items store a snapshot of all relevant product data at order time (name, description, prep time, price).
Archiving or editing a product must not alter any past order.

---

### 3. Order Status as a State Machine

**Problem:** Statuses like `in_preparation` and `canceled` have strict transition rules.
A canceled order cannot go back to `confirmed`. An order cannot skip from `confirmed` to `delivered`.

**Rule:** Status transitions are enforced at the application layer (Actions/Services), never open to arbitrary assignment.

Valid transitions:
```
pending_confirmation → confirmed
pending_confirmation → canceled (rejected by restaurant)
confirmed → in_preparation
confirmed → canceled
in_preparation → ready
ready → out_for_delivery (delivery orders)
ready → completed (pickup or dine-in)
out_for_delivery → delivered
delivered → completed
any open status → canceled (with reason)
```

**Audit:** Every transition is recorded in `order_status_histories` with timestamp and actor.

---

### 4. Payment State Is Separate from Order State

**Problem:** Treating payment as just another order status leads to ambiguity.
An order can be `delivered` but payment `failed` (e.g., card declined on delivery).

**Rule:** `orders` has an `order_status` and `payments` has a `payment_status`.
They evolve independently. Business rules can join them (e.g., auto-cancel if payment not confirmed in X minutes) but they are never the same field.

---

### 5. Table Session and Multi-Order Tabs

**Problem:** Multiple orders belong to one table session. The tab total spans all of them.

**Rule:** A `table_sessions` record groups all orders placed at a table between open and close.
The tab is computed from all orders in the session. Closing the session locks it for modification.

---

### 6. ETA History Data

**Problem:** ETA calculation requires reliable historical records. If history is corrupted or incomplete, estimates degrade.

**Rule:**
- `product_prep_histories` records: product_id, time_band, day_of_week, actual_duration_minutes, recorded_at
- `delivery_transit_histories` records: distance_bucket, actual_duration_minutes, recorded_at
- These are append-only — historical records are never updated

---

### 7. Multi-Tenant Isolation

If multiple restaurants use the platform, each restaurant's data must be fully isolated.

**Rule:** Every entity that belongs to a restaurant includes a `restaurant_id` foreign key.
Queries are always scoped to the authenticated user's restaurant. No cross-restaurant data can leak.

---

## Core Entities (Summary)

| Entity | Notes |
|--------|-------|
| `restaurants` | Top-level tenant |
| `branches` | Optional, for multi-location restaurants |
| `users` | All actors — role determines access |
| `customers` | Extended profile for customer-type users |
| `customer_addresses` | Saved delivery addresses |
| `categories` | Product groupings |
| `products` | Catalog items with base_prep_time |
| `product_addons` | Addon groups linked to products |
| `addon_options` | Individual options within an addon group |
| `carts` | Per-user active cart |
| `cart_items` | Items + selected options in cart |
| `orders` | Placed orders with status and type |
| `order_items` | Frozen snapshot of each item at order time |
| `order_item_addons` | Selected addon options per order item (frozen) |
| `order_status_histories` | Full audit trail of every status change |
| `payments` | Payment record per order, independent lifecycle |
| `table_sessions` | Groups orders at a table for tab management |
| `tables` | Physical table config |
| `delivery_drivers` | Driver profiles |
| `deliveries` | Per-order delivery assignment and status |
| `product_prep_histories` | ETA learning data for prep time |
| `delivery_transit_histories` | ETA learning data for transit time |
| `notifications` | Log of every sent notification |
