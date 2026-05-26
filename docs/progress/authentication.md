# Authentication & Roles

**Status:** 🔴 Not Started
**Phase:** 1 — Foundation

## Description
Every actor in the system (restaurant staff, customer, delivery driver) needs an identity and a permission set.
The system must differentiate what each role can see and do without leaking between contexts.

## Actors & Roles
- `restaurant_admin` — full restaurant access, manages everything
- `restaurant_manager` — manages operations, no billing settings
- `kitchen_staff` — sees only the kitchen panel
- `customer` — accesses only the storefront and their own orders
- `delivery_driver` — sees only orders assigned to them

## Tasks

### Authentication
- [ ] Customer registration and login (email or phone)
- [ ] Restaurant staff login (email, managed by admin)
- [ ] Driver login
- [ ] Password reset flow
- [ ] Session management

### Authorization
- [ ] Define roles and permissions (Gate / Policy per domain)
- [ ] Middleware guards per panel (restaurant, customer, driver)
- [ ] Restaurant admin can invite staff and assign roles
- [ ] Kitchen staff cannot access backoffice settings
- [ ] Customers cannot access restaurant internals

### Data Isolation
- [ ] Restaurant data is scoped to the restaurant (multi-tenant safe)
- [ ] Customers see only their own orders
- [ ] Drivers see only orders assigned to them

## Acceptance Criteria
- A customer cannot reach any restaurant admin route
- A kitchen staff member logging in lands on the kitchen panel only
- An unauthenticated user can browse the menu but cannot place an order
- Role changes take effect without requiring a new login

## Dependencies
- None (this is the foundation)
