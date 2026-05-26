# Employee Management

**Status:** 🔴 Not Started
**Phase:** 2 — Restaurant Backoffice

## Description
Restaurant admins can create accounts for their staff, assign roles, and control access.
No staff member should have more access than their operational role requires.

## Tasks

### Staff Accounts
- [ ] Restaurant admin invites staff via email
- [ ] Staff sets their own password on first access
- [ ] Admin can deactivate a staff account
- [ ] Deactivated accounts cannot log in

### Role Assignment
- [ ] Assign one or more roles to a staff member
- [ ] Available roles: `restaurant_manager`, `kitchen_staff`, `counter_staff`
- [ ] Admin can change roles at any time

### Access Control by Role
- [ ] `restaurant_manager`: can manage catalog, tables, orders, view reports — cannot change billing or payment settings
- [ ] `kitchen_staff`: kitchen panel only
- [ ] `counter_staff`: order management panel, table status — no catalog or settings access

### Staff Directory
- [ ] List all staff with name, role, status (active/inactive)
- [ ] Edit staff details and role

## Acceptance Criteria
- A deactivated employee cannot log in even if they have the URL
- Role change is effective immediately on next request
- Admin cannot remove their own admin role
- Each panel enforces its own access guard independently

## Dependencies
- Authentication & Roles
