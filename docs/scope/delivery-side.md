# Delivery Side — Complete Scope

Everything the delivery driver gets from EasyFoods.

The driver interface is intentionally minimal.
A driver on a motorcycle, in traffic, cannot navigate a complex UI.
Every action must be reachable in one or two taps.

---

## 1. Driver Account

- Driver logs in with credentials created by the restaurant admin
- Driver profile: name, vehicle type, phone number
- Driver sets their own availability (available / unavailable)

---

## 2. Incoming Delivery Requests

When an order is ready for delivery, it appears in the driver panel.

**What the driver sees:**
- Restaurant name and pickup address
- Delivery address
- Order summary (number of items, estimated total weight — optional)
- Estimated distance and delivery time

**Actions:**
- Accept delivery request
- Decline delivery request (order returns to pool for reassignment)

**Rules:**
- Only available drivers receive requests
- A driver can only have one active delivery at a time (MVP)

---

## 3. Active Delivery

Once a driver accepts, the order is theirs.

**Flow:**
1. Driver goes to restaurant, picks up the order
2. Driver marks as "Coletei o pedido" (picked up) → order status moves to `out_for_delivery`
3. Customer is notified that driver is on the way
4. Driver arrives at delivery address
5. Driver marks as "Entreguei" → order status moves to `delivered`
6. Customer is notified

**What the driver panel shows:**
- Pickup address (restaurant)
- Delivery address (customer)
- Customer name and contact (phone number, for delivery coordination)
- Current step in the delivery flow

---

## 4. ETA for the Customer

The driver's real-world actions feed into the customer's tracking page.

- When driver accepts and marks picked up: customer sees "Driver a caminho"
- ETA for delivery is estimated based on: distance, current traffic signals, time of day, driver's historical delivery speed
- ETA is shown to customer as a clock time, not a vague range
- When driver marks delivered: customer tracking page closes the delivery countdown

---

## 5. Driver History

- Driver can see their completed deliveries for the day
- Count of deliveries, total distance covered (for restaurant reporting)

---

## What the Driver Never Sees

- Restaurant backoffice
- Other customers' orders not assigned to them
- Other drivers' assignments
- Kitchen state or prep times
- Financial data

---

## Future Scope (Post-MVP)

- Multiple simultaneous deliveries per driver
- Driver rating by customer
- Driver location shared as live map to customer
- Route optimization suggestions
- Weather-adjusted ETA (rainy day = longer estimate)
