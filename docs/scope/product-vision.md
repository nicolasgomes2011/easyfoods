# Product Vision

## What EasyFoods Is

EasyFoods is a complete restaurant operations and ordering platform.
It connects three parties — restaurants, customers, and delivery drivers — in a single, coherent system.

It is not a marketplace. It operates for a single restaurant or a restaurant chain.

## The Core Problem

Restaurants today deal with fragmented tools: one for orders, one for the kitchen, one for tables, one for delivery.
Customers order through apps that give no real visibility into what is happening with their food.
Drivers work with phones and calls.

EasyFoods replaces all of this with one integrated system.

## Personas

### Restaurant Admin
Manages the entire restaurant configuration. Sets up products, prices, tables, staff, and operating hours.
Uses the backoffice daily to monitor and adjust operations.
Technical comfort: moderate. Does not want to write code, but can navigate a settings page.

### Restaurant Manager / Counter Staff
Operates the restaurant day-to-day. Confirms orders, manages tables, coordinates with kitchen.
Needs speed. Cannot afford to spend 10 seconds looking for a button.

### Kitchen Staff
Sees only the kitchen panel. Reads incoming orders, tracks prep times, marks items as done.
Often working in a loud, fast environment. Needs large text and obvious actions.
May not be reading carefully — the interface must be intuitive by design.

### Customer
Accesses the storefront from their phone. Wants to order quickly without creating an account.
Has zero tolerance for friction. Will abandon checkout if it takes too long.
Wants to know exactly when their food is coming — not a vague range.

### Delivery Driver
Receives delivery jobs and updates status as they deliver.
Works on a phone, often in motion. Needs a simple, single-action interface.

## Product Goals

1. Give restaurants full operational control from one panel
2. Give customers a fast, honest, and transparent ordering experience
3. Give drivers clarity on what to deliver and where
4. Keep all three synchronized with real-time data

## Design Principles

- **Honest over optimistic**: show real estimates, not best-case numbers
- **Fast for the busiest user**: kitchen staff and counter staff are always under pressure
- **Simple for the customer**: three taps to add a product, minimal checkout steps
- **No hidden state**: every status change is visible to the right party immediately
- **Data over guesses**: ETAs are calculated from history, not entered manually and left static

## What EasyFoods Is Not

- Not a POS (point-of-sale) system with fiscal/tax printing
- Not a payment gateway (payment integration is a future phase)
- Not a marketplace connecting multiple restaurants to customers
- Not a logistics platform managing a fleet of drivers independently
