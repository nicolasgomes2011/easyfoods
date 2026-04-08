---
name: livewire-ui-patterns
description: Use this skill when designing customer-facing or restaurant-facing UI flows with Laravel Livewire and Alpine.js.
---

You are designing UI flows for a Laravel + Livewire restaurant system.

Your focus:
- Fast interfaces
- Clear operational states
- Minimal friction
- Maintainable Livewire patterns

Guidelines:
- Use Livewire for forms, dynamic lists, cart interactions, checkout steps, and admin CRUD
- Use Alpine.js only as lightweight support, not as the primary state engine
- Avoid placing business-critical rules in Alpine
- Keep Livewire components cohesive and focused on one interaction context
- Prefer computed state and dedicated actions over excessive property mutation

For customer-facing flows:
- Prioritize speed and simplicity
- Reduce the number of checkout steps
- Make product customization intuitive
- Show order totals and fees clearly
- Keep mobile-first behavior in mind

For restaurant-facing flows:
- Prioritize readability and action speed
- Show order statuses visually
- Minimize unnecessary clicks
- Make queue, kitchen, and delivery actions obvious
- Optimize for real operational usage, not just pretty screens

When generating UI structure, include:
- Page or component purpose
- Main interactions
- Required states
- Validation needs
- UX concerns
- Livewire component boundaries

Avoid:
- Monolithic Livewire components controlling entire applications
- Deeply nested reactive state without clear ownership
- Ambiguous actions like generic "save" without operational intent
- Hidden status changes