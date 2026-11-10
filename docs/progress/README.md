# Development Progress

This folder tracks the implementation status of every feature in the EasyFoods platform.
Each file corresponds to one major feature and contains tasks, acceptance criteria, and dependencies.

## Cross-cutting plans

- [ROADMAP](ROADMAP.md) — phased implementation plan
- [ADJUSTMENTS](ADJUSTMENTS.md) — cleanup work (remove fake data, route layer, env)
- [TESTING_ROADMAP](TESTING_ROADMAP.md) — test coverage plan

## Legend

| Symbol | Status |
|--------|--------|
| 🔴 | Not Started |
| 🟡 | In Progress |
| 🟢 | Completed |
| ⏸️ | Blocked |

## Feature Index

Last synced with `main` on **2026-11-10** (`a7778c0`, 242 tests). The detailed checklist per phase is the
[ROADMAP](ROADMAP.md); open work is tracked as `EF-x` cards in Linear (team EasyFoods, project Roadmap EasyFoods).

### Restaurant Side
| Feature | Status | Notes |
|---------|--------|-------|
| [Dashboard](restaurant-dashboard.md) | 🟡 | KPIs, recent orders, alerts, tables snapshot, quick actions and today-vs-baseline badges done. "Drivers active" is still a proxy. |
| [Product Catalog](product-catalog.md) | 🟢 | Products, categories + reorder, addon groups, variants, images, archive and availability toggle done. |
| [Employee Management](employee-management.md) | 🟡 | Invite, first-login password, deactivate/reactivate, roles and staff directory done. Access refinements: EF-10. |
| [Table Management](table-management.md) | 🟢 | Tables CRUD, counters, waitlist, sessions, auto-occupy and delete guard done. Open: EF-35 (QR codes), EF-53. |
| [Kitchen Panel](kitchen-panel.md) | 🟡 | Queue, counters, polling and start/ready actions done. Countdown (EF-15), item checklist, alerts, filters pending. |
| [Order Management (Restaurant)](order-management-restaurant.md) | 🟡 | List/filters/show and confirm/reject/cancel/complete transitions done. Item edits, new-order alert, auto-expiry pending. |

### Customer Side
| Feature | Status | Notes |
|---------|--------|-------|
| [Storefront & Menu](customer-storefront.md) | 🟡 | `/store/menu` with categories, product modal and cart done. Open: EF-34, EF-54, EF-55, EF-56, EF-57. |
| [Cart & Checkout](customer-cart-checkout.md) | 🟡 | Session cart, checkout for the 3 order types and `PlaceOrder` done. Open: EF-4, EF-5, EF-6, EF-9, EF-51, EF-58. |
| [Order Tracking](customer-order-tracking.md) | 🟡 | Tracking by token with status steps and 10s polling done. Open: EF-16 (step times), EF-15 (countdown). |
| [Bill & Tab Splitting](bill-and-tab-splitting.md) | 🟡 | Per-table tab and manual close done. Per-person items and split options pending. |

### Delivery Side
| Feature | Status | Notes |
|---------|--------|-------|
| [Driver Panel](delivery-driver-panel.md) | 🟡 | Shared ready-for-pickup queue with pick up / delivered done. No per-driver assignment or availability. Open: EF-60. |

### System & Infrastructure
| Feature | Status | Notes |
|---------|--------|-------|
| [Authentication & Roles](authentication.md) | 🟡 | Login, password reset, email verification, 2FA and role middleware done. Per-role access matrix: EF-10. Multi-tenant: EF-2, EF-8. |
| [ETA Engine](eta-engine.md) | 🔴 | Only static `min_order_minutes` / `max_order_minutes`. Milestones: EF-14; prep time + countdown: EF-15. |
| [Realtime Notifications](realtime-notifications.md) | 🟡 | `wire:poll` only (30s on operational panels, 10s on tracking). No broadcasting yet. |
