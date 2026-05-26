# Realtime Notifications

**Status:** 🔴 Not Started
**Phase:** 4 — Kitchen & Order Flow

## Description
The system must push updates to all relevant parties when something important happens.
No actor should need to refresh a page to know the current state.

## Notification Events

| Event | Who is notified | Channel |
|-------|----------------|---------|
| New order received | Restaurant staff | In-app alert + sound |
| Order confirmed | Customer | In-app status update |
| Order moved to in_preparation | Customer | In-app status update |
| Order ready | Counter staff + customer | In-app alert |
| Order out for delivery | Customer | In-app + optional push |
| Order delivered | Customer + restaurant | In-app status update |
| Order canceled | Customer | In-app + reason |
| Table available (waiting list) | Restaurant counter staff | In-app alert |
| New driver assignment | Driver | In-app alert |

## Tasks

### Infrastructure
- [ ] Choose realtime strategy: Livewire polling, Laravel Echo + Pusher/Soketi, or SSE
- [ ] Queue-based notification dispatch (all notifications go through a job)
- [ ] Notification log per order (for debugging and support)

### In-App (Browser / Panel)
- [ ] Restaurant panel receives new order alerts without reload
- [ ] Kitchen panel receives order updates without reload
- [ ] Customer tracking page receives status updates without reload
- [ ] Driver panel receives delivery requests without reload

### Sound Alerts
- [ ] New order sound for restaurant staff
- [ ] New delivery request sound for driver
- [ ] Configurable: restaurant admin can toggle sound alerts

### Notification Log
- [ ] Every sent notification is stored with: event type, recipient, timestamp, delivery status
- [ ] Failed notifications are retried up to 3 times

## Acceptance Criteria
- Restaurant staff see a new order within 3 seconds of it being placed
- Customer status page updates within 5 seconds of a status change
- Sound alerts do not fire more than once per event per tab
- Notification log is queryable for support purposes

## Dependencies
- Order Management (Restaurant) (event source)
- Kitchen Panel (consumer)
- Customer Order Tracking (consumer)
- Delivery Driver Panel (consumer)
