# Table Management

**Status:** 🔴 Not Started
**Phase:** 2 — Restaurant Backoffice / 4 — Kitchen & Order Flow

## Description
Restaurants configure their physical layout (tables) and manage table state in real time.
Table management is the bridge between the backoffice configuration and live operational control.

## Tasks

### Configuration (Backoffice)
- [ ] Add tables: table number/name, capacity (seats)
- [ ] Edit table details
- [ ] Remove table (guard: cannot remove if table has an open session)
- [ ] Organize tables by section/area (optional grouping)

### Live Status (Operational Panel)
- [ ] Visual overview of all tables with current status
- [ ] Table statuses: `available`, `occupied`, `waiting`, `served`, `reserved`
- [ ] Staff can manually change table status
- [ ] When an order is placed for a table, table auto-transitions to `occupied`
- [ ] When all orders at a table are closed/paid, table transitions to `served` and then `available`

### Waiting List
- [ ] Add a group to the waiting list (name, party size, time waiting)
- [ ] View waiting list sorted by arrival time
- [ ] Assign waiting group to a newly available table
- [ ] Remove from waiting list (left, no-show, seated)
- [ ] Alert staff when a table opens up and the waiting list is not empty

### Table Session
- [ ] A table session starts when the first order is placed at it
- [ ] Multiple orders can belong to one session (group ordering)
- [ ] Session can be closed (all items served, tab closed)
- [ ] Session history is preserved for reporting

## Acceptance Criteria
- A table cannot be removed while it has an active session
- When a table opens, the staff sees a visible cue if there is someone on the waiting list
- Table status is visible in real time to all restaurant staff without page refresh
- Waiting list shows time elapsed since each group arrived

## Dependencies
- Authentication & Roles
- Order Management (Restaurant)
