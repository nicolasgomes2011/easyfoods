# Kitchen Panel

**Status:** 🔴 Not Started
**Phase:** 4 — Kitchen & Order Flow

## Description
The kitchen panel is the operational heart of the restaurant.
Kitchen staff see incoming orders, their contents, and a live countdown per order.
The countdown is driven by the ETA engine, not a static number.

## Tasks

### Order Queue Display
- [ ] Show all active orders in preparation queue
- [ ] Each order card shows: order number, table or delivery, items, special notes
- [ ] Orders sorted by: time received (oldest first)
- [ ] Visual differentiation between dine-in, pickup, and delivery orders

### Countdown Timers
- [ ] Each order has a live countdown timer
- [ ] Timer starts when order status moves to `in_preparation`
- [ ] Estimated completion time = calculated by ETA engine (not just the product's base time)
- [ ] Timer goes red when less than 2 minutes remain
- [ ] Timer continues counting past zero (shows how overdue)

### Status Transitions (Kitchen)
- [ ] Kitchen staff marks order as `in_preparation` (starts timer)
- [ ] Kitchen staff marks order as `ready` (notifies counter/delivery)
- [ ] Kitchen cannot mark an order ready if items are missing confirmation

### Multi-Item Orders
- [ ] Show each item in the order as a checklist
- [ ] Kitchen staff can check off each item as it is completed
- [ ] Order is auto-marked `ready` when all items are checked (optional setting)

### Sound & Visual Alerts
- [ ] Audio alert when a new order arrives
- [ ] Visual pulse on new order cards
- [ ] Alert when an order is approaching overdue

### Filters
- [ ] Filter view by: all orders, dine-in only, delivery only, pickup only

## Acceptance Criteria
- Kitchen panel is usable without a keyboard (touch-friendly)
- New orders appear without page refresh
- Countdown timer never freezes or resets unexpectedly
- Overdue orders remain visible and clearly flagged
- Kitchen staff cannot access any backoffice settings from this panel

## Dependencies
- Authentication & Roles (kitchen_staff role)
- Product Catalog (for item details)
- Order Management (Restaurant)
- ETA Engine (for dynamic countdown)
- Realtime Notifications
