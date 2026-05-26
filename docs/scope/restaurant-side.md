# Restaurant Side — Complete Scope

Everything the restaurant gets from EasyFoods.

---

## 1. Dashboard

The operational home screen after login. Gives a live snapshot of what is happening right now.

**What it shows:**
- Active orders by status (how many confirmed, how many in prep, how many ready)
- Table overview: available, occupied, waiting
- Orders needing confirmation right now
- Today's order count and completion rate
- Average prep time today vs historical average
- Alerts: overdue orders, tables with long wait times

**What it does NOT do:**
- It does not process anything — it is a read-only operational view
- It does not replace the kitchen panel or the order management panel

---

## 2. Product Catalog

Full management of what the restaurant sells.

**Products:**
- Create product with: name, description, price, category, image, base prep time (required)
- Edit product at any time
- Archive product (hides from storefront, preserves order history)
- Toggle availability (instant hide/show without archiving)
- Products cannot be deleted if referenced by open orders

**Categories:**
- Create and name categories
- Set display order on the storefront
- Archive categories (hides all products in it)

**Addons & Customizations:**
- Create addon groups (e.g., "Ingredientes extras", "Remover ingredientes", "Tamanho da bebida")
- Set each group as required or optional
- Set minimum and maximum selections
- Each option can have a price delta (e.g., extra cheese = +R$2)
- Addon groups can be linked to multiple products

**Prep Time:**
- Each product has a base prep time (minutes) set by the restaurant
- This is the starting point — the ETA engine refines it over time using real order history
- The restaurant can update the base time at any point

---

## 3. Table Management

Configuration of physical tables and live operational control.

**Configuration:**
- Add tables with name/number and seat capacity
- Group tables by section or area (optional)
- Remove tables (blocked if table has an active session)

**Live Status Panel:**
- Visual grid of all tables
- Status per table: available, occupied, waiting, served, reserved
- Status transitions happen automatically on order events or manually by staff
- Waiting list: add group (name + party size + arrival time), assign to available table, remove from list
- Alert when a table opens and waiting list is not empty

**Table Session:**
- A session starts when the first order is placed at a table
- Session accumulates all orders placed at that table
- Session ends when the tab is closed
- Session history is preserved for reporting

---

## 4. Kitchen Panel

The operational screen for kitchen staff. This is a separate, focused interface.

**Order Queue:**
- Shows all active orders in preparation
- Each order card: order number, type (dine-in / delivery / pickup), items, notes
- Ordered by: oldest first

**Countdown Timers:**
- Each order has a live countdown to estimated completion
- Timer starts when kitchen marks order as `in_preparation`
- Timer is driven by ETA engine (adapts to kitchen load)
- Timer goes red when time is nearly up
- Timer continues past zero showing overdue minutes

**Item Checklist:**
- Each item in an order is a checkbox
- Kitchen checks off items as they are completed
- Visual progress indicator per order

**Alerts:**
- Sound + visual alert on new incoming orders
- Alert on orders approaching or past their ETA

**Filters:**
- All orders / dine-in only / delivery only / pickup only

---

## 5. Order Management (Restaurant)

The full order management view for managers and counter staff.

**Order List:**
- All orders grouped by status
- Filter by: status, order type, date range
- Search by order number

**Order Detail:**
- Full item list with addons and removals
- Price breakdown
- Order timeline (status history with timestamps)
- Customer info (name, contact)
- Assigned driver (if delivery)

**Actions:**
- Confirm incoming order
- Cancel order with mandatory reason
- Mark order as completed after fulfillment

**Incoming Alert:**
- Sound + visual alert for orders awaiting confirmation
- Orders can auto-cancel if not confirmed within a configurable window

---

## 6. Employee Management

**Staff Accounts:**
- Admin invites staff by email
- Staff sets own password on first access
- Admin deactivates accounts for terminated staff

**Roles:**
- `restaurant_manager`: catalog, orders, tables, reports — no billing
- `kitchen_staff`: kitchen panel only
- `counter_staff`: order management, table status — no catalog or settings

---

## 7. Operating Hours

- Set opening and closing times per day of week
- Mark specific days as closed
- The storefront respects operating hours in real time
- Orders cannot be placed when the restaurant is closed

---

## 8. Reports (Post-MVP)

- Revenue per day / week / month
- Top-selling products
- Average order value
- Peak order hours
- Avg prep time per product
- Cancellation rate
