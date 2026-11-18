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

Last synced with `main` on **2026-11-18** (`de95d97`, 758 tests). The detailed checklist per phase is the
[ROADMAP](ROADMAP.md); open work is tracked as `EF-x` cards in Linear (team EasyFoods, project Roadmap EasyFoods).

### Restaurant Side
| Feature | Status | Notes |
|---------|--------|-------|
| [Dashboard](restaurant-dashboard.md) | 🟢 | KPIs, recent orders, alerts, tables snapshot, active drivers (EF-38), quick actions and today-vs-baseline badges done. |
| [Product Catalog](product-catalog.md) | 🟢 | Products, categories + reorder, addon groups, variants, images, archive, availability toggle, base prep time (EF-15) and ingredients (EF-39) done. |
| [Employee Management](employee-management.md) | 🟡 | Invite, first-login password, deactivate/reactivate, roles, staff directory and staff per restaurant (EF-81) done. Open: EF-24 (invite e-mail), EF-63 (in-screen authorization). |
| [Table Management](table-management.md) | 🟢 | Tables CRUD, counters, waitlist, sessions, auto-occupy, delete guard, server re-checks (EF-53), QR codes (EF-35) and the table tab (EF-44, EF-45) done. Open: EF-18 (call waiter). |
| [Kitchen Panel](kitchen-panel.md) | 🟢 | Queue grouped by status, type filter, touch layout (EF-42), item checklist (EF-43), prep timer (EF-15), new-order alert (EF-19) and waste log (EF-40) done. |
| [Order Management (Restaurant)](order-management-restaurant.md) | 🟢 | List/filters/show, confirm/reject/cancel/complete, item edits (EF-21), new-order alert (EF-19), auto-expiry (EF-20) and "Marcar como pago" (EF-4) done. Open: EF-60, EF-65. |

### Customer Side
| Feature | Status | Notes |
|---------|--------|-------|
| [Storefront & Menu](customer-storefront.md) | 🟢 | `/store/menu` with category sections (EF-56), open/closed header (EF-54), greyed-out unavailable products (EF-55), quick search (EF-57), most ordered (EF-31) and table QR mode (EF-34) done. Open: EF-18. |
| [Cart & Checkout](customer-cart-checkout.md) | 🟢 | Session cart, clear cart (EF-58), checkout for the 3 order types, payment method + pending payment (EF-4), store-open check (EF-5), zone fee (EF-51) and customer by phone (EF-9) done. |
| [Order Tracking](customer-order-tracking.md) | 🟡 | Tracking by token with steps per order type, step times (EF-16), "Pronto às" and delay message (EF-15, EF-26) and 10s polling done. Broadcasting: EF-29. |
| [Bill & Tab Splitting](bill-and-tab-splitting.md) | 🟡 | Per-table tab with totals (EF-44), people and item-to-person (EF-45) and manual close done. Split options and partial payments: EF-46. |

### Delivery Side
| Feature | Status | Notes |
|---------|--------|-------|
| [Driver Panel](delivery-driver-panel.md) | 🟡 | Offers to available drivers with accept/decline (EF-36), pause/resume (EF-37), call/map links (EF-38) and delivered done. Open: EF-60, EF-76 (delivery ETA). |

### System & Infrastructure
| Feature | Status | Notes |
|---------|--------|-------|
| [Authentication & Roles](authentication.md) | 🟢 | Login, password reset, email verification, 2FA, per-role access matrix and home screen (EF-10) and multi-tenant scoping (EF-81 to EF-84) done. Open: EF-63, EF-67. |
| [ETA Engine](eta-engine.md) | 🟡 | Milestones (EF-14), base prep time + ready-at estimate (EF-15) and learned prep time from history and load (EF-26) done. Delivery transit and recalculation: EF-76. |
| [Realtime Notifications](realtime-notifications.md) | 🟡 | `wire:poll` (30s on operational panels, 10s on tracking) and the new-order sound alert (EF-19). No broadcasting yet (EF-29). |
