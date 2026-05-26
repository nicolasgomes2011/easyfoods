---
name: load-memory
description: >
  Load only the relevant slice of EasyFoods project memory (docs/memory/) for the current task, producing a
  compact operational briefing without context bloat. Use when the user asks "carrega a memória", "load
  context", "o que você sabe sobre o projeto", "me diz o contexto salvo", "leia a memória", or when a task
  needs grounding in saved rules/decisions. Prioritizes the Tier-1 overview plus the touched domain's
  business rules, prefers recent and critical files, verifies code-authority facts before relying on them,
  and synthesizes rather than dumping raw file contents.
---

# load-memory

Retrieves the *right* memory, lean. The counterpart to `save-memory`; the recall step inside `bootstrap-project`.

## When to use
- Starting a task that needs project grounding; the user asks to recall context.

## When NOT to use
- Self-contained questions needing no project state.
- When context is already loaded this session (don't reload the tree repeatedly).

## Execution workflow (tiered — context economy)
1. **Tier 1 (always):** read `docs/memory/system-overview.md`. If absent (fresh clone — memory is
   local-only), say so and fall back to `docs/scope/` + `docs/progress/`.
2. **Identify the domain** of the task; use `architecture/domain-map.md` to pick the relevant slice.
3. **Tier 2 (by domain):** read only the relevant `business-rules/*` + `architecture/*` files (e.g. orders →
   `order-state-machine` + `data-integrity`; any query → `tenant-isolation`).
4. **Tier 3 (on demand only):** `decisions/`, `bugs/known-issues.md`, `active-work/current.md`,
   `summaries/`, `project-context.md` — pull a specific file only if the task needs it.
5. **Prioritize recent & critical.** Prefer higher `last_verified` and files tagged high-risk; if two
   conflict, the more recent / `authority: code` one wins.
6. **Verify before acting.** For `authority: code` facts the user is about to act on, confirm against the
   actual file (it may have changed) — recall is a hypothesis, the code is truth.
7. **Synthesize, don't dump.** Output a compact briefing relevant to the task; link to files for detail.

## Output format
```
🧠 Contexto relevante (<domain>):
- Regras que se aplicam: <1–3 bullets + [[links]]>
- Decisões já tomadas (não reabrir): <…>
- Riscos/bugs a evitar: <…>
```
Keep it short. Never paste whole files. If the user asked about a specific topic, return only that slice.

## Architecture concerns
- Don't over-fetch memory (bloats context). Tier 1 + the domain slice is usually enough.
- Treat stale memory as a lead, not a fact; reconcile with code and (via `save-memory`) fix drift.

## Anti-patterns
- Reading the entire `docs/memory` tree for a narrow task.
- Dumping raw file contents instead of synthesizing.
- Trusting a `authority: code` memory without checking the file when about to act.
- Reloading every turn.

## Validation checklist
- [ ] Tier-1 overview read (or fallback noted)
- [ ] Only the relevant domain slice loaded
- [ ] Recent/critical prioritized; conflicts resolved toward code/recency
- [ ] Code-authority facts verified before action
- [ ] Output compact + synthesized + linked

## EasyFoods example
Task: "ajusta a fila da cozinha". Load: system-overview (Tier 1) + `order-state-machine` + `tenant-isolation`
(domain slice). Briefing: transitions must go through the enum + write history; kitchen queries are currently
unscoped (tenant risk); kitchen actions aren't implemented yet (DEBT-003). Skip ETA/customer/decisions files —
not needed for this task.
