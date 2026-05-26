# Customer Storefront & Menu

**Status:** 🔴 Not Started
**Phase:** 3 — Customer Ordering

## Description
The public-facing menu page. This is the first thing a customer sees.
It must be fast, clear, and require zero friction to navigate.
Customers can be authenticated or guest — both can browse the menu.

## Tasks

### Menu Display
- [ ] Show restaurant info (name, open/closed status, operating hours)
- [ ] Show categories with product listing under each
- [ ] Category navigation (sticky or tab-based on mobile)
- [ ] Product card: image, name, short description, price, "add" button
- [ ] Show which products are currently unavailable (grayed out, not hidden)

### Product Detail
- [ ] Open product detail page or bottom sheet (mobile)
- [ ] Full description, ingredients list
- [ ] Customization groups (addon groups, removable ingredients)
- [ ] Selected options affect displayed price in real time
- [ ] Quantity selector

### Search
- [ ] Search products by name within the storefront
- [ ] Results show inline without page reload

### Restaurant Status
- [ ] If restaurant is closed, show closed banner with next opening time
- [ ] Products can still be browsed but "add to cart" is disabled when closed

## Acceptance Criteria
- Storefront loads without requiring login
- A product that is paused/archived does not appear
- Price updates in real time as customer selects addons
- Mobile layout is primary — desktop is secondary
- Page is usable in under 3 taps to add a product to cart

## Dependencies
- Product Catalog (data source)
- Cart & Checkout (add to cart action)
