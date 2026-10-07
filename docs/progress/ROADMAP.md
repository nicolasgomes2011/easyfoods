# EasyFoods — Roadmap

> **Reading guide**
> - `[x]` = implemented and merged on `main`
> - `[~]` = partially implemented (UI/structure exists, business rules or actions pending)
> - `[ ]` = not started
>
> Cross-cutting work is tracked in [ADJUSTMENTS.md](ADJUSTMENTS.md) (cleanup of fake data, route layer, env) and [TESTING_ROADMAP.md](TESTING_ROADMAP.md) (test coverage).

**Last reconciled against the code: 2026-10-07** (`main` @ `cfb4f79`, 214 tests). Checkboxes below
were re-derived from the actual source, not from the previous plan — where the shipped behavior differs from
what a bullet specced, the bullet says so instead of being silently ticked.

---

## Phase 0 — Adjustments & Cleanup ✅ complete (commit `2bf420a`)

Mission: remove every piece of demo/fake data so the panel reflects only what was registered through the UI. Full plan in [ADJUSTMENTS.md](ADJUSTMENTS.md).

- [x] Remove `OrderSeeder`, `CatalogSeeder`, `RestaurantSeeder` from `DatabaseSeeder`
- [x] Strip `UserSeeder` down to the single admin (Nicolas)
- [x] Delete any hardcoded sample arrays in Livewire/Volt components
- [x] Drop the duplicate `routes/web.php` (only `default_routes_web.php` is loaded)
- [x] Add a one-time migration or artisan command to wipe demo rows from existing DBs (`php artisan demo:purge`)
- [x] Verify every panel page renders gracefully with an empty DB (empty states, no errors)

## Phase 1 — Foundation

Core infrastructure. Nothing works without this.

- [x] Authentication: login, register, password reset, email verification, 2FA (Volt)
- [x] Role & permission middleware (`admin`, `manager`, `attendant`, `kitchen`, `delivery`)
- [x] Database schema: restaurants, users, customers, products, categories, addons, variants, carts, orders, order items, payments, status history, dining tables, operating hours, delivery zones, store settings
- [x] Base Livewire layout (`components.layouts.app`) for the restaurant panel
- [~] Multi-tenant scoping (restaurant_id) enforced at query level — applied **explicitly per query** (`where('restaurant_id', Restaurant::query()->value('id'))`) across catalog/orders/kitchen/dining/driver. A global scope or policy is not implemented yet
- [x] Customer-facing layout (`components.layouts.customer`, mobile-first)
- [~] Driver-facing layout — driver panel reuses the admin/app layout; no dedicated driver shell yet

## Phase 2 — Restaurant Backoffice (Core Operations)

The restaurant needs to configure and operate.

### Product Catalog
- [x] Product list with search and filter (Livewire `Catalog\ProductList`)
- [x] Create product form (Livewire `Catalog\ProductForm`)
- [x] Edit product form
- [x] Addon group management (Volt `catalog.addons`)
- [x] Category CRUD (Volt `catalog.categories`)
- [x] Reorder categories (moveUp/moveDown, restaurant-scoped)
- [x] Archive / pause product (`archived_at`, distinct from availability pause)
- [x] Delete product with guard against open orders (active-order check, not "any order")
- [x] Manual availability toggle
- [x] Product variants (Small / Medium / Large) — repeatable rows in `ProductForm`
- [x] Product image upload + resize (GD, capped at 1200px longest side)

### Dining (Tables)
- [x] Tables list with create/edit/delete (Volt `dining.tables`)
- [x] Table status counters (free/occupied/reserved)
- [x] Waitlist queue (Volt `dining.queue` — add/seat/remove, FIFO, tenant-scoped)
- [x] Auto-transition `occupied` when an order is placed at the table
- [x] Table session lifecycle (opens on first dine-in order, staff close manually — stand-in for "on payment" since no payment gateway exists yet)
- [x] Guard: cannot delete a table with an active session

### Employee Management
- [x] Staff invite via email (`InviteStaffMember` action; UI falls back to a copyable link if no mail driver)
- [x] First-login password set (password-reset routes restored in `routes/auth.php`, commit `becc52e`)
- [x] Deactivate / reactivate accounts (`is_active` + `RoleMiddleware` blocks inactive users)
- [x] Role assignment UI
- [x] Staff directory list (Volt `users.index`)

### Settings
- [x] Store info (Volt, persisted)
- [x] Operating hours (7-day grid, `opens_at`/`closes_at` nullable for closed days)
- [x] Delivery zones (CRUD, `fee` drives the real checkout delivery fee)
- [x] Payment methods (5 toggles via `StoreSetting`)
- [x] All four converted to Livewire/Volt with persistence

### Dashboard
- [x] KPI cards (open orders, in preparation, ready, today total, avg prep time, revenue)
- [x] Recent orders list
- [x] Kitchen queue snippet
- [x] Top items today
- [x] Alerts (delayed orders, kitchen overload) — keys off `order_status_histories.changed_at`
- [x] Tables status snapshot (available / occupied / reserved)
- [~] Drivers currently active — shows an out-for-delivery order count as an honest proxy; there is no driver-online concept yet (needs Phase 6 `DriverStatus`)
- [x] Quick actions (kitchen / orders / tables / waitlist queue)
- [x] Today vs historical comparison (vs yesterday and vs 7-day average, badge hidden when there is no baseline)

## Phase 3 — Customer Ordering

The customer browses the menu, customizes items, and places an order from their phone or a tablet at the table. The system supports three order types: **dine-in** (mesa com QR / tablet), **delivery**, and **pickup**. No account required — guest checkout with name + phone only.

> **Route-shape drift (intentional):** this section was specced with multi-restaurant URLs (`/r/{slug}/…`).
> What shipped uses single-restaurant URLs under `/store` (`/store/menu`, `/store/cart`, `/store/checkout`,
> `/store/order/{token}`), consistent with the single-tenant shortcut used everywhere else. Revisit when
> multi-branch support (Phase 9) lands.

### 3.1 — Foundation (prerequisites)
- [x] `DeliveryType::DineIn` enum case (label "Mesa", no address, no delivery fee)
- [x] `accepts_dine_in` boolean flag on `restaurants` table + Restaurant model
- [x] Migration: `uuid` column on `dining_tables` (used in QR code link — prevents table enumeration)
- [x] Migration: `dining_table_id` (FK nullable) + `table_number` snapshot on `orders`
- [x] Migration: `dining_table_id` (FK nullable) + `delivery_type` intent on `carts`
- [x] Migration: `token` (UUID unique) on `orders` — public tracking link, no ID exposure
- [x] Migration: `waiter_calls` table (`restaurant_id`, `dining_table_id`, `status` pending/acknowledged, `called_at`, `acknowledged_at`)
- [x] Customer-facing layout (`resources/views/components/layouts/customer.blade.php`) — mobile-first, no sidebar

### 3.2 — Storefront & Menu
- [x] Public storefront route (no auth) — shipped as `GET /store/menu`, see drift note above
- [~] Restaurant header — logo + cart link only; **open/closed status and hours are not shown**
- [~] Category navigation — sticky pill tabs that filter server-side (`wire:click`), not smooth-scroll to section anchors
- [x] Product grid per category: image, name, price, "Adicionar" button
- [ ] Unavailable products greyed out with label (never hidden) — **diverges: the menu query calls `->available()`, so paused products are hidden entirely**
- [ ] Quick search (client-side Alpine filter, no reload)
- [x] Empty state when restaurant has no products

### 3.3 — Product Detail & Cart
- [x] Product detail modal: full description, addon groups, variants, quantity selector
- [x] Real-time price update as addons/variants are selected (`modalUnitPrice()`)
- [x] Required addon groups block add-to-cart until selected (min/max enforced server-side)
- [x] Cart (session-based for guests): add, update qty, remove item, order notes
- [x] Floating cart button with item count (`cartItemCount()`)
- [x] Cart page with item list, totals and checkout CTA (full page, not a drawer/overlay)
- [ ] Clear cart with confirmation

### 3.4 — Dine-In via QR / Tablet
> **Partially built: the data model is complete, the customer entry point is not.** Dine-in ordering works
> today by picking a table in checkout; the QR/kiosk flow does not exist.
- [ ] QR code link: `GET /mesa/{table:uuid}` → stores `table_id` in session → redirects to storefront
- [ ] Storefront auto-detects table session → sets order type to DineIn, shows table number
- [ ] Kiosk mode: `?kiosk=1` URL param locks order type to DineIn, hides delivery/pickup option
- [ ] "Chamar Garçom" button (shown only in dine-in session): creates `waiter_call` record — the `CreateWaiterCall` action exists but is not wired to any UI yet
- [x] Restaurant panel: waiter call widget with table number + acknowledge action (lives in the kitchen panel, `kitchen.index`) — currently unreachable in practice, since no call can be created

### 3.5 — Checkout
- [x] Order type selector: Dine-in / Delivery / Pickup
- [~] Dine-in: table is **chosen by the customer in checkout** (required), not read from a QR session
- [x] Delivery: address form + delivery zone select (zone `fee` drives the real delivery fee)
- [x] Pickup: no address needed, no fee
- [x] Guest info: name + phone
- [x] Order notes field
- [~] Server-side validation at placement: total recalculated, products verified available/not-archived, required addons enforced — **"restaurant must be open" is not checked**
- [x] `PlaceOrder` action: creates order with `pending_confirmation`, writes `order_status_histories`, sets `order.token`, clears cart, occupies the table and opens/joins its session for dine-in
- [x] Order confirmation → redirect to the tracking link by `token`

### 3.6 — Order Tracking (Customer)
- [x] Public tracking route resolved by `orders.token`, not the enumerable order number (no auth) — `GET /store/order/{token}`
- [x] Status steps visualization: Recebido → Confirmado → Em preparo → Pronto → Entregue
- [ ] Each completed step shows exact timestamp
- [ ] Live countdown: "Pronto às HH:MM" (polling via Livewire) — the page does not auto-refresh yet (no `wire:poll` is rendered)
- [ ] Delay message when ETA shifts: "Está demorando um pouco mais, novo horário: HH:MM"
- [x] Shareable link (guest-safe, no login required)

## Phase 4 — Kitchen & Order Flow (Restaurant Operations)

Orders flow from customer to kitchen to delivery.

### Order Management
- [x] Order list with filters (status, type, date, search) — Volt `orders.index`
- [x] In-progress view (Volt `orders.in-progress`)
- [x] History view (Volt `orders.history`)
- [x] Order detail view (Volt `orders.show`)
- [x] Confirm incoming order (`pending_confirmation` → `confirmed`) — `TransitionOrderStatus` action
- [x] Cancel order with required reason — `TransitionOrderStatus` action
- [x] Reject order (`pending_confirmation` → `canceled`) — labeled "Reject" in UI
- [x] Mark order as completed (dine-in: `ready_for_pickup` → `completed`; delivery: `delivered` → `completed`)
- [ ] Add / remove items on open dine-in orders
- [ ] Audio + visual alert for new incoming orders
- [ ] Auto-expire confirmation window

### Kitchen Panel
- [x] Kitchen queue with status counters (Volt `kitchen.index`)
- [ ] Polling refresh (30s) — the view advertises "atualiza a cada 30s", but no `wire:poll` is rendered yet
- [x] Mark order as `in_preparation` — `TransitionOrderStatus` action (kitchen role)
- [x] Mark order as `ready` (`ready_for_pickup`) — `TransitionOrderStatus` action (kitchen role)
- [ ] Per-order live countdown timer
- [ ] Item-level checklist (check off each item)
- [ ] Audio alert on new order
- [ ] Filter view (dine-in / delivery / pickup)
- [ ] Touch-friendly layout pass

### Customers (Restaurant view)
- [x] Customers list with order count, total spent, last order (Volt `customers.index`)
- [ ] Customer detail page
- [ ] Customer order history view

## Phase 5 — Order Tracking (Customer)

Customers see what is happening with their order.

> Overlaps [3.6](#36--order-tracking-customer). The static tracking page shipped with Phase 3; what remains
> here is the *live* half.

- [~] Real-time order status page — the page exists and is correct, but is static (no polling/broadcast)
- [ ] Live countdown timer (always decreasing)
- [ ] Status change notifications

## Phase 6 — Delivery Driver

The driver receives, accepts, and delivers orders.

> Shipped as a **shared queue**, not per-driver assignment: every `delivery` role user sees the same list and
> any of them can pick an order up. There is no `driver_id` on orders and no online/offline concept, which is
> why the dashboard's "drivers active" block is still a proxy.

- [~] Driver login + profile — login and the `delivery` role gate work; no driver profile screen
- [x] Incoming delivery requests list (ready-for-pickup queue, Volt `driver.index`)
- [~] Accept / decline delivery — "pick up" claims an order; there is no decline, and no assignment to claim *from*
- [x] Status updates: picked up (`ready_for_pickup` → `out_for_delivery`) and delivered, both via `TransitionOrderStatus` with history + milestone timestamps
- [ ] Customer-facing ETA
- [ ] Driver availability toggle

## Phase 7 — ETA Intelligence

The system learns from historical data to produce accurate estimates.

- [ ] Collect prep time per product (capture `created_at` → `ready_at`)
- [ ] Dynamic prep time calculation (historical average)
- [ ] Delivery ETA based on distance + demand
- [ ] ETA recalculation as orders progress

## Phase 8 — Table Tab & Bill Splitting

Groups dining in need to split their bill.

- [x] Group tab per table — `TableSession` opens on the table's first dine-in order and is reused by later
  orders at that table (delivered as part of Phase 2 close-out)
- [ ] Items linked to specific person in the group
- [ ] Split options (equal, by item)
- [~] Tab closing flow — staff close the session manually ("Fechar mesa"), blocked while any order under the
  session is still active. Closing on payment is out of reach until a payment gateway exists

## Phase 9 — Realtime & Polish

After the core product is stable.

- [~] Reports placeholder (Volt `reports.index` — 6 cards marked "Em desenvolvimento")
- [ ] Reports: sales, top items, peak hours, prep time, dining-room performance, cancellations
- [ ] Replace polling with broadcasting (Reverb / Pusher) for orders, kitchen, tracking
- [ ] Order history + reorder for customers
- [ ] Promotions and coupons
- [ ] Scheduled orders
- [ ] Multi-branch support
