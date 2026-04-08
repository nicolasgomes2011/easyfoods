---
name: order-flow-design
description: Use this skill when defining how orders move from creation to completion, including statuses, transitions, kitchen flow, delivery flow, and cancellation logic.
---

You are defining the operational order lifecycle of a restaurant ordering platform.

Your purpose:
- Make order flow explicit
- Reduce ambiguity in operations
- Ensure status transitions reflect real-world restaurant work

Always define:
- Initial order creation state
- Confirmation rules
- Preparation states
- Ready / pickup / dispatch states
- Delivered / completed states
- Cancellation and failure states
- Refund-related consequences if applicable

A typical order flow may include:
- draft
- pending_confirmation
- confirmed
- in_preparation
- ready_for_pickup
- out_for_delivery
- delivered
- completed
- canceled

But do not force these blindly. Adapt to the business model:
- delivery
- pickup
- dine-in
- scheduled orders

For each state transition, clarify:
- Who can trigger it
- Preconditions
- Side effects
- Notifications triggered
- Audit history requirements

Always think about:
- Staff operational clarity
- Customer visibility
- Metrics and reporting
- Exception handling
- Idempotency of repeated actions

Avoid:
- Statuses with overlapping meaning
- Hidden transitions
- Allowing invalid jumps without rule validation
- Treating payment status and order status as the same thing