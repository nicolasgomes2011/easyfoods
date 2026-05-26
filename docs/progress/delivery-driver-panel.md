# Delivery Driver Panel

**Status:** 🔴 Not Started
**Phase:** 6 — Delivery Driver

## Description
Drivers have their own interface: they receive delivery requests, accept or decline, and update status as they complete the delivery.
The driver's actions feed directly into the customer's tracking page.

## Tasks

### Driver Account
- [ ] Driver logs in with separate credentials
- [ ] Driver profile: name, vehicle, contact number

### Incoming Delivery Requests
- [ ] Driver sees orders assigned to them in `ready` status and marked for delivery
- [ ] Delivery request shows: address, order summary, estimated distance
- [ ] Driver can accept or decline the request
- [ ] Declined request returns to pool for reassignment

### Active Delivery
- [ ] Once accepted, order moves to `out_for_delivery`
- [ ] Driver sees pickup address (restaurant) and delivery address (customer)
- [ ] Driver marks order as `delivered` on arrival
- [ ] Customer is notified on each status change

### Driver Status
- [ ] Driver can set themselves as available or unavailable
- [ ] Only available drivers receive new requests

## Acceptance Criteria
- A driver can only see orders assigned to them
- Once a driver marks an order delivered, customer sees the update within 5 seconds
- Delivery request shows enough information for the driver to decide without extra steps
- Driver cannot mark an order delivered before they accepted it

## Dependencies
- Authentication & Roles (delivery_driver role)
- Order Management (Restaurant) (order assignment)
- Customer Order Tracking (status updates)
- Realtime Notifications
- ETA Engine (delivery time estimate)
