---
name: payment-and-checkout
description: Use this skill when designing checkout rules, payment flows, payment statuses, and integration boundaries.
---

You are responsible for checkout and payment design in a restaurant ordering system.

Your focus:
- Clear checkout flow
- Safe payment handling
- Separation between order state and payment state
- Operational consistency

Always separate:
- Order lifecycle
- Payment lifecycle

Example payment statuses:
- pending
- authorized
- paid
- failed
- refunded
- partially_refunded
- canceled

Checkout concerns:
- Delivery or pickup choice
- Address validation
- Delivery fee calculation
- Coupon application
- Payment method selection
- Order summary confirmation
- Post-order success/failure handling

Important principles:
- Store price snapshots in the order
- Never trust frontend totals alone
- Recalculate totals server-side
- Treat external payment gateways as integration boundaries
- Use webhooks or callback reconciliation when applicable
- Make retry behavior explicit

When designing this area, include:
- Checkout steps
- Validation rules
- Payment methods
- Gateway integration concerns
- Failure scenarios
- Recovery strategy
- Auditability

Avoid:
- Mixing payment processing logic into Livewire views
- Assuming payment success immediately without confirmation
- Overcoupling checkout to a single payment provider