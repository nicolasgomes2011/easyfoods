# Product Catalog

**Status:** 🔴 Not Started
**Phase:** 2 — Restaurant Backoffice

## Description
Restaurants configure everything they sell here: products, categories, addons, variants, and availability.
The base prep time entered here feeds directly into the ETA engine.
Products can be paused without being deleted.

## Tasks

### Categories
- [ ] Create, edit, archive categories
- [ ] Reorder categories (display order on storefront)
- [ ] Category image (optional)

### Products
- [ ] Create product: name, description, price, category, image
- [ ] Set base prep time (in minutes) at product creation — required field
- [ ] Edit product
- [ ] Archive / pause product (temporarily hide from storefront)
- [ ] Permanently delete product (with guard: cannot delete if referenced in open orders)
- [ ] Product availability toggle (manual on/off)

### Addons & Customizations
- [ ] Create addon groups (e.g., "Extra ingredients", "Remove ingredients", "Drink size")
- [ ] Set each group as optional or required
- [ ] Set min/max selections per group
- [ ] Each option can have a price delta (positive or zero)
- [ ] Link addon groups to one or more products

### Variants
- [ ] Products can have variants (e.g., Small / Medium / Large)
- [ ] Each variant has its own price and prep time base

### Product Images
- [ ] Upload product image
- [ ] Resize and optimize on upload

## Acceptance Criteria
- A product without a base prep time cannot be saved
- Paused products do not appear on the customer storefront
- Addons with additional cost show the price delta clearly
- A product referenced in an open order cannot be deleted, only archived
- Prep time field accepts only positive integers (minutes)

## Dependencies
- Authentication & Roles (restaurant_admin, restaurant_manager)
