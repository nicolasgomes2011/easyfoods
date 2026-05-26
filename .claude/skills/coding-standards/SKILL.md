---
name: coding-standards
description: Use this skill when writing or refactoring code in the restaurant platform to keep patterns, naming, structure, and implementation quality consistent.
---

You are enforcing coding standards for a Laravel + Livewire codebase.

Standards:
- Prefer clear naming over short naming
- Use domain language consistently
- Keep methods focused
- Avoid giant classes
- Keep controllers thin
- Keep Livewire components lean
- Move business logic to dedicated classes
- Use enums for statuses and fixed business vocabularies
- Validate input clearly
- Handle errors explicitly
- Favor readability over cleverness

Laravel conventions:
- Use Form Requests where useful
- Use Policies for authorization
- Use Jobs for async work
- Use Events for domain-triggered side effects
- Use Eloquent relationships intentionally
- Avoid N+1 issues
- Keep migrations reversible and clear

Livewire conventions:
- One component, one clear responsibility
- Avoid sprawling public properties
- Keep UI state separate from domain rules
- Use actions with explicit names
- Prefer stable, predictable state flow

Code review checklist:
- Is business logic in the right layer?
- Are names clear?
- Are validations complete?
- Are status transitions protected?
- Is the code testable?
- Is there hidden coupling?
- Are queries efficient?
- Is the UX state reliable?

Avoid:
- Magic strings for statuses
- Repeated rule logic across components
- Massive save() methods doing everything
- Silent failures

## Definition of Done (DoD) — mandatory for every change

A change is NOT done until ALL of these are true:

1. **The real surface was tested.** For any UI/route/component change: an HTTP request was made against the real URL (`curl --cookie ...` or browser) and returned `STATUS=200` with no `Undefined`/`ErrorException` in the body, plus expected content was confirmed present.
2. **`php artisan view:clear` was run before testing.** Stale compiled blades cause false PASS/FAIL.
3. **Automated test exists or was created.** If no test covered the surface, create one before closing — feature test (`->actingAs($user)->get('/route')->assertOk()->assertSeeLivewire(...)`) plus Livewire component test (`Livewire::test(...)->assertSee(...)`). Component tests alone do NOT prove the route works.
4. **No declaration of "pronto" / "fixed" without captured evidence.** Concrete observation > confidence.

This applies on every change, no exceptions. Use [[debug-livewire]] and [[testing-and-quality]] for the detailed procedures.