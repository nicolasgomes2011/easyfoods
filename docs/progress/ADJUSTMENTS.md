# Adjustments & Cleanup Roadmap

> **Mission:** the panel must display only data registered through the UI. No demo restaurant, no sample products, no fake orders, no demo users — only the admin account.
>
> This is the priority before continuing feature work in Phase 2+.

## Status legend
- `[x]` done
- `[~]` partial
- `[ ]` pending

---

## 1. Fake/Seed Data Removal (priority)

### 1.1 Seeders
Files to modify or delete in `database/seeders/`:

- [ ] **`OrderSeeder.php`** — delete entirely. Creates 8 fake orders + 5 fake customers + payments + status history.
- [ ] **`CatalogSeeder.php`** — delete entirely. Creates the "Hambúrguer Artesanal" sample product with addon group.
- [ ] **`RestaurantSeeder.php`** — delete entirely. Creates "EasyFoods Demo" restaurant, operating hours, two delivery zones, and store settings.
- [ ] **`UserSeeder.php`** — strip down to a single admin entry (Nicolas). Remove `Admin`, `Gerente Demo`, `Atendente Demo`, `Cozinha Demo`, `Entregador Demo`.
- [ ] **`DatabaseSeeder.php`** — call list should be only `UserSeeder::class` (or remove the call entirely if running `db:seed` is no longer expected).

### 1.2 Factories
- [ ] Audit `database/factories/` for factories used only by deleted seeders. Keep factories needed by tests; remove ones with no remaining caller.

### 1.3 Hardcoded sample data inside components/blades
The components themselves already query from the DB (good), but a few files contain hardcoded arrays that are visual-only ("Em desenvolvimento") and may confuse a user on an empty install:

- [ ] `resources/views/livewire/reports/index.blade.php` — 6 hardcoded report cards. Either keep with clearer "coming soon" treatment, or remove entirely until reports are real.
- [ ] Sweep every Volt/Livewire `@php` block and component class for `// fake`, `// demo`, `// TODO remove`, or inline arrays that look like sample data and remove them.

### 1.4 Existing DB cleanup
For databases that already received the seed data:

- [ ] Create artisan command `php artisan demo:purge` that truncates: `orders`, `order_items`, `order_item_addons`, `order_status_history`, `payments`, `customers`, `customer_addresses`, `carts`, `cart_items`, `cart_item_addons`, `products`, `product_variants`, `addon_groups`, `addon_options`, `categories`, `delivery_zones`, `operating_hours`, `store_settings`, `restaurants`. Preserves the admin user.
- [ ] Document in `ONBOARDING.md` / `README.md` that fresh installs should run `migrate:fresh` + `db:seed` (which will now only create the admin) and that existing installs should run `demo:purge`.

### 1.5 Empty-state verification (per screen)
Hit every panel page with an empty DB and verify it renders gracefully (no errors, helpful empty message):

- [ ] `/dashboard` — empty KPIs show `0` and `—`, empty lists show "Nenhum pedido encontrado." / "Nenhum item em preparo." / "Nenhum pedido registrado hoje."
- [ ] `/admin/orders` — "0 pedido(s) encontrado(s)" + empty table state
- [ ] `/admin/orders/in-progress` — empty state
- [ ] `/admin/orders/history` — empty state
- [ ] `/admin/kitchen` — empty queue state
- [ ] `/admin/dining/tables` — "Nenhuma mesa cadastrada" + obvious CTA to create
- [ ] `/admin/dining/queue` — empty waitlist state
- [ ] `/admin/customers` — empty state
- [ ] `/admin/catalog/products` — empty product list + CTA
- [ ] `/admin/catalog/addons` — empty state
- [ ] `/admin/reports` — handle the "Em desenvolvimento" cards explicitly

---

## 2. Route Layer Cleanup

Background: `bootstrap/app.php` loads `routes/default_routes_web.php` only, but `routes/web.php` still exists with conflicting (and partially wrong) definitions. This already caused one production bug (`Route::view('dashboard', 'dashboard')` was active and bypassed the Livewire component).

- [x] Switch the dashboard route from `Route::view` to `Route::get(Dashboard::class)` (commit `05912aa`)
- [ ] Delete `routes/web.php` (it is not loaded — confirmed via `bootstrap/app.php`)
- [ ] Audit `routes/default_routes_web.php` for any remaining `Route::view()` calls that should be Livewire components
- [ ] Make `Route::redirect('/', '/login')` configurable (a logged-in user landing on `/` should probably go to `/dashboard`, not the login page — verify)

---

## 3. Configuration & Environment

- [ ] Audit `.env.example` — make sure no real credentials leak and required keys are listed
- [ ] Confirm `APP_ENV=local` defaults are safe for first-run (cache drivers, mail, session)
- [ ] Ensure `php artisan migrate:fresh --seed` produces a usable state with only the admin user

---

## 4. Known issues / TODOs picked up during the audit

- [ ] `App\Livewire\Dashboard::alerts()` reads `updated_at` to detect delayed orders — this changes on any column write, not on a status transition. Switch to a join on `order_status_history` or a dedicated `status_changed_at` column.
- [ ] Polling cadence (`wire:poll.30s` on dashboard, `#[Poll(30000)]` on kitchen) should become broadcasting in Phase 9. Track here so it is not forgotten.
- [ ] Multi-tenant safety: most queries (`Order::query()`, `Customer::*`, `DiningTable::orderBy(...)`) do not filter by `restaurant_id`. Acceptable while there is a single restaurant; must change before the second restaurant is created.
- [ ] `Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');` in `routes/admin.php` returns a static view called `admin.dashboard`. Verify this is not a dead alias once Phase 0 is finished.

---

## 5. Done

- [x] Fix dashboard 500 caused by `Route::view` not invoking the Livewire component (commit `05912aa`, 2026-05-26)
- [x] Skill updates: `coding-standards` Definition of Done + `testing-and-quality` screen-testing workflow (commit `bb77d92`, 2026-05-26)
