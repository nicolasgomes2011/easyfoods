# Bill & Tab Splitting

**Status:** 🔴 Not Started
**Phase:** 8 — Advanced

## Description
Groups dining in need to manage a shared tab and split it.
The comanda (tab) belongs to the table session.
Multiple people can add orders to the same tab, and splitting must be flexible.

## Context
For dine-in customers, the typical flow is:
1. Group arrives at table
2. Each person orders separately or together
3. At the end, they split the bill

The system must support both scenarios: one person orders for everyone, or each person orders individually and tracks their own items.

## Tasks

### Tab per Table
- [ ] A table session has one open tab
- [ ] All orders placed at a table in a session belong to the tab
- [ ] Tab shows running total as orders are added
- [ ] Tab can be viewed by any customer linked to the table (via QR or session)

### Individual Item Tracking
- [ ] Customers at the same table can identify which items are theirs
- [ ] Each item in the tab can be tagged to a person (name or seat number)
- [ ] Untagged items are treated as shared

### Split Options
- [ ] Split equally: divide total by number of people
- [ ] Split by item: each person pays for their tagged items
- [ ] Custom split: manual amount per person

### Tab Closing
- [ ] Staff or customer initiates tab close
- [ ] System shows split summary before closing
- [ ] Tab is locked once closing process begins (no new items)

## Acceptance Criteria
- Two people at the same table can see the same tab in real time
- Splitting by item accounts for all tagged and untagged items
- Equal split always totals to the exact tab amount (no rounding errors leaving a remainder)
- Tab cannot be closed while there are items still being prepared

## Dependencies
- Table Management
- Customer Cart & Checkout
- Order Management (Restaurant)
- Authentication (optional — works with table session link)
