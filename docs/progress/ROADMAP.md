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

The customer needs to be able to browse, customize, and place an order.

- [ ] Public storefront / menu listing
- [ ] Product detail with customization
- [ ] Cart (add, update, remove, persist)
- [ ] Checkout flow (dine-in table selection or delivery address)
- [ ] Server-side total recalculation
- [ ] Order placement → `pending_confirmation`
- [ ] Order confirmation screen

## Phase 4 — Kitchen & Order Flow (Restaurant Operations)

Orders flow from customer to kitchen to delivery.

### Order Management
- [x] Order list with filters (status, type, date, search) — Volt `orders.index`
- [x] In-progress view (Volt `orders.in-progress`)
- [x] History view (Volt `orders.history`)
- [x] Order detail view (Volt `orders.show`)
- [ ] Confirm incoming order (`pending_confirmation` → `confirmed`) action
- [ ] Cancel order with required reason
- [ ] Reject order with required reason
- [ ] Mark order as completed
- [ ] Add / remove items on open dine-in orders
- [ ] Audio + visual alert for new orders
- [ ] Auto-expire confirmation window

### Kitchen Panel
- [x] Kitchen queue with status counters (Volt `kitchen.index`)
- [x] Polling refresh (30s)
- [ ] Mark order as `in_preparation` action
- [ ] Mark order as `ready` action
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
