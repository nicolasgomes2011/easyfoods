# MVP vs Future

What ships in the MVP and what comes later.
The goal of the MVP is to prove the core loop works end-to-end:
customer orders → restaurant prepares → customer tracks.

---

## MVP (Must Work Before Anything Else)

### Authentication
- Customer registration/login (email or phone)
- Restaurant staff login
- Role-based access: admin, kitchen, counter, customer

### Restaurant Backoffice (MVP subset)
- Product catalog: create, edit, archive products
- Addon groups and options
- Category management
- Operating hours
- Table configuration (add/remove/capacity)

### Customer Ordering
- Storefront: browse menu, view product details
- Product customization (addons, removals)
- Cart: add, update, remove items
- Checkout: dine-in or delivery
- Order placement

### Kitchen Panel (Basic)
- Order queue showing incoming orders
- Static prep time countdown (base time from product, not yet ETA engine)
- Status transitions: in_preparation → ready

### Order Management (Restaurant)
- Order list by status
- Confirm / cancel incoming orders
- Order detail view

### Order Tracking (Customer)
- Live status page after placing order
- Countdown based on static base prep time (ETA engine comes later)
- Status updates without page refresh

---

## Phase 2 — Delivery Driver

- Driver panel: incoming requests, accept/decline
- Status updates: picked up, delivered
- Customer sees delivery status in real time

---

## Phase 3 — ETA Intelligence

- Collect actual prep time history per product
- Compute rolling historical average
- Apply kitchen load factor
- Replace static countdown with dynamic estimate
- Collect delivery transit history
- Dynamic delivery ETA

---

## Phase 4 — Table Tab & Bill Splitting

- Group tab per table session
- Item tagging per person
- Equal split, split by item, custom split
- Tab closing flow

---

## Phase 5 — Reports & Analytics

- Revenue by day/week/month
- Top products
- Average order value
- Peak hours
- Avg prep time per product vs estimate
- Cancellation rate

---

## Post-MVP (No Committed Date)

| Feature | Why it's post-MVP |
|---------|------------------|
| Order scheduling (future date/time) | Complex, not core loop |
| Multi-branch support | Needs proven single-branch first |
| Coupons and promotions | Not essential for core loop |
| Customer favorites / reorder | Convenience, not critical |
| Driver real-time location map | Infrastructure complexity |
| Weather-adjusted ETA | Needs external API and enough history |
| Payment gateway integration | Fiscal/monetary complexity excluded for now |
| SMS / WhatsApp notifications | External service dependency |
| Fiscal / tax document printing (NF, NFC-e) | Legal complexity, excluded explicitly |
