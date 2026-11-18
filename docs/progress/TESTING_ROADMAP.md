# Testing Roadmap

> **Principle:** every fix/feature must be exercised against the running app before it is declared done. Automated tests are the regression net under that workflow, not a substitute for it. See `coding-standards` and `testing-and-quality` skills for the Definition of Done.

## Status legend
- `[x]` done
- `[~]` partial
- `[ ]` pending

Last checked against `main` on **2026-11-18** (`de95d97`): **758 tests**, all passing (`php artisan test`), on SQLite and MySQL 8 in CI.

---

## 1. Current baseline (PHPUnit)

Already in the suite (`tests/`):

- `Feature/Auth/*` — login, registration disabled, password reset (and its Portuguese screens), email verification, password confirmation, 2FA challenge, per-role access matrix (`RoleAccessTest`: menu links, 200/403 per role, home screen per role, disabled accounts)
- `Feature/Settings/*` — profile, password update, 2FA management
- `Feature/DashboardTest.php`, `DashboardBlocksTest.php`, `DashboardPrepTimeTest.php` — dashboard on the real route + Livewire blocks (tables snapshot, deliveries, quick actions, comparison badges, average prep time, tenant scope)
- `Feature/Catalog/*` — product form (variants, images), lifecycle (archive, delete guard), addons, category reorder and unique names, ingredients, tenant scoping
- `Feature/Dining/*` — table sessions and server re-checks, waitlist, table QR codes, table tab and people at the table
- `Feature/Driver/*` — accept / decline, pause / resume, pick up / delivered, active drivers on the dashboard, call/map links
- `Feature/Order/*` — `TransitionOrderStatus`, numbering, milestones, kitchen actions / checklist / type filter / item details, order detail actions, item edits on open orders, "Marcar como pago", new-order alert, auto-expiry, ready-at estimate and learned prep time, `Order` model
- `Feature/Store/*` — storefront → cart → checkout → `PlaceOrder` flow, menu (sections, open status, search, unavailable, most ordered), cart (selection, quantities, notes, clear), checkout (payment, customer by phone, closed store, zones, dine-in table, default type, address length), table QR mode, order tracking
- `Feature/Reports/*`, `Feature/Waste/*` — sales, top products / peak hours and waste reports with CSV, waste log
- `Feature/TenantLeakTest.php`, `TenantIsolationByIdTest.php`, `RestaurantScopeTest.php`, `CurrentRestaurantTest.php`, `StaffRestaurantTest.php` — two-restaurant isolation on every screen and id lookup
- `Feature/LivePollingTest.php`, `LocaleAndTimezoneTest.php`, `DeadRoutesTest.php` — polling cadence, pt-BR / Brasília time, removed routes stay gone
- `Unit/*` — `OrderStatus`, `PaymentStatus`, `UserRole`, `ProductAvailabilityStatus` enums; file-size ceiling

Gaps: dashboard KPI computations one by one, orders index filters, settings screens persistence, cart update/remove, browser-level smoke tests.

---

## 2. Test infrastructure (foundation)

- [ ] Confirm `RefreshDatabase` / `DatabaseTransactions` trait usage is consistent
- [~] Factories for every model — exist for `Restaurant`, `Customer`, `Ingredient`, `Order`, `TableSession`, `User`, `WaitlistEntry`, `WasteEntry`; missing for `Category`, `Product`, `AddonGroup`, `AddonOption`, `OrderItem`, `Payment`, `DiningTable`
- [ ] Helper trait or preset to spin up a logged-in user per role (`actingAsAdmin()`, `actingAsKitchen()`, etc.)
- [x] CI job (GitHub Actions) running `php artisan test` on every pull request and push to `main`, on SQLite and MySQL 8 (EF-11)
- [x] Dashboard covered on both layers: real HTTP route + `Livewire::test(Dashboard::class)` (`DashboardBlocksTest`)

---

## 3. Phase 1 — Foundation tests

### 3.1 Authentication & Roles
- [x] Unauthenticated request to `/dashboard` redirects to `/login`
- [x] Unauthenticated request to `/admin/*` redirects to `/login`
- [x] Each role is blocked outside its scope — every forbidden menu area answers 403 (`RoleAccessTest`)
- [x] Admin can reach every admin route linked from the menu (`RoleAccessTest`)
- [x] Logout invalidates the session
- [x] A disabled account cannot log in and an open session is logged out (`RoleAccessTest`)

### 3.2 Models & enums
- [x] `OrderStatus`, `PaymentStatus`, `UserRole`, `ProductAvailabilityStatus` enum coverage
- [~] `DeliveryType` enum coverage — delivery / pickup detection on `Order`, labels on panel screens
- [ ] `DiningTableStatus` enum coverage
- [x] `Order` total recomputation (subtotal + delivery_fee − discount)
- [x] `Order::byStatus` scope returns the expected rows
- [ ] `Product` price coercion / decimals
- [ ] Cascade rules: deleting a `Category` with products is blocked or scoped per spec

### 3.3 Multi-tenant
- [x] Every panel screen shows only the panel restaurant's rows (`TenantLeakTest`)
- [x] Ids of another restaurant answer 404 / are refused by the policies (`TenantIsolationByIdTest`)
- [x] Global scope and the current restaurant follow the logged-in user (`RestaurantScopeTest`, `CurrentRestaurantTest`, `StaffRestaurantTest`)

---

## 4. Phase 2 — Restaurant Backoffice tests

### 4.1 Dashboard (`App\Livewire\Dashboard`)
- [~] `openCount`, `inPreparationCount`, `readyCount` return DB counts — open and in-preparation asserted per restaurant (`StaffRestaurantTest`)
- [ ] `todayOrderCount` excludes `canceled` and `draft`
- [x] `avgPrepMinutes` returns `null` when there is no kitchen data, and counts only kitchen time (`DashboardPrepTimeTest`)
- [ ] `todayRevenue` only sums `delivered` + `completed` for today
- [~] `kitchenQueue` aggregates by product across in-preparation orders — minutes display asserted (`OperationalMinutesTest`)
- [~] `alerts` returns the delayed-order alert — minutes display asserted (`OperationalMinutesTest`)
- [ ] `alerts` returns the kitchen-overload alert when `inPreparationCount >= 5`
- [x] Tables snapshot, deliveries en route, active drivers and comparison badges (incl. no-baseline guard)
- [x] Blocks scoped to the panel restaurant

### 4.2 Product Catalog (`App\Livewire\Catalog\ProductList`, `ProductForm`)
- [~] Create product: required fields validated — variant rows validated; base fields not covered
- [ ] Create product: prep time must be a positive integer
- [x] Edit product: changes persist (variants sync, image replacement)
- [ ] List paginates and filters by name / category
- [x] Addon groups linked to a product
- [x] Addon options: min/max selections respected
- [x] Archive / unarchive, delete guard with open orders, foreign-record rejection
- [x] Category names unique per restaurant; ingredients registry

### 4.3 Dining (Volt `dining.tables`, `dining.queue`, `dining.tab`)
- [ ] Create table: `number` and `capacity` required
- [ ] Edit table: persists
- [x] Delete blocked when there is an active session
- [ ] Counters (`free`, `occupied`, `reserved`) match DB state
- [x] Waitlist add / seat / remove actions (FIFO, tenant-scoped); seating refuses a table that is no longer free
- [x] Session close blocked while an order is active; frees the table when all orders are final
- [x] Status change refuses free / reserved while a tab is open, and unknown values
- [x] QR code per table: page, PNG/SVG download, print page, link opens `/mesa/{uuid}`
- [x] Table tab: orders, totals, people and item-to-person

### 4.4 Orders index / show
- [ ] Index filters by status, type, date, search term
- [ ] Pagination works
- [~] Show view returns 404 — covered for an order of another restaurant (`TenantIsolationByIdTest`), not for a non-existent id
- [~] Show view — actions, items with addon names, item edits and payment asserted; totals and history not

### 4.5 Settings
- [ ] Store info form save persists
- [ ] Operating hours save persists per weekday
- [ ] Delivery zones CRUD persists
- [ ] Payment methods toggle persists

---

## 5. Phase 4 — Kitchen & Order Flow tests

### 5.1 Kitchen panel
- [~] Queue lists only `confirmed` and `in_preparation` — the real route renders the start button for a confirmed order
- [x] Counters match DB and follow the type filter (`KitchenTypeFilterTest`)
- [~] Polling — cadence asserted (`LivePollingTest`); state across polls not asserted
- [x] Mark `in_preparation` action transitions status and writes history
- [x] Mark `ready` action transitions status and writes history
- [x] Cannot mark `ready` if the item checklist is incomplete (`KitchenItemChecklistTest`)
- [x] Item addons, notes and removed items on the card; new-order sound alert

### 5.2 Order actions
- [x] Confirm: `pending_confirmation` → `confirmed`, history row created
- [x] Cancel: requires reason, status → `canceled`, history row stores reason
- [x] Reject: requires reason
- [x] Mark completed: requires `delivered` or `ready_for_pickup` previous status
- [x] Backward transitions are rejected (e.g. `confirmed` → `draft`)
- [x] Auto-expiry of unconfirmed orders; add / remove items on open dine-in orders; "Marcar como pago" keeps the order status

---

## 6. Phase 3 / 5 / 6 — Customer & Delivery tests

- [x] Storefront menu listing (public pages render), sections, open status, search, unavailable products
- [~] Cart add / update / remove — add (with variants/addons), quantity limits, notes and clear covered; update/remove of one line not asserted
- [x] Checkout server-side total recalc cannot be bypassed by client (snapshots, foreign variants/addons rejected, zone fee on the server)
- [x] Order placement creates `pending_confirmation` with frozen snapshot and history; the counter and kitchen are alerted (`NewOrderAlertTest`)
- [x] Checkout payment (pending `Payment`, change), customer by phone, closed store, table QR / kiosk mode
- [x] Customer tracking — polling while active / stops when final; steps per order type with their times (`OrderTrackingTest`)
- [x] Driver flow — accept / decline, pause / resume, pick up / delivered

---

## 7. End-to-end / browser

`Livewire::test()` is not enough for route-layer bugs (see ADJUSTMENTS.md item 2). Browser-level smoke tests:

- [ ] Dusk or Playwright suite that logs in and hits every admin page, asserting HTTP 200 and absence of `Undefined`, `ErrorException`, `Internal Server Error` in the response
- [ ] One golden-path test: login → create product → create dining table → log out → log back in → confirm everything is still there

---

## 8. Quality gates

- [x] CI fails on test regression (EF-11)
- [x] CI runs `php artisan view:clear` then the test suite (EF-11)
- [ ] Coverage report — target ≥ 70% on `app/Livewire/**`, `app/Models/**`, `app/Enums/**`
- [x] Forbid `dd(`, `dump(`, `var_dump(` in committed files — CI `guard` job (EF-11)
- [x] File-size ceiling: `tests/Unit/FileSizeCeilingTest.php` keeps files under the line limit
