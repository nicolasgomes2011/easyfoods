# Cart & Checkout

**Status:** 🟢 Completed
**Current state (2026-11-18):** Session cart with server-side quantity checks and clear cart (EF-58), checkout for dine-in / delivery / pickup, payment method with a pending `Payment` (EF-4), store-open check (EF-5), zone fee on the server (EF-51), occupied tables selectable (EF-6) and the customer registered by phone (EF-9) are on `main`. See [ROADMAP](ROADMAP.md).
**Phase:** 3 — Customer Ordering

## Description
The customer reviews their selections and places the order.
Checkout must be fast — minimum steps, no surprises.
Total is always recalculated server-side; the frontend total is only visual.

## Tasks

### Cart
- [ ] Persistent cart (survives page reload for authenticated users)
- [ ] Guest cart (session-based)
- [ ] Add item with selected addons and removals
- [ ] Update item quantity in cart
- [ ] Remove item from cart
- [ ] Show subtotal, delivery fee (if applicable), and total
- [ ] Clear cart action with confirmation

### Checkout Flow
- [ ] Step 1: Review cart (items, quantities, selected options)
- [ ] Step 2: Choose order type: dine-in (table) or delivery
  - Dine-in: select table number or scan QR (if applicable)
  - Delivery: enter or select saved address
- [ ] Step 3: Order notes (optional, per order or per item)
- [ ] Step 4: Confirm order

### Validation (Server-Side)
- [ ] Recalculate total server-side before order creation
- [ ] Reject if a product is no longer available
- [ ] Reject if restaurant is closed
- [ ] Reject if required addon group has no selection

### Order Placement
- [ ] Create order in `pending_confirmation` status
- [ ] Show confirmation screen with order number
- [ ] Redirect to order tracking

## Acceptance Criteria
- Customer cannot place order with missing required addons
- Total shown to customer matches total calculated server-side
- Checkout completes in 3 steps or fewer from cart review
- Error messages explain exactly what failed and how to fix it

## Dependencies
- Customer Storefront (product data)
- Authentication & Roles (guest or authenticated)
- Order Management (Restaurant) (order creation)
- Table Management (dine-in flow)
