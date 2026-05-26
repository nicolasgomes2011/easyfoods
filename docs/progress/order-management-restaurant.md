# Order Management (Restaurant)

**Status:** 🔴 Not Started
**Phase:** 4 — Kitchen & Order Flow

## Description
Restaurant staff have a dedicated view to manage all orders across all channels (dine-in, delivery, pickup).
This is different from the kitchen panel — it is the operational command center for managers and counter staff.

## Tasks

### Order List
- [ ] Show all orders grouped by status
- [ ] Statuses: `pending_confirmation`, `confirmed`, `in_preparation`, `ready`, `out_for_delivery`, `delivered`, `completed`, `canceled`
- [ ] Filter by status, type (dine-in / delivery / pickup), date
- [ ] Search by order number or customer name

### Order Detail
- [ ] View full order: items, addons, notes, customer info
- [ ] View price breakdown
- [ ] View order timeline (status history with timestamps)
- [ ] View assigned driver (if delivery)

### Status Transitions (Restaurant)
- [ ] Confirm incoming order (`pending_confirmation` → `confirmed`)
- [ ] Cancel order with required reason
- [ ] Reject order at confirmation stage with required reason
- [ ] Mark order as completed after delivery/pickup confirmation

### Incoming Order Alert
- [ ] Audio + visual alert for new orders in `pending_confirmation`
- [ ] Orders auto-expire confirmation window if not acted on (configurable timeout)

### Order Corrections
- [ ] Add items to an open dine-in order (before it moves to `in_preparation`)
- [ ] Remove items from an open order before kitchen confirmation

## Acceptance Criteria
- An order in `in_preparation` cannot have items added/removed
- Canceled orders include a mandatory reason stored in the status history
- Order list updates in real time without page refresh
- Staff cannot move an order backward in the status flow (no `ready` → `in_preparation`)

## Dependencies
- Authentication & Roles
- Product Catalog
- Table Management (for dine-in orders)
- Realtime Notifications
