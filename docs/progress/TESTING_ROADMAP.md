# Testing Roadmap

> **Principle:** every fix/feature must be exercised against the running app before it is declared done. Automated tests are the regression net under that workflow, not a substitute for it. See `coding-standards` and `testing-and-quality` skills for the Definition of Done.

## Status legend
- `[x]` done
- `[~]` partial
- `[ ]` pending

---

## 1. Current baseline (Pest)

Already in the suite (`tests/`):

- `Feature/Auth/*` — login, register, password reset, email verification, password confirmation, 2FA (Fortify defaults)
- `Feature/Settings/*` — profile, password update, 2FA management
- `Feature/DashboardTest.php` — basic dashboard reachability (needs review after the route fix in commit `05912aa`)
- `Feature/Order/OrderModelTest.php` — Order model behavior
- `Unit/OrderStatusTest.php` — `OrderStatus` enum
- `Unit/PaymentStatusTest.php` — `PaymentStatus` enum
- `Unit/UserRoleTest.php` — `UserRole` enum
- `Unit/ProductAvailabilityStatusTest.php` — availability enum

Gap: nothing covers the implemented Livewire components (Dashboard KPIs, kitchen queue, orders index, customers, dining tables, product CRUD).

---

## 2. Test infrastructure (foundation)

- [ ] Confirm `RefreshDatabase` / `DatabaseTransactions` trait usage is consistent
- [ ] Factories for every model (audit `database/factories/`): `Restaurant`, `Category`, `Product`, `AddonGroup`, `AddonOption`, `Customer`, `Order`, `OrderItem`, `Payment`, `DiningTable`, `User`
- [ ] Helper trait or Pest preset to spin up a logged-in user per role (`actingAsAdmin()`, `actingAsKitchen()`, etc.)
- [ ] CI job (GitHub Actions) running `php artisan test` on every push
- [ ] Update `DashboardTest.php` to assert the Livewire component renders (use `Livewire::test(Dashboard::class)`) AND a full HTTP `get('/dashboard')->assertOk()` — both layers (regression for the route fix in commit `05912aa`)

---

## 3. Phase 1 — Foundation tests

### 3.1 Authentication & Roles
- [ ] Unauthenticated request to `/dashboard` redirects to `/login`
- [ ] Unauthenticated request to `/admin/*` redirects to `/login`
- [ ] Each role middleware blocks routes outside its scope:
  - [ ] Kitchen user cannot reach `/admin/catalog/products`
  - [ ] Attendant cannot reach `/admin/settings/*`
  - [ ] Delivery cannot reach `/admin/orders` (or whatever the contract is — verify and lock)
- [ ] Admin can reach every admin route
- [ ] Logout invalidates the session

### 3.2 Models & enums
- [x] `OrderStatus`, `PaymentStatus`, `UserRole`, `ProductAvailabilityStatus` enum coverage
- [ ] `DeliveryType` enum coverage
- [ ] `DiningTableStatus` enum coverage
- [ ] `Order` total recomputation (subtotal + delivery_fee − discount)
- [ ] `Order::byStatus` scope returns the expected rows
- [ ] `Product` price coercion / decimals
- [ ] Cascade rules: deleting a `Category` with products is blocked or scoped per spec

---

## 4. Phase 2 — Restaurant Backoffice tests

### 4.1 Dashboard (`App\Livewire\Dashboard`)
- [ ] `openCount`, `inPreparationCount`, `readyCount` return DB counts
- [ ] `todayOrderCount` excludes `canceled` and `draft`
- [ ] `avgPrepMinutes` returns `null` when no orders are ready today
- [ ] `todayRevenue` only sums `delivered` + `completed` for today
- [ ] `kitchenQueue` aggregates by product across in-preparation orders
- [ ] `alerts` returns the delayed-order alert for orders with `updated_at > 30min`
- [ ] `alerts` returns the kitchen-overload alert when `inPreparationCount >= 5`
- [ ] Empty DB: page renders with zeros and `—`, no exceptions

### 4.2 Product Catalog (`App\Livewire\Catalog\ProductList`, `ProductForm`)
- [ ] Create product: required fields validated
- [ ] Create product: prep time must be a positive integer
- [ ] Edit product: changes persist
- [ ] List paginates and filters by name / category
- [ ] Addon groups linked to a product
- [ ] Addon options: min/max selections respected

### 4.3 Dining (Volt `dining.tables`, `dining.queue`)
- [ ] Create table: `number` and `capacity` required
- [ ] Edit table: persists
- [ ] Delete blocked when there is an active session (once sessions exist)
- [ ] Counters (`free`, `occupied`, `reserved`) match DB state
- [ ] Waitlist add / seat / remove actions

### 4.4 Orders index / show
- [ ] Index filters by status, type, date, search term
- [ ] Pagination works
- [ ] Show view returns 404 on a non-existent order
- [ ] Show view displays items, addons, totals, status history, payment

### 4.5 Settings
- [ ] Store info form save persists
- [ ] Operating hours save persists per weekday
- [ ] Delivery zones CRUD persists
- [ ] Payment methods toggle persists

---

## 5. Phase 4 — Kitchen & Order Flow tests

### 5.1 Kitchen panel
- [ ] Queue lists only `confirmed` and `in_preparation`
- [ ] Counters match DB
- [ ] Polling does not break component state
- [ ] Mark `in_preparation` action transitions status and writes history
- [ ] Mark `ready` action transitions status and writes history
- [ ] Cannot mark `ready` if items checklist is incomplete (once item-level exists)

### 5.2 Order actions
- [ ] Confirm: `pending_confirmation` → `confirmed`, history row created
- [ ] Cancel: requires reason, status → `canceled`, history row stores reason
- [ ] Reject: requires reason
- [ ] Mark completed: requires `delivered` or `ready_for_pickup` previous status
- [ ] Backward transitions are rejected (e.g. `ready` → `in_preparation`)

---

## 6. Phase 3 / 5 / 6 — Customer & Delivery tests

To be written when those phases land. Placeholders so they are not forgotten:

- [ ] Storefront menu listing (guest + authenticated)
- [ ] Cart add / update / remove
- [ ] Checkout server-side total recalc cannot be bypassed by client
- [ ] Order placement creates `pending_confirmation` and notifies restaurant
- [ ] Customer tracking shows real-time status
- [ ] Driver accept / decline / status updates flow

---

## 7. End-to-end / browser

`Livewire::test()` is not enough for route-layer bugs (see ADJUSTMENTS.md item 2). Browser-level smoke tests:

- [ ] Dusk or Playwright suite that logs in and hits every admin page, asserting HTTP 200 and absence of `Undefined`, `ErrorException`, `Internal Server Error` in the response
- [ ] One golden-path test: login → create product → create dining table → log out → log back in → confirm everything is still there

---

## 8. Quality gates

Once the foundation is in place:

- [ ] CI fails on test regression
- [ ] CI runs `php artisan view:clear` then the test suite (stale compiled blades have already burned us once)
- [ ] Coverage report — target ≥ 70% on `app/Livewire/**`, `app/Models/**`, `app/Enums/**`
- [ ] Pre-commit hook: forbid `dd(`, `dump(`, `var_dump(` in committed files
