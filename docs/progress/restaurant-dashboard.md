# Restaurant Dashboard

**Status:** 🔴 Not Started
**Phase:** 2 — Restaurant Backoffice

## Description
The main landing page for restaurant managers after login.
It gives an operational snapshot: what is happening right now, what needs attention, and today's summary.
It is not a reporting dashboard — it is an operational awareness screen.

## Tasks

### Live Operations Summary
- [ ] Count of active orders by status
- [ ] Count of tables: available, occupied, waiting
- [ ] Count of pending confirmations (needing action)
- [ ] Drivers currently active / assigned

### Today's Numbers
- [ ] Total orders received today
- [ ] Orders completed vs canceled
- [ ] Average prep time today vs historical average

### Alerts & Attention Items
- [ ] Orders awaiting confirmation for more than N minutes
- [ ] Overdue orders in the kitchen
- [ ] Tables waiting for staff attention

### Quick Actions
- [ ] Go to kitchen panel
- [ ] Go to order management
- [ ] Go to table management

## Acceptance Criteria
- Dashboard loads in under 2 seconds
- Numbers update without full page reload
- No business logic runs in the dashboard component — data comes from pre-computed queries

## Dependencies
- Authentication & Roles
- Order Management (Restaurant)
- Table Management
- Kitchen Panel
