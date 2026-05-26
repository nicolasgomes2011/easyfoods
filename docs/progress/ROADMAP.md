# EasyFoods — Roadmap

## Phase 1 — Foundation
Core infrastructure. Nothing works without this.

- [ ] Authentication system (restaurant staff, customers, drivers)
- [ ] Role and permission model (admin, manager, kitchen, customer, driver)
- [ ] Database schema: restaurants, users, products, orders, tables
- [ ] Base Livewire layout for each actor (restaurant panel, storefront, driver app)

## Phase 2 — Restaurant Backoffice (Core Operations)
The restaurant needs to be able to configure and operate.

- [ ] Product catalog: create, edit, archive products with base prep time
- [ ] Category management
- [ ] Addon and customization groups
- [ ] Table management: add, remove, set capacity
- [ ] Employee management: roles, access control
- [ ] Operating hours configuration
- [ ] Basic restaurant dashboard

## Phase 3 — Customer Ordering
The customer needs to be able to browse, customize, and place an order.

- [ ] Public storefront (menu listing)
- [ ] Product detail with customization (add/remove ingredients, addons)
- [ ] Cart (add, update, remove items)
- [ ] Checkout: dine-in table selection or delivery address
- [ ] Order placement and confirmation

## Phase 4 — Kitchen & Order Flow (Restaurant Operations)
Orders need to flow from customer to kitchen to delivery.

- [ ] Kitchen panel: queue of active orders
- [ ] Per-order countdown timer (driven by estimated prep time)
- [ ] Order status transitions: confirmed → in preparation → ready
- [ ] Order management dashboard for restaurant staff
- [ ] Table status: available, occupied, waiting, served

## Phase 5 — Order Tracking (Customer)
Customers need to see what is happening with their order.

- [ ] Real-time order status page
- [ ] Live countdown timer (not static, always decreasing)
- [ ] Status change notifications

## Phase 6 — Delivery Driver
The motoboy needs to receive, accept, and deliver orders.

- [ ] Driver panel: incoming delivery requests
- [ ] Accept / decline delivery
- [ ] Status updates: picked up, on the way, delivered
- [ ] Customer-facing ETA for delivery

## Phase 7 — ETA Intelligence
The system learns from historical data to produce accurate time estimates.

- [ ] Collect prep time history per product
- [ ] Dynamic prep time calculation (historical average, not static value)
- [ ] Delivery ETA based on distance, demand, weather signals
- [ ] ETA recalculation as orders progress

## Phase 8 — Table Tab & Bill Splitting
Groups dining in need to split their bill.

- [ ] Group tab per table
- [ ] Individual items linked to specific person in the group
- [ ] Split options: equal split, split by item
- [ ] Tab closing flow

## Phase 9 — Advanced & Polish
After the core product is stable.

- [ ] Order history and reorder for customers
- [ ] Restaurant reports (revenue, popular items, peak hours)
- [ ] Promotions and coupons
- [ ] Scheduled orders
- [ ] Multi-branch support
