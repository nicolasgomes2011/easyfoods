# task.md

## Context

We are building a **web application for restaurant management**, focused on **internal operation, service flow, and decision support**.

This product is **not** a marketplace and must **not** be modeled as an iFood clone.

The central goal is to help the restaurant operate better by organizing:

- menu/catalog management
- order flow
- dining room / table control
- kitchen workload
- customer records and history
- operational capacity
- estimated waiting time
- real-time operational visibility

The current project already has a base interface from the **Laravel Starter Kit**, including a default sidebar and a placeholder dashboard. This needs to be replaced with a navigation structure that reflects the real product.

The system should evolve as an **operational SaaS/admin panel** for restaurants.

---

## Important execution note

Before implementing anything, you must use the existing project skills as guidance for structure, architecture, and scope decisions.

Relevant skills that should guide your reasoning and implementation include, at minimum:

- `.claude/skills/product-discovery/SKILL.md`
- `.claude/skills/laravel-architecture/SKILL.md`
- `.claude/skills/livewire-ui-patterns/SKILL.md`
- `.claude/skills/database-modeling/SKILL.md`
- `.claude/skills/order-flow-design/SKILL.md`
- `.claude/skills/restaurant-backoffice/SKILL.md`
- `.claude/skills/customer-ordering-flow/SKILL.md`
- `.claude/skills/payment-and-checkout/SKILL.md`
- `.claude/skills/notifications-and-realtime/SKILL.md`
- `.claude/skills/testing-and-quality/SKILL.md`
- `.claude/skills/task-breakdown/SKILL.md`
- `.claude/skills/coding-standards/SKILL.md`

You must **read and use those skills before proposing or changing the application structure**.

Do not ignore them.
Do not make assumptions that conflict with them.
If there is a conflict between this file and the project skills, reconcile it explicitly and document the decision.

---

## Objective of this task

Redesign the current admin navigation and define the first real version of the restaurant dashboard.

The immediate goal is to replace the generic starter-kit sidebar with a menu architecture aligned with the product.

This task is **not yet about implementing the full business logic of the platform**.
It is about creating the correct **information architecture**, **module boundaries**, **navigation**, and **dashboard structure** so the rest of the product can be built on top of a solid foundation.

---

## Product direction

This system is intended to support the restaurant in day-to-day operation.

The product should cover, progressively:

1. **Operational control**
   - orders
   - order queue
   - production flow
   - dining room occupancy
   - service bottlenecks

2. **Menu and service structure**
   - categories
   - products
   - add-ons
   - variations
   - item availability
   - prep time per item

3. **Customer management**
   - customer registry
   - order history
   - notes/preferences
   - frequency / recurrence visibility

4. **Operational intelligence**
   - estimated waiting time
   - peak hours
   - average preparation time
   - kitchen capacity constraints
   - occupancy / service load indicators

5. **Management support**
   - reports
   - performance indicators
   - sales insights
   - future settings and operational parameters

---

## Strategic product principle

This application should behave more like a mix of:

- restaurant backoffice
- operations dashboard
- dining room / kitchen coordination panel
- customer/order control center

And less like:

- public ordering marketplace
- consumer marketplace app
- app aggregator

The architecture should reflect that distinction from the beginning.

---

## Current state

The current screen is still essentially the Laravel Starter Kit default dashboard, with:

- generic sidebar
- generic dashboard cards/skeleton blocks
- no domain-driven navigation
- no restaurant-oriented information hierarchy

This must be replaced.

---

## Expected outcome

At the end of this task, the project should have a clear and defensible proposal for:

1. main navigation architecture
2. menu grouping
3. submenu structure
4. dashboard composition
5. MVP module prioritization
6. future-ready modular expansion path

If implementation is started in this task, it should focus on the structural/UI layer only, not on completing all domain rules.

---

# Phase 1 — Functional architecture definition

## Your first deliverable

Before changing files, produce a concise but solid functional proposal containing:

### 1. Final sidebar navigation tree

Organize the system into clear groups.

Recommended high-level grouping:

- **Operation**
- **Management**
- **Configuration**

Within those groups, propose the best menu architecture for this product.

The minimum expected menus should be evaluated around the following domains:

- Dashboard
- Orders
- Dining Room / Tables
- Kitchen
- Menu
- Customers
- Operational Capacity / Wait Time
- Reports
- Settings

You may improve the naming if you find a clearer, more scalable vocabulary.

### 2. Short description for each menu

Each menu/submenu should have a short explanation covering:

- what it is responsible for
- why it belongs in the system
- whether it belongs to MVP or future phase

### 3. Dashboard proposal

Define what the initial dashboard should display.

The dashboard should prioritize **live operational visibility**, not generic admin widgets.

At minimum, evaluate widgets/sections like:

- open orders
- orders in preparation
- ready orders
- occupied tables
- average prep time
- estimated wait time
- order volume today
- revenue today
- current bottlenecks / alerts
- recent urgent items
- kitchen workload snapshot
- dining room occupancy snapshot

### 4. MVP vs future breakdown

Separate:

- what must exist in the first usable version
- what can remain as future expansion

This is required so the product does not become bloated too early.

---

# Phase 2 — Menu architecture to be implemented

After defining the architecture, the sidebar should be rebuilt around a structure close to this, unless a better version is justified.

## Suggested baseline navigation

### Group: Operation

- **Dashboard**
- **Orders**
  - All orders
  - New order
  - In progress
  - History
- **Dining Room**
  - Tables
  - Waiting list
  - Reservations *(future, optional)*
- **Kitchen**
  - Preparation queue
  - Stations / sectors *(future, optional)*
- **Operational Capacity**
  - Wait time estimation
  - Capacity rules *(future or advanced)*

### Group: Management

- **Menu**
  - Categories
  - Products
  - Add-ons
  - Variations / combos *(depending on modeling approach)*
  - Availability
- **Customers**
  - Customer list
  - Customer history
  - Notes / preferences *(future if necessary)*
- **Reports**
  - Sales
  - Most ordered items
  - Peak hours
  - Operational performance

### Group: Configuration

- **Settings**
  - Restaurant profile
  - Opening hours
  - Users and roles
  - Operational parameters
  - Integrations *(future)*
  - Notifications *(future)*

This is a baseline, not a prison.
You may refine it, merge items, or split them if you justify the decision clearly.

---

# Phase 3 — Dashboard design principles

The new dashboard must not be a decorative admin landing page.
It should behave like an operational control panel.

## Dashboard priorities

The dashboard should answer questions like:

- What is happening right now?
- Where is the bottleneck?
- How overloaded is the kitchen?
- How many orders are pending?
- How long is the estimated wait for new orders?
- How many tables are occupied?
- Is the restaurant under normal load or stress?

## Recommended dashboard structure

### Top summary cards
Use concise operational KPIs such as:

- Open orders
- In preparation
- Ready for delivery / serving
- Occupied tables
- Average prep time
- Estimated wait time
- Revenue today

### Middle section
Operational detail blocks, such as:

- order volume by hour
- kitchen queue summary
- latest critical orders
- delayed orders
- occupancy snapshot
- service load indicator

### Lower section
Support panels such as:

- best selling items today
- alerts / anomalies
- low availability items *(future if stock is modeled)*
- recent customer activity *(optional)*

---

# Phase 4 — UX and architecture constraints

The solution must follow these constraints:

## Navigation and usability

- The sidebar must remain clean and understandable.
- Avoid stuffing too many first-level menu items.
- Prefer grouping by operational context.
- Use clear labels, not abstract admin names.
- Design for a restaurant manager or staff member who needs quick orientation.

## Product architecture

- Think modularly.
- Menu structure should reflect future modules without forcing all of them into MVP.
- Do not overfit the structure to the current placeholder UI.
- The architecture should work whether the frontend is Blade + Livewire or evolves later.

## Technical direction

- Respect Laravel conventions.
- Respect the project coding standards skill.
- Prefer maintainable component boundaries.
- If you implement menu config, prefer a structure that can evolve cleanly.

## Scope control

- Do not attempt to build the whole ERP in this task.
- Focus on information architecture and dashboard structure first.
- Any UI implementation should be enough to establish direction, not to fake a finished system.

---

# Phase 5 — Expected implementation work

After presenting the functional proposal, proceed with the structural implementation.

## Minimum implementation target

Implement enough of the UI structure so that the project visibly stops looking like the Laravel Starter Kit default and starts looking like a restaurant operations product.

### This includes

- updating the sidebar menu
- renaming/reorganizing menu groups
- creating placeholder routes/pages/components where necessary
- updating the dashboard content structure
- replacing generic cards with restaurant-oriented blocks/placeholders
- ensuring the navigation reflects the chosen architecture

### This does not require

- complete CRUDs
- complete database modeling
- final report logic
- real-time processing
- final estimation engine

Use placeholders where needed, but meaningful placeholders aligned with the domain.

---

# Implementation guidance

## Preferred execution order

1. Read the project skills
2. Analyze current layout/sidebar/dashboard structure
3. Propose final navigation tree
4. Validate menu grouping and MVP boundaries internally
5. Implement sidebar restructuring
6. Replace dashboard skeleton with restaurant-focused dashboard structure
7. Create placeholder destinations for main modules if needed
8. Summarize what was done and what remains

## When creating placeholders

Do not create meaningless “empty pages”.
Each placeholder page should indicate its future domain clearly.

Examples:
- Orders page should already suggest filters/status areas
- Kitchen page should suggest queue/station flow
- Tables page should suggest occupancy/map/list direction
- Menu page should suggest categories/products/add-ons structure

---

# Deliverables required from you

At the end of the task, provide:

## 1. Functional proposal summary
A concise summary of:
- final navigation tree
- menu purpose
- MVP vs future modules
- dashboard structure

## 2. Implementation summary
A list of:
- files created
- files changed
- why each change was made

## 3. Architectural notes
Explain briefly:
- why the chosen structure is appropriate for a restaurant control system
- how it supports future growth
- what was intentionally left out of MVP

---

# Non-goals

To avoid scope drift, do not treat these as required for this task unless they become necessary for structural coherence:

- public customer-facing ordering app
- marketplace integration
- payment gateway implementation
- inventory/stock control in depth
- fiscal/tax modules
- full reservation engine
- delivery routing system
- advanced loyalty program
- AI optimization engine
- production-grade forecasting

They may exist later, but they are not the current objective.

---

# Product quality bar

The result should feel like the beginning of a serious SaaS/admin system for restaurants.

That means:

- coherent information architecture
- good naming
- realistic operational thinking
- structured MVP boundaries
- clean UI direction
- clear domain intent

Do not deliver something generic.
Do not keep starter-kit semantics if they no longer fit.
Do not create a dashboard that could belong to any random admin panel.

This needs to look and feel specifically like a restaurant operations platform.

---

# Final instruction

Use the project skills.
Think like a product architect, not just a code generator.
Make explicit decisions.
Keep the solution clean, modular, and extensible.
Prioritize operational clarity.
