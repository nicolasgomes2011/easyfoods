# Adjustments & Cleanup Roadmap

> **Mission:** the panel must display only data registered through the UI. No demo restaurant, no sample products, no fake orders, no demo users — only the admin account.
>
> Phase 0 of the [ROADMAP](ROADMAP.md) — completed in commit `2bf420a`. Last checked against `main` on **2026-11-18** (`de95d97`).
> New cleanup work is tracked as `EF-x` cards in Linear, not in this file.

## Status legend
- `[x]` done
- `[~]` partial
- `[ ]` pending

---

## 1. Fake/Seed Data Removal (priority)

### 1.1 Seeders
Files to modify or delete in `database/seeders/`:

- [x] **`OrderSeeder.php`** — deleted.
- [x] **`CatalogSeeder.php`** — deleted.
- [x] **`RestaurantSeeder.php`** — deleted.
- [x] **`UserSeeder.php`** — stripped down to the single admin entry.
- [x] **`DatabaseSeeder.php`** — calls only `UserSeeder::class`.

### 1.2 Factories
- [x] Audit `database/factories/` — the remaining factories (`Customer`, `Ingredient`, `Order`, `Restaurant`, `TableSession`, `User`, `WaitlistEntry`, `WasteEntry`) all have a caller in the tests.

### 1.3 Hardcoded sample data inside components/blades
- [~] `resources/views/livewire/reports/index.blade.php` — sales (EF-47), top products and peak hours (EF-48) and waste (EF-41) are real reports; the 3 remaining cards stay marked "Em desenvolvimento" until they are built (Phase 9 of the ROADMAP).
- [x] Sweep every Volt/Livewire `@php` block and component class for inline sample data (Phase 0).

### 1.4 Existing DB cleanup
- [x] `php artisan demo:purge` (`app/Console/Commands/DemoPurge.php`) wipes the demo rows and preserves the admin user.
- [x] `README.md` documents `demo:purge --force` for existing installs.

### 1.5 Empty-state verification (per screen)
Every panel page was checked with an empty DB as part of Phase 0 (`2bf420a`):

- [x] `/admin/dashboard` (the old `/dashboard` address now redirects to the role's home screen — EF-10)
- [x] `/admin/orders`, `/admin/orders/in-progress`, `/admin/orders/history`
- [x] `/admin/kitchen`
- [x] `/admin/dining/tables`, `/admin/dining/queue`
- [x] `/admin/customers`
- [x] `/admin/catalog/products`, `/admin/catalog/addons`
- [x] `/admin/reports` — see 1.3

---

## 2. Route Layer Cleanup

Background: `bootstrap/app.php` loads `routes/default_routes_web.php`, `admin.php` and `customer.php` (plus `auth.php`). There is no `routes/web.php`.

- [x] Switch the dashboard route from `Route::view` to `Route::get(Dashboard::class)` (commit `05912aa`)
- [x] Delete `routes/web.php` — gone
- [x] No `Route::view()` left in `routes/`
- [x] `Route::redirect('/', '/login')` — a logged-in user is sent on by the `guest` middleware of `/login`, so no extra config is needed

---

## 3. Configuration & Environment

- [ ] Audit `.env.example` — make sure no real credentials leak and required keys are listed
- [ ] Confirm `APP_ENV=local` defaults are safe for first-run (cache drivers, mail, session)
- [x] `php artisan migrate:fresh --seed` creates only the admin user (Phase 0)
- [x] Minimum PHP version declared in `composer.json` matches the lock file: `^8.4` (EF-12)

---

## 4. Known issues / TODOs picked up during the audit

- [x] `App\Livewire\Dashboard::alerts()` keys off `order_status_histories.changed_at`, not `updated_at`.
- [ ] Polling (`wire:poll`) should become broadcasting in Phase 9 of the ROADMAP (EF-29).
- [x] Multi-tenant scoping — staff per restaurant, global tenant scope, policies by restaurant and a leak test suite (EF-81 to EF-84).
- [x] `/admin/dashboard` renders the `App\Livewire\Dashboard` component (no static `admin.dashboard` view).

---

## 5. Done

- [x] Fix dashboard 500 caused by `Route::view` not invoking the Livewire component (commit `05912aa`, 2026-05-26)
- [x] Skill updates: `coding-standards` Definition of Done + `testing-and-quality` screen-testing workflow (commit `bb77d92`, 2026-05-26)
- [x] Phase 0 cleanup (commit `2bf420a`)
