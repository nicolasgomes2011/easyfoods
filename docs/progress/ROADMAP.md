# EasyFoods — Roadmap

> **Reading guide**
> - `[x]` = implemented and merged on `main`
> - `[~]` = partially implemented (UI/structure exists, business rules or actions pending)
> - `[ ]` = not started
>
> Cross-cutting work is tracked in [ADJUSTMENTS.md](ADJUSTMENTS.md) (cleanup of fake data, route layer, env) and [TESTING_ROADMAP.md](TESTING_ROADMAP.md) (test coverage).

**Last reconciled against the code: 2026-11-18** (`main` @ `de95d97`, 758 tests). Checkboxes below
were re-derived from the actual source, not from the previous plan — where the shipped behavior differs from
what a bullet specced, the bullet says so instead of being silently ticked. Open items link the Linear card
(`EF-x`, team EasyFoods) that carries them; a card with an open pull request is still `[ ]`/`[~]` here until it is merged.

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

- [x] Authentication: login, password reset, email verification, 2FA (Volt). Self-registration and the old customer account area were removed (EF-13); staff accounts come from invites
- [x] Role & permission middleware (`admin`, `manager`, `attendant`, `kitchen`, `delivery`) — single access matrix in `UserRole::allowedFor()` for routes + menu + home screen per role (EF-10). Authorization inside screens (create/delete table, `OrderPolicy` on order actions): EF-63
- [x] Database schema: restaurants, users, customers, products, categories, addons, variants, carts, orders, order items, payments, status history, dining tables, operating hours, delivery zones, store settings, ingredients, waste entries, prep samples, table session guests
- [x] Base Livewire layout (`components.layouts.app`) for the restaurant panel
- [x] Multi-tenant scoping (restaurant_id) enforced at query level — staff belong to a restaurant and `CurrentRestaurant` follows the logged-in user; restaurant-owned models use the `BelongsToRestaurant` global scope; policies and id lookups refuse another restaurant's rows; a two-restaurant leak suite covers every screen (EF-81 to EF-84). The public storefront still serves one restaurant (see the Phase 3 drift note)
- [x] Customer-facing layout (`components.layouts.customer`, mobile-first)
- [~] Driver-facing layout — driver panel reuses the admin/app layout; no dedicated driver shell yet
- [x] CI: GitHub Actions runs the guard (debug calls, private files) and the full suite on SQLite and MySQL 8 on every pull request (EF-11)
- [x] App runs in Brasília time and Brazilian Portuguese (EF-7)

## Phase 2 — Restaurant Backoffice (Core Operations)

The restaurant needs to configure and operate.

### Product Catalog
- [x] Product list with search and filter (Livewire `Catalog\ProductList`)
- [x] Create product form (Livewire `Catalog\ProductForm`)
- [x] Edit product form
- [x] Addon group management (Volt `catalog.addons`)
- [x] Category CRUD (Volt `catalog.categories`) — a name whose slug is already used in the restaurant is refused (EF-69)
- [x] Reorder categories (moveUp/moveDown, restaurant-scoped)
- [x] Archive / pause product (`archived_at`, distinct from availability pause)
- [x] Delete product with guard against open orders (active-order check, not "any order")
- [x] Manual availability toggle
- [x] Product variants (Small / Medium / Large) — repeatable rows in `ProductForm`
- [x] Product image upload + resize (GD, capped at 1200px longest side)
- [x] Base prep time per product (`prep_minutes`), used by the ready-at estimate (EF-15)

### Ingredients & Waste
- [x] Ingredients registry in the catalog (Volt `catalog.ingredients`) (EF-39)
- [x] Kitchen waste log with append-only corrections (Volt `waste.index`) (EF-40)
- [x] Waste report by period, ingredient, category and reason (Volt `reports.waste`) (EF-41)
- [ ] Plate waste from the dining room (EF-33)

### Dining (Tables)
- [x] Tables list with create/edit/delete (Volt `dining.tables`)
- [x] Table status counters (free/occupied/reserved)
- [x] Waitlist queue (Volt `dining.queue` — add/seat/remove, FIFO, tenant-scoped)
- [x] Auto-transition `occupied` when an order is placed at the table
- [x] Table session lifecycle (opens on first dine-in order, staff close manually — stand-in for "on payment" since no payment gateway exists yet)
- [x] Guard: cannot delete a table with an active session
- [x] Server re-checks table state: a table with an open tab only leaves through "Fechar mesa", and seating from the waitlist locks the table row (EF-53)
- [x] QR code per table in the admin: show, download PNG/SVG, batch print (EF-35)
- [x] Table tab screen (Volt `dining.tab`): session orders, total, paid and pending, people at the table (EF-44, EF-45)

### Employee Management
- [x] Staff invite via email (`InviteStaffMember` action; UI falls back to a copyable link if no mail driver). A dedicated invite e-mail instead of the password-reset one: EF-24
- [x] First-login password set (password-reset routes restored in `routes/auth.php`, commit `becc52e`)
- [x] Deactivate / reactivate accounts (`is_active` + `RoleMiddleware` blocks inactive users; login shows "Sua conta foi desativada." and an open session is logged out — EF-10)
- [x] Role assignment UI
- [x] Staff directory list (Volt `users.index`)

### Settings
- [x] Store info (Volt, persisted)
- [x] Operating hours (7-day grid, `opens_at`/`closes_at` nullable for closed days)
- [x] Delivery zones (CRUD, `fee` drives the real checkout delivery fee; only active zones of the restaurant are accepted — EF-51)
- [x] Payment methods (5 toggles via `StoreSetting`; the checkout only offers the enabled ones — EF-4)
- [x] All four converted to Livewire/Volt with persistence
- [x] Confirmation window: orders nobody confirms in time are canceled by the scheduler (`ExpireUnconfirmedOrders`) (EF-20)

### Dashboard
- [x] KPI cards (open orders, in preparation, ready, today total, avg prep time, revenue)
- [x] Recent orders list
- [x] Kitchen queue snippet
- [x] Top items today
- [x] Alerts (delayed orders, kitchen overload) — keys off `order_status_histories.changed_at`
- [x] Tables status snapshot (available / occupied / reserved)
- [x] Drivers currently active — available, paused and busy drivers with their orders (EF-38)
- [x] Quick actions (kitchen / orders / tables / waitlist queue)
- [x] Today vs historical comparison (vs yesterday and vs 7-day average, badge hidden when there is no baseline)

## Phase 3 — Customer Ordering

The customer browses the menu, customizes items, and places an order from their phone or a tablet at the table. The system supports three order types: **dine-in** (mesa com QR / tablet), **delivery**, and **pickup**. No account required — guest checkout with name + phone only.

> **Route-shape drift (intentional):** this section was specced with multi-restaurant URLs (`/r/{slug}/…`).
> What shipped uses single-restaurant URLs under `/store` (`/store/menu`, `/store/cart`, `/store/checkout`,
> `/store/order/{token}`) plus `/mesa/{uuid}` for the table QR code. The panel is multi-tenant (Phase 1),
> but the storefront serves one restaurant. Revisit when multi-branch support (Phase 9) lands.

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
- [x] Restaurant header — logo, cart link, open/closed status and today's hours (EF-54)
- [x] Category navigation — every category is a section and the sticky tabs scroll to it (EF-56)
- [x] Product grid per category: image, name, price, "Adicionar" button
- [x] Unavailable products greyed out with label (never hidden) (EF-55)
- [x] Quick search (client-side Alpine filter, no reload) (EF-57)
- [x] "Mais pedidos" section (EF-31)
- [x] Empty state when restaurant has no products

### 3.3 — Product Detail & Cart
- [x] Product detail modal: full description, addon groups, variants, quantity selector
- [x] Real-time price update as addons/variants are selected (`modalUnitPrice()`)
- [x] Required addon groups block add-to-cart until selected (min/max enforced server-side)
- [x] Cart (session-based for guests): add, update qty, remove item, order notes — quantities validated on the server (EF-77); a different note stays on its own line (EF-78)
- [x] Floating cart button with item count (`cartItemCount()`)
- [x] Cart page with item list, totals and checkout CTA (full page, not a drawer/overlay)
- [x] Clear cart with confirmation (EF-58)

### 3.4 — Dine-In via QR / Tablet
- [x] QR code link: `GET /mesa/{table:uuid}` → stores the table in the session → redirects to the menu (EF-34)
- [x] Storefront detects the table session → order type fixed to DineIn, "Mesa N" shown on the menu and checkout (EF-34)
- [x] Kiosk mode: `?kiosk=1` keeps the order type on DineIn and hides delivery/pickup, with no way out of table mode (EF-34)
- [ ] "Chamar Garçom" button (shown only in dine-in session): creates `waiter_call` record — the `CreateWaiterCall` action exists but is not wired to any UI yet (EF-18)
- [x] Restaurant panel: waiter call widget with table number + acknowledge action (lives in the kitchen panel, `kitchen.index`) — currently unreachable in practice, since no call can be created

### 3.5 — Checkout
- [x] Order type selector: Dine-in / Delivery / Pickup — only the types the store takes, starting on the first one (EF-68)
- [x] Dine-in: table fixed by the QR session, or chosen by the customer in checkout (free or occupied tables only — EF-6)
- [x] Delivery: address form + delivery zone select (zone `fee` drives the real delivery fee, computed on the server — EF-51)
- [x] Pickup: no address needed, no fee
- [x] Guest info: name + phone — the phone registers the restaurant's customer (EF-9)
- [x] Order notes field
- [x] Payment method: only the enabled ones; cash takes an optional "change for" amount; one pending `Payment` per order (EF-4). Showing method/change on the kitchen and driver cards: EF-60
- [x] Server-side validation at placement: total recalculated, products verified available/not-archived, required addons enforced, store open and order type accepted (EF-5)
- [x] `PlaceOrder` action: creates order with `pending_confirmation`, writes `order_status_histories`, sets `order.token`, clears cart, occupies the table and opens/joins its session for dine-in
- [x] Order confirmation → redirect to the tracking link by `token`

### 3.6 — Order Tracking (Customer)
- [x] Public tracking route resolved by `orders.token`, not the enumerable order number (no auth) — `GET /store/order/{token}`
- [x] Status steps visualization per order type, with the cancel reason when canceled (EF-16)
- [x] Each completed step shows exact timestamp (EF-16)
- [x] "Pronto às HH:MM" clock time while the kitchen works on the order (EF-15, learned estimate EF-26). The page refreshes every 10s (`wire:poll.10s`) and stops polling once the order reaches a final status
- [x] Delay message when ETA shifts: "Está demorando um pouquinho mais, novo horário: HH:MM"
- [x] Shareable link (guest-safe, no login required)

## Phase 4 — Kitchen & Order Flow (Restaurant Operations)

Orders flow from customer to kitchen to delivery.

### Order Management
- [x] Order list with filters (status, type, date, search) — Volt `orders.index`
- [x] In-progress view (Volt `orders.in-progress`)
- [x] History view (Volt `orders.history`)
- [x] Order detail view (Volt `orders.show`) — with "Marcar como pago" for a pending payment (EF-4)
- [x] Confirm incoming order (`pending_confirmation` → `confirmed`) — `TransitionOrderStatus` action
- [x] Cancel order with required reason — `TransitionOrderStatus` action
- [x] Reject order (`pending_confirmation` → `canceled`) — labeled "Reject" in UI
- [x] Mark order as completed (dine-in: `ready_for_pickup` → `completed`; delivery: `delivered` → `completed`)
- [x] Add / remove items on open dine-in orders (EF-21)
- [x] Audio + visual alert for new incoming orders (EF-19)
- [x] Auto-expire confirmation window (EF-20)
- [ ] Create an order from the orders screen (EF-65)

### Kitchen Panel
- [x] Kitchen queue with status counters (Volt `kitchen.index`), grouped by status (EF-42)
- [x] Polling refresh (30s) — `wire:poll.30s` on the component root (same on in-progress orders, waitlist queue and driver queue)
- [x] Mark order as `in_preparation` — `TransitionOrderStatus` action (kitchen role)
- [x] Mark order as `ready` (`ready_for_pickup`) — `TransitionOrderStatus` action (kitchen role), only once every item is ticked (EF-43)
- [x] Per-order prep timer, red past the estimate (EF-15)
- [x] Item-level checklist (check off each item) (EF-43)
- [x] Audio alert on new order (EF-19)
- [x] Filter view (dine-in / delivery / pickup) (EF-42)
- [x] Touch-friendly layout pass (EF-42)
- [x] Item addons, notes and items removed after confirmation shown on the card (EF-72, EF-73)

### Customers (Restaurant view)
- [x] Customers list with order count, total spent, last order (Volt `customers.index`) — per restaurant, filled by the checkout (EF-9)
- [ ] Customer detail page (EF-23)
- [ ] Customer order history view (EF-23)

## Phase 5 — Order Tracking (Customer)

Customers see what is happening with their order.

> Overlaps [3.6](#36--order-tracking-customer). The tracking page shipped with Phase 3; what remains
> here is the *live* half.

- [~] Real-time order status page — refreshes by polling every 10s until the order is final; no broadcasting yet (EF-29)
- [ ] Live countdown timer (always decreasing) — the page shows the clock time ("Pronto às HH:MM"), not a ticking timer
- [ ] Status change notifications

## Phase 6 — Delivery Driver

The driver receives, accepts, and delivers orders.

> Orders ready for delivery are offered to available drivers; a driver accepts or declines, and only the
> driver who accepted (`orders.driver_id`) can deliver it.

- [~] Driver login + profile — login and the `delivery` role gate work; no driver profile screen
- [x] Incoming delivery requests list (ready-for-pickup queue, Volt `driver.index`) with call and map links (EF-38)
- [x] Accept / decline delivery (EF-36)
- [x] Status updates: picked up (`ready_for_pickup` → `out_for_delivery`) and delivered, both via `TransitionOrderStatus` with history + milestone timestamps
- [ ] Customer-facing delivery ETA (EF-76)
- [x] Driver availability toggle — pause and resume offers (EF-37)

## Phase 7 — ETA Intelligence

The system learns from historical data to produce accurate estimates.

- [x] Collect prep time per product — preparation / departure milestones on orders (EF-14) and prep samples per item (EF-26)
- [x] Dynamic prep time calculation — learned from real kitchen history and current load, falling back to the product's base prep time (EF-15, EF-26)
- [ ] Delivery ETA based on distance + demand (EF-76)
- [ ] ETA recalculation as orders progress (EF-76)

## Phase 8 — Table Tab & Bill Splitting

Groups dining in need to split their bill.

- [x] Group tab per table — `TableSession` opens on the table's first dine-in order and is reused by later
  orders at that table; the tab screen shows its orders, total, paid and pending (EF-44)
- [x] Items linked to specific person in the group (EF-45)
- [ ] Split options (equal, by item) (EF-46)
- [~] Tab closing flow — staff close the session manually ("Fechar mesa"), blocked while any order under the
  session is still active. Partial payments and closing on payment: EF-46

## Phase 9 — Realtime & Polish

After the core product is stable.

- [~] Reports (Volt `reports.index`) — sales by period with average ticket (EF-47), top products and peak hours (EF-48) and waste (EF-41) are live, with CSV export; prep time, dining-room performance and cancellations are still marked "Em desenvolvimento"
- [ ] Replace polling with broadcasting (Reverb / Pusher) for orders, kitchen, tracking (EF-29)
- [ ] Order history + reorder for customers
- [ ] Promotions and coupons
- [ ] Scheduled orders
- [ ] Multi-branch support
