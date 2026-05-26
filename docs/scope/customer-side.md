# Customer Side — Complete Scope

Everything the customer experiences in EasyFoods.

The customer interface must be the simplest part of the system.
The complexity is in the backend. The customer never sees it.

---

## Design Principle

A customer should be able to:
1. Open the storefront
2. Choose a product
3. Customize it
4. Add to cart
5. Check out
6. See the order confirmed

...in under 2 minutes, without creating an account, without confusion.

---

## 1. Storefront & Menu

**Restaurant info:**
- Restaurant name, description, open/closed status
- Operating hours shown clearly
- If closed: when does it reopen

**Menu layout:**
- Products organized by category
- Category navigation (sticky on mobile)
- Each product: image, name, short description, price, "Adicionar" button
- Unavailable products shown as grayed out with label — not hidden
- Restaurant is honest: if something is off, it says so

**Product detail:**
- Full description
- Ingredient list (so customer knows what they are customizing)
- Addon groups (e.g., extras, removals, sizes) with clear price impact
- Price updates in real time as options are selected
- Quantity selector
- Add to cart confirmation

**Search:**
- Quick search within the menu
- No page reload

---

## 2. Product Customization

The customer must have full control over their order.

**What they can do:**
- Add optional extras (extra ingredients, sauces, sides)
- Remove default ingredients (e.g., "sem cebola", "sem tomate")
- Select required options (e.g., drink size: small, medium, large)
- Set quantity

**What they see:**
- Price impact of each selection, updated live
- Required groups are clearly marked — they cannot proceed without selecting

**What they do NOT see:**
- Prep time per item
- Internal stock levels
- Any backend complexity

---

## 3. Cart

- Cart persists across page reloads for logged-in users
- Guest cart is session-based
- Each item shows: name, selected options, quantity, item total
- Update quantity directly in cart
- Remove items from cart
- Order notes field (optional, per order)
- Clear cart with confirmation prompt
- Running total visible at all times

---

## 4. Checkout

Minimal. No surprises. No hidden fees.

**Steps:**
1. Review cart (final check of items + totals)
2. Choose order type:
   - Dine-in: enter table number or scan QR code
   - Delivery: enter address or select saved address
   - Pickup: no address needed
3. Optional: order notes
4. Confirm order

**Server-side validation at placement:**
- Total is recalculated — frontend total is never trusted
- Products are re-verified as available
- Restaurant must be open
- Required addon groups must have selections

**Post-checkout:**
- Order confirmation screen with order number
- Immediate redirect to order tracking

**Guest checkout:**
- No account creation required to order
- Only name and (optionally) phone number for contact

---

## 5. Order Tracking

The customer's window into what is happening with their order.

**What they see:**
- Order number
- Current status with a human-readable label (not a status code)
- List of items ordered
- A live countdown to when the order will be ready (dine-in/pickup) or delivered

**The countdown:**
- Starts at order confirmation
- Shows estimated ready time as a clock time: "Pronto às 12:34"
- Timer counts down in real time, every second
- Timer is based on ETA engine output — not a static number the restaurant typed
- If the estimate changes (kitchen is overloaded), the timer adjusts — with a human explanation: "Está demorando um pouquinho mais, novo horário: 12:48"
- The timer NEVER freezes. If we genuinely do not know, we show a pulsing indicator with the last status

**Status progression:**
- Visual steps: Recebido → Confirmado → Em preparo → Pronto → Entregue
- Each completed step shows the exact time it happened
- Delays are visible, explained, and honest

**Sharing:**
- Tracking link is shareable — guest at a table can share with friends
- No login required to view a tracking page (for guest orders)

---

## 6. Table Tab & Bill Splitting (Dine-In)

For groups eating at the same table.

**The problem:**
- A group of 4 people sits down
- Each person orders individually or someone orders for everyone
- At the end, they want to split the bill fairly

**What the system supports:**
- Everyone at the table accesses the same session tab (via table link or QR code)
- Each item can be tagged to a person's name
- Untagged items are treated as shared

**Split modes:**
- Equal split: total divided by number of people
- Split by item: each person pays for their tagged items
- Custom: manual amounts

**Tab view:**
- Running total visible to everyone at the table
- Each person can see their portion before confirming

---

## 7. Order History (Authenticated Customers)

- View past orders
- Reorder: add the same items to cart with one tap
- View past order details and status history

---

## What the Customer Never Sees

- Prep time per product
- Kitchen queue state
- Driver real-time location (Phase 7+, and only as ETA, not a map)
- Other customers' orders
- Any restaurant configuration
