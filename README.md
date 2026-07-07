# EasyFoods

A complete restaurant **operations and ordering platform** — not a marketplace, not a POS, not a
payment gateway. It runs the full loop for a single restaurant (or chain):

**customer orders → restaurant prepares → customer tracks.**

## The problem it solves

Restaurants today juggle fragmented tools: one system for orders, another for the kitchen, a
notebook for tables, phone calls for delivery. Customers order through apps that give no real
visibility into what's happening with their food. Drivers coordinate by phone.

EasyFoods replaces all of that with one integrated system connecting three parties — **restaurant
staff**, **customers**, and **delivery drivers** — around a shared, real-time source of truth.

## Who uses it

| Actor | Interface | Goal |
|-------|-----------|------|
| Restaurant Admin | Backoffice panel | Configure products, staff, tables, hours |
| Manager / Counter Staff | Backoffice panel | Confirm orders, run the floor, coordinate with kitchen |
| Kitchen Staff | Kitchen panel | See incoming orders, track prep, mark items done — fast, large touch targets |
| Customer | Storefront + tracking page | Browse, customize, order, and know exactly when food arrives — no account required |
| Delivery Driver | Driver panel | Receive, accept, and complete deliveries from a phone |

## Design principles

- **Honest over optimistic** — real estimates, not best-case numbers
- **Fast for the busiest user** — kitchen and counter staff are always under pressure; 1–2 taps per action
- **Simple for the customer** — three taps to add a product, minimal checkout, guest-first
- **No hidden state** — every status change is visible to the right party immediately
- **Data over guesses** — ETAs come from historical prep/delivery data, not static numbers left untouched

## What it explicitly is *not*

- Not a POS with fiscal/tax printing (NF, NFC-e)
- Not a payment gateway (payment integration is a future phase)
- Not a marketplace connecting multiple restaurants to customers
- Not a fleet-logistics platform managing drivers independently

Full product vision, personas, and non-goals: [`docs/scope/product-vision.md`](docs/scope/product-vision.md).

## Feature scope

The MVP proves the core loop end-to-end: auth + roles, product catalog (categories, addons,
variants), customer storefront with cart/checkout (dine-in, delivery, or pickup), a kitchen queue
with status transitions, and a live customer order-tracking page.

Beyond MVP, the roadmap adds (in order): a delivery-driver panel, an ETA intelligence engine that
learns prep/delivery times from history instead of using static numbers, table-tab & bill
splitting, and reporting/analytics. Explicitly deferred: payment gateway integration, SMS/WhatsApp
notifications, multi-branch support, coupons, and fiscal document printing.

Full breakdown: [`docs/scope/mvp-vs-future.md`](docs/scope/mvp-vs-future.md).

## Current status

Development is active and moves fast — the authoritative, continuously-updated status lives in
[`docs/progress/ROADMAP.md`](docs/progress/ROADMAP.md) (checkbox-per-feature, per phase). Rough
picture as of this writing:

- **Phase 0 (cleanup)** — done: demo seeders removed, panel reflects only real data.
- **Phase 1 (foundation)** — auth/roles/schema/layout in place; multi-tenant query scoping is
  applied explicitly per-query (not a global scope yet) — safe for a single restaurant.
- **Phase 2 (backoffice)** — catalog, categories, addons, dining tables, employee management, and
  settings are largely wired to real persistence; a handful of gaps (category reorder, product
  variants UI, image upload, dashboard widgets, waitlist queue) are being closed out.
- **Phase 3 (customer ordering)** and **Phase 4 (kitchen & order flow)** — storefront, cart,
  checkout, order placement, and kitchen/order status transitions are implemented and covered by
  real-HTTP tests (`app/Actions/Orders/TransitionOrderStatus`, `PlaceOrder`).
- **Phase 6 (delivery driver)** — driver panel with pickup/deliver actions implemented.
- **Phases 5, 7, 8, 9** (customer tracking polish, ETA intelligence, table-tab splitting,
  realtime/reports) — not yet started.

## Architecture

**Stack:** Laravel 12 (PHP 8.2+) · Livewire 3 + Volt (single-file components) · Alpine.js (UI
interactivity only, never business logic) · Tailwind CSS 4 · SQLite (dev) / MySQL (prod target) ·
Vite.

```
app/
  Actions/         Business operations (PlaceOrder, TransitionOrderStatus, CreateWaiterCall, InviteStaffMember)
  Enums/           Behavior-carrying enums (OrderStatus, PaymentStatus, DeliveryType, UserRole, ...)
  Http/Middleware/ RoleMiddleware — role-based route gating
  Livewire/        Class-based components (Dashboard, Catalog/*, Auth/*) for richer multi-action screens
  Models/          Eloquent models + relationships
  Policies/        Authorization (OrderPolicy, ProductPolicy, UserPolicy)

resources/views/livewire/   Volt single-file components (kitchen, orders, dining, store, driver, settings, ...)
routes/
  default_routes_web.php    actual web entry point loaded by bootstrap/app.php
  admin.php, customer.php, auth.php
  web.php                   legacy/unused — do not add routes here

database/migrations/        schema (36+ migrations)
tests/Feature/, tests/Unit/ PHPUnit — Auth, Catalog, Dining, Driver, Order, Settings, Store
docs/
  scope/     product scope by actor/module — what the platform promises to deliver
  progress/  ROADMAP.md (live status) + ADJUSTMENTS.md + TESTING_ROADMAP.md
  memory/    project memory (architecture, business rules, decisions, known issues, active work)
```

Two Livewire styles are both in active use: **Volt** for a single focused screen, **class-based**
components when logic grows or is reused across views.

### Domain model (high level)

- **Orders** — `Order`, `OrderItem`, `OrderItemAddon`, `OrderStatusHistory` (append-only), `Payment`.
  Status transitions always go through `TransitionOrderStatus` and the `OrderStatus` enum;
  `order_status` and `payment_status` are independent fields; order items freeze product
  name/price at order time (never read live catalog prices for historical orders).
- **Catalog** — `Product`, `ProductVariant`, `Category`, `AddonGroup`, `AddonOption`.
- **Dining** — `DiningTable`, `WaiterCall`, `WaitlistEntry`.
- **Customer-facing** — `Cart`, `CartItem`, `CartItemAddon`, `Customer`, `CustomerAddress` (guest
  checkout by default — name + phone, no account required).
- **Restaurant config** — `Restaurant`, `OperatingHour`, `DeliveryZone`, `StoreSetting`.
- **Identity** — `User` + `UserRole` enum (admin, manager, attendant, kitchen, delivery), gated by
  `RoleMiddleware`.

### Key invariants

1. Every order status change goes through the `OrderStatus` enum and appends to
   `order_status_histories` — never mutate `orders.status` directly.
2. Order items store **frozen** price/name snapshots, independent of later catalog edits.
3. `order_status` and `payment_status` are tracked and updated independently.
4. History tables are append-only.
5. Tenant scoping today is **explicit per query** (`Restaurant::query()->value('id')`), not a
   global scope — correct only while a single restaurant exists. See
   [`docs/memory/business-rules/tenant-isolation.md`](docs/memory/business-rules/tenant-isolation.md).

## Getting started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

npm run build   # or `npm run dev` for HMR
composer run dev   # serves app + queue worker + logs + vite, concurrently
```

The app is built for [Laravel Herd](https://herd.laravel.com) locally; `composer run dev` works
without it. Default DB is SQLite (`DB_CONNECTION=sqlite`); MySQL is the production target — avoid
DB-specific SQL (e.g. `HAVING` on non-aggregate queries works on MySQL but not SQLite).

To wipe demo/seed data from an existing database: `php artisan demo:purge --force`.

## Testing

```bash
php artisan test
```

Feature tests hit real HTTP routes (not just `Livewire::test()`) for anything route- or
guard-sensitive — several real bugs (missing auth guards, IDOR via enumerable order numbers, SQLite
`HAVING` incompatibility) only surfaced under a real request, not the Livewire test harness.

## Documentation map

| Need | Where |
|------|-------|
| Full product scope, by actor | `docs/scope/` (start at `docs/scope/README.md`) |
| What ships now vs. later | `docs/scope/mvp-vs-future.md` |
| Live implementation status | `docs/progress/ROADMAP.md` |
| Architecture, business rules, decisions, known issues | `docs/memory/` (start at `docs/memory/system-overview.md`) |

## Development workflow

- One branch per feature/phase; never commit directly to `main`.
- Every feature and non-obvious line of code is documented as it's built.
- Commit messages describe exactly what changed and why.
