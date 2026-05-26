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

### Restaurant Side
| Feature | Status | Notes |
|---------|--------|-------|
| [Dashboard](restaurant-dashboard.md) | 🟡 | KPIs + recent orders + alerts done. Table snapshot, drivers active, quick actions pending. |
| [Product Catalog](product-catalog.md) | 🟡 | Products CRUD + addon groups done. Categories, archive, variants, images pending. |
| [Employee Management](employee-management.md) | 🔴 | |
| [Table Management](table-management.md) | 🟡 | Tables CRUD done. Sessions, auto-transitions, guards pending. |
| [Kitchen Panel](kitchen-panel.md) | 🟡 | Queue + counters + polling done. Status actions, timers, item checklist, alerts pending. |
| [Order Management (Restaurant)](order-management-restaurant.md) | 🟡 | List/filters/show done. Status transitions and item edits pending. |

### Customer Side
| Feature | Status |
|---------|--------|
| [Storefront & Menu](customer-storefront.md) | 🔴 |
| [Cart & Checkout](customer-cart-checkout.md) | 🔴 |
| [Order Tracking](customer-order-tracking.md) | 🔴 |
| [Bill & Tab Splitting](bill-and-tab-splitting.md) | 🔴 |

### Delivery Side
| Feature | Status |
|---------|--------|
| [Driver Panel](delivery-driver-panel.md) | 🔴 |

### System & Infrastructure
| Feature | Status | Notes |
|---------|--------|-------|
| [Authentication & Roles](authentication.md) | 🟡 | Login/register/2FA + multi-role middleware done. Invites, isolation guards, customer/driver flows pending. |
| [ETA Engine](eta-engine.md) | 🔴 | Only static `min_order_minutes` / `max_order_minutes` on restaurant. No historical learning. |
| [Realtime Notifications](realtime-notifications.md) | 🔴 | Only Livewire `wire:poll` (30s) on dashboard and kitchen. No broadcasting yet. |
