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

## MANDATORY workflow — Test on the screen before declaring a fix done

A bug is NOT fixed until you have observed the real surface working. This is non-negotiable.

For every UI/route/component fix:

1. **Hit the real URL via HTTP** (not `Livewire::test()`, not tinker isolation). Use `curl` with the user's session cookie extracted from the error page Headers section, or use Playwright/browser-driven verification. The test must traverse the full middleware → routing → component → blade pipeline.

2. **Capture concrete evidence**: `STATUS=200` from curl, response body grep for `Undefined`/`ErrorException`/`Internal Server Error` returning zero matches, AND positive content present (a label or value that should appear when the fix works).

3. **Always run `php artisan view:clear` before testing**. Stale compiled blades are a known source of false PASS and false FAIL.

4. **If no automated test covers the surface, create one before closing**. Minimum: a Livewire feature test (`Livewire::test(Component::class)->assertSee(...)`) for the component itself AND a Laravel feature test (`$this->actingAs($user)->get('/route')->assertOk()`) for the route — because component tests skip route resolution and route-only tests skip component state.

5. **Never declare "done" based on unit-level signals alone**: `Livewire::test()` skips middleware and route resolution; tinker render skips both; static analysis skips everything that matters at runtime.

Why this exists: declaring a fix as done before screen-testing burned three rounds of user frustration on a single Livewire dashboard bug. The Livewire component test passed (correct in isolation) but the actual route used `Route::view()` and never invoked the component — only a real HTTP request would have caught it.