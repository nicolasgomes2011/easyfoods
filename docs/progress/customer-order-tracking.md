# Order Tracking (Customer)

**Status:** 🔴 Not Started
**Phase:** 5 — Order Tracking

## Description
After placing an order, the customer sees a live tracking page.
The countdown is always moving. The status is always current.
The customer must never be left wondering what is happening.

## Design Principle
The timer must count down consistently from the estimated completion time.
The customer sees "Pronto às 12:34" and a live countdown to that time.
The system updates the estimate if delays occur — with a clear explanation.

## Tasks

### Order Status Page
- [ ] Accessible via order confirmation link (shareable, no login required for guest)
- [ ] Show current order status with human-readable label
- [ ] Show order items summary
- [ ] Show order number

### Live Countdown
- [ ] Countdown to estimated ready time (for dine-in/pickup) or estimated delivery time
- [ ] Timer counts down in real time (JavaScript, updated every second)
- [ ] Expected ready time shown as clock time ("pronto às 12:34"), not just minutes
- [ ] If estimate changes, timer updates smoothly — never jumps or resets abruptly
- [ ] When order is ready / delivered, timer is replaced by status message

### Status Progression Display
- [ ] Visual status steps (e.g., Received → Confirmed → Being Prepared → Ready → Delivered)
- [ ] Current step highlighted
- [ ] Timestamp shown for each completed step
- [ ] Estimated time shown for pending steps

### Delay Communication
- [ ] If prep time is exceeded, show "Um pouco mais de tempo que o esperado" with updated estimate
- [ ] Never show a frozen timer — if estimate is unknown, show a spinner with status update

### Delivery-Specific
- [ ] When order is `out_for_delivery`, show driver is on the way
- [ ] Show updated ETA based on driver's location signals (phase 7+)

## Acceptance Criteria
- Timer is always decreasing (never frozen, never jumping backwards by more than a small correction)
- Page works for guest users without login
- Status updates without full page reload
- Delay is communicated with a human explanation, not a raw technical status
- Customer can share the tracking link

## Dependencies
- Cart & Checkout (order creation)
- Order Management (Restaurant) (status source)
- ETA Engine (estimate source)
- Realtime Notifications (status push)
