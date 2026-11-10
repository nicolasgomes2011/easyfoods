# Testing Roadmap

> **Principle:** every fix/feature must be exercised against the running app before it is declared done. Automated tests are the regression net under that workflow, not a substitute for it. See `coding-standards` and `testing-and-quality` skills for the Definition of Done.

## Status legend
- `[x]` done
- `[~]` partial
- `[ ]` pending

Last checked against `main` on **2026-11-10** (`a7778c0`): **242 tests**, all passing (`php artisan test`).

---

## 1. Current baseline (PHPUnit)

Already in the suite (`tests/`):

- `Feature/Auth/*` — login, registration disabled, password reset, email verification, password confirmation, 2FA challenge
- `Feature/Settings/*` — profile, password update, 2FA management
- `Feature/DashboardTest.php`, `Feature/DashboardBlocksTest.php` — dashboard on the real route + Livewire blocks (tables snapshot, deliveries, quick actions, comparison badges, tenant scope)
- `Feature/Catalog/*` — product form (variants, images), lifecycle (archive, delete guard), addons, category reorder, tenant scoping
- `Feature/Dining/*` — table sessions, waitlist
- `Feature/Driver/DriverPanelTest.php` — pick up / delivered, tenant scope
- `Feature/Order/*` — `TransitionOrderStatus`, kitchen actions, order detail actions, `Order` model
- `Feature/Store/*` — storefront → cart → checkout → `PlaceOrder` flow, cart selection validation
- `Feature/LivePollingTest.php` — polling cadence on panels and customer tracking
- `Unit/*` — `OrderStatus`, `PaymentStatus`, `UserRole`, `ProductAvailabilityStatus` enums; file-size ceiling

Gaps: per-role HTTP access (EF-10), dashboard KPI computations one by one, orders index filters, settings persistence, browser-level smoke tests.

---

## 2. Test infrastructure (foundation)

- [ ] Confirm `RefreshDatabase` / `DatabaseTransactions` trait usage is consistent
- [~] Factories for every model — exist for `Restaurant`, `Customer`, `Order`, `TableSession`, `User`, `WaitlistEntry`; missing for `Category`, `Product`, `AddonGroup`, `AddonOption`, `OrderItem`, `Payment`, `DiningTable`
- [ ] Helper trait or preset to spin up a logged-in user per role (`actingAsAdmin()`, `actingAsKitchen()`, etc.)
- [ ] CI job (GitHub Actions) running `php artisan test` on every push — EF-11
- [x] Dashboard covered on both layers: real HTTP route + `Livewire::test(Dashboard::class)` (`DashboardBlocksTest`)

---

## 3. Phase 1 — Foundation tests

### 3.1 Authentication & Roles
- [x] Unauthenticated request to `/dashboard` redirects to `/login`
- [ ] Unauthenticated request to `/admin/*` redirects to `/login`
- [~] Each role is blocked outside its scope — the `UserRole` permission helpers are unit-tested; the HTTP check per role and per menu link is EF-10
- [ ] Admin can reach every admin route — EF-10
- [x] Logout invalidates the session

### 3.2 Models & enums
- [x] `OrderStatus`, `PaymentStatus`, `UserRole`, `ProductAvailabilityStatus` enum coverage
- [~] `DeliveryType` enum coverage — delivery / pickup detection on `Order`
- [ ] `DiningTableStatus` enum coverage
- [x] `Order` total recomputation (subtotal + delivery_fee − discount)
- [x] `Order::byStatus` scope returns the expected rows
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
- [ ] `alerts` returns the delayed-order alert (keys off `order_status_histories.changed_at`)
- [ ] `alerts` returns the kitchen-overload alert when `inPreparationCount >= 5`
- [x] Tables snapshot, deliveries en route and comparison badges (incl. no-baseline guard)
- [x] Blocks scoped to the panel restaurant

### 4.2 Product Catalog (`App\Livewire\Catalog\ProductList`, `ProductForm`)
- [~] Create product: required fields validated — variant rows validated; base fields not covered
- [ ] Create product: prep time must be a positive integer
- [x] Edit product: changes persist (variants sync, image replacement)
- [ ] List paginates and filters by name / category
- [x] Addon groups linked to a product
- [x] Addon options: min/max selections respected
- [x] Archive / unarchive, delete guard with open orders, foreign-record rejection

### 4.3 Dining (Volt `dining.tables`, `dining.queue`)
- [ ] Create table: `number` and `capacity` required
- [ ] Edit table: persists
- [x] Delete blocked when there is an active session
- [ ] Counters (`free`, `occupied`, `reserved`) match DB state
- [x] Waitlist add / seat / remove actions (FIFO, tenant-scoped)
- [x] Session close blocked while an order is active; frees the table when all orders are final

### 4.4 Orders index / show
- [ ] Index filters by status, type, date, search term
- [ ] Pagination works
- [ ] Show view returns 404 on a non-existent order
- [~] Show view — renders the confirm action for a pending order; items/addons/totals/history/payment not asserted

### 4.5 Settings
- [ ] Store info form save persists
- [ ] Operating hours save persists per weekday
- [ ] Delivery zones CRUD persists
- [ ] Payment methods toggle persists

---

## 5. Phase 4 — Kitchen & Order Flow tests

### 5.1 Kitchen panel
- [~] Queue lists only `confirmed` and `in_preparation` — the real route renders the start button for a confirmed order
- [ ] Counters match DB
- [~] Polling — cadence asserted (`LivePollingTest`); state across polls not asserted
- [x] Mark `in_preparation` action transitions status and writes history
- [x] Mark `ready` action transitions status and writes history
- [ ] Cannot mark `ready` if items checklist is incomplete (once item-level exists)

### 5.2 Order actions
- [x] Confirm: `pending_confirmation` → `confirmed`, history row created
- [x] Cancel: requires reason, status → `canceled`, history row stores reason
- [x] Reject: requires reason
- [x] Mark completed: requires `delivered` or `ready_for_pickup` previous status
- [x] Backward transitions are rejected (e.g. `confirmed` → `draft`)

---

## 6. Phase 3 / 5 / 6 — Customer & Delivery tests

- [x] Storefront menu listing (public pages render)
- [~] Cart add / update / remove — add (with variants/addons) covered; update/remove not asserted
- [x] Checkout server-side total recalc cannot be bypassed by client (snapshots, foreign variants/addons rejected)
- [~] Order placement creates `pending_confirmation` with frozen snapshot and history — restaurant notification not built
- [~] Customer tracking — polling while active / stops when final; status-step content not asserted
- [~] Driver flow — pick up / delivered covered; accept / decline does not exist

---

## 7. End-to-end / browser

`Livewire::test()` is not enough for route-layer bugs (see ADJUSTMENTS.md item 2). Browser-level smoke tests:

- [ ] Dusk or Playwright suite that logs in and hits every admin page, asserting HTTP 200 and absence of `Undefined`, `ErrorException`, `Internal Server Error` in the response
- [ ] One golden-path test: login → create product → create dining table → log out → log back in → confirm everything is still there

---

## 8. Quality gates

- [ ] CI fails on test regression — EF-11
- [ ] CI runs `php artisan view:clear` then the test suite (stale compiled blades have already burned us once) — EF-11
- [ ] Coverage report — target ≥ 70% on `app/Livewire/**`, `app/Models/**`, `app/Enums/**`
- [ ] Forbid `dd(`, `dump(`, `var_dump(` in committed files — EF-11 (CI guard job)
- [x] File-size ceiling: `tests/Unit/FileSizeCeilingTest.php` keeps files under the line limit
