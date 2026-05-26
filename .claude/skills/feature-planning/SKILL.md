---
name: feature-planning
description: >
  Turn a feature request into a concrete, review-checked implementation plan for the EasyFoods Laravel +
  Livewire codebase — without writing code yet. Use when the user says "planeja", "como faríamos X",
  "qual a abordagem", "desenha a solução", "antes de implementar", or wants design/scoping before code.
  Detects affected domain and entities, identifies state-machine / multi-tenant / data-integrity impact,
  proposes migrations, Actions/Services, Livewire components and routes, and produces a step-by-step plan
  with a test strategy. This is the design half of implement-feature; it stops at the plan.
---

# feature-planning

Designs the implementation before any code. Output is a plan the user can approve or redirect.

## When to use
- The user wants an approach/design, or you're inside `implement-feature` at the planning step.
- Any non-trivial change touching orders, catalog, tenancy, schema, or kitchen flow.

## When NOT to use
- One-line/obvious edits (just do them).
- Pure investigation of a bug (use `bug-investigation`).
- The user explicitly wants code now for a trivial change.

## Execution workflow
1. **Ground context.** Ensure `bootstrap-project` ran this session; if not, load `docs/memory/system-overview.md`
   + the domain slice via `docs/memory/architecture/domain-map.md`.
2. **Define scope & acceptance.** Restate the feature in one sentence + concrete acceptance criteria
   (what proves it works). Align with `docs/scope/` and flag any scope/code divergence.
3. **Impact analysis.** Identify:
   - Entities/models touched and new ones needed.
   - **State machine** impact → consult `state-machine-review` concerns if order status is involved.
   - **Multi-tenant** impact → every new query/table scoped by `restaurant_id`? (`multi-tenant-review`).
   - **Data integrity** → frozen snapshots, append-only history, payment independence.
   - **ETA** impact if prep/transit times are involved (`eta-engine-review`).
   - **UX** for operational screens (`ux-operational-review`).
4. **Propose structure.** Migrations (reversible), Enums, an `app/Actions/` action (create the layer if it
   doesn't exist yet), Policy, Livewire/Volt component, route (in `default_routes_web.php`), events/jobs if
   needed. Follow `docs/memory/patterns/laravel-livewire-conventions.md`.
5. **Sequence the work.** Ordered steps, smallest safe increments, each independently testable.
6. **Test strategy.** Feature test (real route via actingAs) + Livewire component test + the
   `coding-standards` Definition of Done (hit the real URL). State what proves done.
7. **Risks & open questions.** List unknowns; ask the user rather than guess (project guardrail).

## Architecture concerns
- Keep business rules in Actions/Services, not Blade/Livewire hooks.
- New status transitions must exist in the `OrderStatus` enum first.
- Reversible migrations; no MySQL-specific SQL (SQLite dev).

## Expected output (plan template)
```
🎯 Feature: <1 line> · Acceptance: <bullets>
🗂  Impacto: entidades <…> · state machine <sim/não> · tenant <sim/não> · integridade <…>
🧱 Estrutura proposta: migrations / enums / actions / policy / componentes / rota
🪜 Passos: 1… 2… 3… (incrementos testáveis)
🧪 Testes: <feature + component + DoD>
❓ Riscos / perguntas: <…>
```

## Anti-patterns
- Planning code in `routes/web.php` (legacy — use `default_routes_web.php`).
- Forgetting tenant scoping or status-history writes in the plan.
- A plan with no test strategy or no acceptance criteria.
- Designing for hypothetical future requirements beyond the request.

## Validation checklist
- [ ] One-sentence scope + acceptance criteria
- [ ] Entity/state-machine/tenant/integrity impact each addressed
- [ ] Structure names concrete files/classes and follows conventions
- [ ] Steps are ordered and independently testable
- [ ] Test strategy + DoD stated
- [ ] Open questions surfaced (not silently assumed)

## EasyFoods example
**Request:** "cria employee management"
**Plan:** Acceptance: admin convida staff por email, staff define senha no 1º acesso, admin ativa/desativa,
atribui role. Impacto: `User` (+invite token, status), `UserRole` enum existe; tenant: usuários escopados por
`restaurant_id`; sem impacto em state machine de pedidos. Estrutura: migration (colunas invite/status),
`app/Actions/Staff/{InviteStaff,DeactivateStaff,AssignRole}`, `UserPolicy` checks, Livewire `Staff/StaffList`
+ `Staff/InviteForm`, rota em `default_routes_web.php` com role middleware. Passos: migration → actions →
policy → componentes → emails → testes. Testes: feature (actingAs admin → /admin/staff 200), component
(convite cria token), DoD na URL real. Perguntas: e-mail transacional já configurado? reaproveitar fluxo
de reset de senha para o 1º acesso?
```
