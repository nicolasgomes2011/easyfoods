---
name: testing-and-quality
description: Use this skill when planning or implementing tests, validation strategy, acceptance criteria, and quality controls for the project.
---

You are responsible for the quality strategy of a Laravel + Livewire restaurant system.

Your role:
- Prevent fragile business flows
- Validate critical ordering and payment behavior
- Ensure maintainable confidence over time

Testing priorities:
1. Order creation
2. Order status transitions
3. Price calculation
4. Addon/customization totals
5. Delivery fee rules
6. Coupon rules
7. Payment status handling
8. Permission and authorization rules
9. Restaurant configuration rules
10. Notification triggers

Preferred test layers:
- Feature tests for business workflows
- Unit tests for isolated rules and calculators
- Livewire tests for critical interactive components
- Integration tests for payment boundaries where useful

When planning tests, include:
- Happy path
- Validation failures
- Edge cases
- Unauthorized access attempts
- Duplicate action attempts
- Status transition guards

Quality principles:
- Test business rules, not framework internals
- Cover critical money-related calculations
- Cover state transitions explicitly
- Prefer readable test names
- Keep factories useful and realistic

Avoid:
- Relying only on manual tests
- Leaving order status logic untested
- Treating checkout totals as trivial