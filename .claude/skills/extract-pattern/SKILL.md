---
name: extract-pattern
description: >
  Detect recurring structures in the EasyFoods codebase and turn them into documented conventions — and,
  when a repeated *workflow* emerges, propose a new skill. Use when you notice the same code/approach written
  three or more times, or when the user says "isso se repete", "extrai um padrão", "abstrai isso", "cria um
  helper/trait/action pra isso", "padroniza", "vira convenção". Produces a convention doc (or trait/base
  class/Action proposal) and updates patterns memory; this is the system's self-evolution mechanism.
---

# extract-pattern

Turns repetition into a single source of truth — code abstraction, documented convention, or a new skill.

## When to use
- The same structure appears ≥3 times (queries, component shapes, validation, status handling).
- The user asks to standardize/abstract, or a recurring multi-step workflow keeps happening manually.

## When NOT to use
- Two occurrences (premature — wait for the rule of three).
- A coincidental similarity that isn't truly the same concept.

## Execution workflow
1. **Gather instances.** Identify the concrete occurrences (cite files). Confirm they're the *same* concept,
   not lookalikes.
2. **Name the pattern.** What invariant/intent do they share? (e.g. "every tenant-bound model needs a global
   restaurant scope"; "every status transition writes history + milestone".)
3. **Choose the abstraction level:**
   - **Code** → trait, base class, Action, Blade component, or scope. (e.g. `BelongsToRestaurant` trait;
     `app/Actions/Orders/TransitionOrderStatus` base.)
   - **Convention** → document in `docs/memory/patterns/` (no code change yet).
   - **New skill** → if it's a recurring *workflow/review*, draft a new `.claude/skills/<name>/SKILL.md`
     with the full structure (description/when/when-not/workflow/anti-patterns/output/checklist/example).
4. **Propose, don't impose.** Present the abstraction + migration of existing call sites. For a new skill,
   write the draft and ask the user to approve before relying on it.
5. **Document & link.** Update `patterns/laravel-livewire-conventions.md` and cross-link with `[[…]]`.
   If a decision was made, add an ADR via `save-memory`.
6. **Avoid premature abstraction.** Prefer the simplest shared form; don't over-generalize for hypothetical cases.

## Architecture concerns
- Extraction must preserve invariants (tenant scope, enum transitions, snapshots) — coordinate with the
  relevant review skill.
- One concept, one home. Keep the abstraction discoverable (named, documented).

## Anti-patterns
- Abstracting at 2 occurrences, or over-engineering a generic framework.
- Creating a skill for a one-off task.
- Extracting shared code that silently changes behavior of one call site.

## Expected output
Pattern name + instances + chosen abstraction (code/convention/skill) + migration of call sites + memory
update. For a new skill: a ready-to-review draft SKILL.md.

## Validation checklist
- [ ] ≥3 genuine instances confirmed
- [ ] Pattern named by its invariant/intent
- [ ] Right abstraction level chosen (not premature)
- [ ] Existing call sites migrated/listed
- [ ] Invariants preserved
- [ ] patterns memory + ADR updated; links added

## EasyFoods example
You implement `CancelOrder`, then `MarkOrderReady`, then `ConfirmOrder` — each validates a transition, writes
`order_status_histories`, and sets a milestone timestamp. That's the rule of three → extract
`app/Actions/Orders/TransitionOrderStatus` (params: order, target status, actor, optional reason) that all
three call. Document the "transition action" convention in patterns memory, and record an ADR. If you also
find you keep manually doing "review state machine + tenant + write test" for every order action, that's
already captured as `implement-feature` — no new skill needed.
