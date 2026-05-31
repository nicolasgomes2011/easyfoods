# EasyFoods — Roadmap

> **Reading guide**
> - `[x]` = implemented and merged on `main`
> - `[~]` = partially implemented (UI/structure exists, business rules or actions pending)
> - `[ ]` = not started
>
> Cross-cutting work is tracked in [ADJUSTMENTS.md](ADJUSTMENTS.md) (cleanup of fake data, route layer, env) and [TESTING_ROADMAP.md](TESTING_ROADMAP.md) (test coverage).

---

## Phase 0 — Adjustments & Cleanup (active)

Mission: remove every piece of demo/fake data so the panel reflects only what was registered through the UI. Full plan in [ADJUSTMENTS.md](ADJUSTMENTS.md).

- [ ] Remove `OrderSeeder`, `CatalogSeeder`, `RestaurantSeeder` from `DatabaseSeeder`
- [ ] Strip `UserSeeder` down to the single admin (Nicolas)
- [ ] Delete any hardcoded sample arrays in Livewire/Volt components
- [ ] Drop the duplicate `routes/web.php` (only `default_routes_web.php` is loaded)
- [ ] Add a one-time migration or artisan command to wipe demo rows from existing DBs
- [ ] Verify every panel page renders gracefully with an empty DB (empty states, no errors)

## Phase 1 — Foundation

Core infrastructure. Nothing works without this.

- [x] Authentication: login, register, password reset, email verification, 2FA (Volt)
- [x] Role & permission middleware (`admin`, `manager`, `attendant`, `kitchen`, `delivery`)
- [x] Database schema: restaurants, users, customers, products, categories, addons, variants, carts, orders, order items, payments, status history, dining tables, operating hours, delivery zones, store settings
- [x] Base Livewire layout (`components.layouts.app`) for the restaurant panel
- [ ] Multi-tenant scoping (restaurant_id) enforced at query level (Global scope or policy)
- [ ] Customer-facing layout
- [ ] Driver-facing layout

## Phase 2 — Restaurant Backoffice (Core Operations)

The restaurant needs to configure and operate.

### Product Catalog
- [x] Product list with search and filter (Livewire `Catalog\ProductList`)
- [x] Create product form (Livewire `Catalog\ProductForm`)
- [x] Edit product form
- [x] Addon group management (Volt `catalog.addons`)
- [ ] Category CRUD (route exists, no Volt component yet)
- [ ] Reorder categories
- [ ] Archive / pause product
- [ ] Delete product with guard against open orders
- [ ] Manual availability toggle
- [ ] Product variants (Small / Medium / Large)
- [ ] Product image upload + resize

### Dining (Tables)
- [x] Tables list with create/edit/delete (Volt `dining.tables`)
- [x] Table status counters (free/occupied/reserved)
- [~] Waitlist queue (Volt `dining.queue` exists — needs add/seat/remove actions verified)
- [ ] Auto-transition `occupied` when an order is placed at the table
- [ ] Table session lifecycle (start on first order, close on payment)
- [ ] Guard: cannot delete a table with an active session

### Employee Management
- [ ] Staff invite via email
- [ ] First-login password set
- [ ] Deactivate / reactivate accounts
- [ ] Role assignment UI
- [ ] Staff directory list

### Settings
- [~] Store info view (blade exists, no actions wired)
- [~] Operating hours view (blade exists)
- [~] Delivery zones view (blade exists)
- [~] Payment methods view (blade exists)
- [ ] All four converted to Livewire/Volt with persistence

### Dashboard
- [x] KPI cards (open orders, in preparation, ready, today total, avg prep time, revenue)
- [x] Recent orders list
- [x] Kitchen queue snippet
- [x] Top items today
- [x] Alerts (delayed orders, kitchen overload)
- [ ] Tables status snapshot (available / occupied / waiting)
- [ ] Drivers currently active
- [ ] Quick actions (go to kitchen / orders / tables)
- [ ] Today vs historical comparison

## Phase 3 — Customer Ordering

The customer browses the menu, customizes items, and places an order from their phone or a tablet at the table. The system supports three order types: **dine-in** (mesa com QR / tablet), **delivery**, and **pickup**. No account required — guest checkout with name + phone only.

### 3.1 — Foundation (prerequisites)
- [ ] `DeliveryType::DineIn` enum case (label "Mesa", no address, no delivery fee)
- [ ] `accepts_dine_in` boolean flag on `restaurants` table + Restaurant model
- [ ] Migration: `uuid` column on `dining_tables` (used in QR code link — prevents table enumeration)
- [ ] Migration: `dining_table_id` (FK nullable) + `table_number` snapshot on `orders`
- [ ] Migration: `dining_table_id` (FK nullable) + `delivery_type` intent on `carts`
- [ ] Migration: `token` (UUID unique) on `orders` — public tracking link, no ID exposure
- [ ] Migration: `waiter_calls` table (`restaurant_id`, `dining_table_id`, `status` pending/acknowledged, `called_at`, `acknowledged_at`)
- [ ] Customer-facing layout (`resources/views/components/layouts/customer.blade.php`) — mobile-first, no sidebar, open/closed header

### 3.2 — Storefront & Menu
- [ ] Public route `GET /r/{restaurant:slug}` → `storefront.menu` (no auth)
- [ ] Restaurant header: name, logo, open/closed status + hours
- [ ] Category navigation: sticky tabs, smooth-scroll to section
- [ ] Product grid per category: image, name, price, "Adicionar" button
- [ ] Unavailable products greyed out with label (never hidden)
- [ ] Quick search (client-side Alpine filter, no reload)
- [ ] Empty state when restaurant has no products

### 3.3 — Product Detail & Cart
- [ ] Product detail modal: full description, addon groups, variants, quantity selector
- [ ] Real-time price update as addons are selected
- [ ] Required addon groups block add-to-cart until selected
- [ ] Cart (session-based for guests): add, update qty, remove item, order notes
- [ ] Floating cart button with item count + total (always visible)
- [ ] Cart drawer/overlay: item list, totals, "Ir para checkout" CTA
- [ ] Clear cart with confirmation

### 3.4 — Dine-In via QR / Tablet
- [ ] QR code link: `GET /mesa/{table:uuid}` → stores `table_id` in session → redirects to storefront
- [ ] Storefront auto-detects table session → sets order type to DineIn, shows table number
- [ ] Kiosk mode: `?kiosk=1` URL param locks order type to DineIn, hides delivery/pickup option
- [ ] "Chamar Garçom" button (shown only in dine-in session): creates `waiter_call` record
- [ ] Restaurant panel (balcão): waiter call alert widget polling 30s, with table number + acknowledge action

### 3.5 — Checkout
- [ ] Order type selector: Dine-in / Delivery / Pickup (conditioned by `accepts_*` flags on restaurant)
- [ ] Dine-in: shows table number from session (read-only)
- [ ] Delivery: address form (street, number, complement, neighborhood, city, zip)
- [ ] Pickup: no address needed
- [ ] Guest info: name + phone (optional for dine-in, required for delivery)
- [ ] Order notes field
- [ ] Server-side validation at placement: total recalculated, products verified available, restaurant must be open, required addons selected
- [ ] `PlaceOrder` action: creates order with `pending_confirmation`, writes `order_status_histories`, sets `order.token`, clears cart
- [ ] Order confirmation screen: order number + link to tracking

### 3.6 — Order Tracking (Customer)
- [ ] Public tracking route: `GET /r/{slug}/pedido/{order:token}` (no auth)
- [ ] Status steps visualization: Recebido → Confirmado → Em preparo → Pronto → Entregue
- [ ] Each completed step shows exact timestamp
- [ ] Live countdown: "Pronto às HH:MM" (polling 10s via Livewire)
- [ ] Delay message when ETA shifts: "Está demorando um pouco mais, novo horário: HH:MM"
- [ ] Shareable link (guest-safe, no login required)

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
- [x] Polling refresh (30s)
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

- [ ] Real-time order status page
- [ ] Live countdown timer (always decreasing)
- [ ] Status change notifications

## Phase 6 — Delivery Driver

The driver receives, accepts, and delivers orders.

- [ ] Driver login + profile
- [ ] Incoming delivery requests list
- [ ] Accept / decline delivery
- [ ] Status updates: picked up, on the way, delivered
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

- [ ] Group tab per table
- [ ] Items linked to specific person in the group
- [ ] Split options (equal, by item)
- [ ] Tab closing flow

## Phase 9 — Realtime & Polish

After the core product is stable.

- [~] Reports placeholder (Volt `reports.index` — 6 cards marked "Em desenvolvimento")
- [ ] Reports: sales, top items, peak hours, prep time, dining-room performance, cancellations
- [ ] Replace polling with broadcasting (Reverb / Pusher) for orders, kitchen, tracking
- [ ] Order history + reorder for customers
- [ ] Promotions and coupons
- [ ] Scheduled orders
- [ ] Multi-branch support
