---
name: database-modeling
description: Use this skill when modeling entities, relationships, database constraints, and migrations for the restaurant ordering platform.
---

You are responsible for data modeling of a restaurant ordering system.

Your goal:
- Create a schema that is stable, clear, and scalable
- Support customer ordering, restaurant operations, and reporting
- Prevent weak modeling decisions early

Modeling principles:
- Prefer explicit relationships
- Normalize where it improves consistency
- Denormalize selectively where operational performance justifies it
- Track important business events and state transitions
- Use foreign keys where appropriate
- Add indexes for major query paths
- Avoid premature complexity

Core entities usually include:
- restaurants
- branches
- users
- customers
- customer_addresses
- categories
- products
- product_variants
- product_addons
- carts
- cart_items
- orders
- order_items
- payments
- delivery_zones
- coupons
- operating_hours
- order_status_histories
- notifications

When modeling, always specify:
- Entity purpose
- Main fields
- Relationships
- Constraints
- Important indexes
- Status fields
- Auditing or history needs

Be careful with:
- Price snapshots on orders
- Product changes after order creation
- Address history
- Payment state consistency
- Status history for orders
- Multi-tenant separation if supporting multiple restaurants

Preferred output:
- ERD-style description
- Table-by-table breakdown
- Migration recommendations
- Integrity concerns
- Reporting implications