---
name: bootstrap-project
description: >
  Load consolidated EasyFoods context at the start of a session or task. Use when the user opens a new
  session, says "carrega o contexto", "me situa", "o que tá rolando", "começa a trabalhar", "bootstrap",
  "onde paramos", or asks anything that needs project-wide grounding before acting. Reads the local memory
  (docs/memory), detects the current roadmap phase, identifies work-in-progress, summarizes the main
  architecture, surfaces active risks, and recommends which specialized skills to use next. Run this first
  before feature work, planning, or architectural review when you lack current context.
---

# bootstrap-project

Establishes situational awareness so every later action is context-aware. This is the orchestrator's
"load state" step.

## When to use
- Session start, or first non-trivial task of a session.
- Before `feature-planning`, `implement-feature`, or any review skill, when context isn't already loaded.
- When the user asks where things stand, what's in progress, or "what should we do".

## When NOT to use
- Mid-task when context is already loaded this session (don't re-bootstrap repeatedly — wasteful).
- Pure, self-contained Q&A that needs no project state ("what does this regex do").

## Execution workflow
1. **Load Tier-1 memory.** Read `docs/memory/system-overview.md`. If it doesn't exist (fresh clone — memory
   is local-only), fall back to `docs/scope/README.md` + `docs/progress/ROADMAP.md` and tell the user the
   local memory isn't present.
2. **Detect phase & WIP.** Read `docs/memory/active-work/current.md` and `docs/memory/summaries/roadmap-status.md`
   (or `docs/progress/ROADMAP.md` + `ADJUSTMENTS.md`).
3. **Note architecture.** Skim `docs/memory/architecture/stack.md` + `domain-map.md` for layer layout and
   where the touched domain lives.
4. **Surface risks.** Pull the active risks from `system-overview.md` and open items from
   `docs/memory/bugs/known-issues.md`.
5. **Verify freshness.** Memory marked `authority: code` is a claim about the code — if the user is about to
   act on it, confirm against the actual file (it may have changed).
6. **Recommend skills.** Map the user's apparent intent to the right skill(s) (see table below).
7. **Report compactly.** Output the briefing format below. Do NOT dump raw file contents.

## Architecture concerns to always carry forward
- Tenant isolation, frozen snapshots, order state machine, append-only history, payment/order independence.
- The Actions/Services layer may not exist yet — check before assuming.

## Intent → skill routing (recommend, don't auto-run destructive steps)
| Intent signal | Recommend |
|---------------|-----------|
| build/add/implement a feature | `implement-feature` (plan-and-approve) |
| design/plan only | `feature-planning` |
| order status / transition / cancel / confirm | `state-machine-review` |
| query/report/dashboard, "leak", restaurant scoping | `multi-tenant-review` |
| migration / schema / new entity | `database-review` |
| ETA / prep time / countdown | `eta-engine-review` |
| kitchen/counter UX, operational speed | `ux-operational-review` |
| "tá quebrado", error, bug | `bug-investigation` |
| repetition, "abstrai isso" | `extract-pattern` |
| status / roadmap / next steps | `roadmap-review` |
| save/recall context | `save-memory` / `load-memory` |

## Expected output (briefing template)
```
📍 Fase atual: <phase + 1-line status>
🔧 Em andamento: <top WIP items>
🏗  Arquitetura tocada: <domain + layer notes>
⚠️  Riscos ativos relevantes: <only those touching the task>
👉 Skills sugeridas: <skill(s)> — porque <reason>
```
Keep it under ~15 lines. Link to memory files instead of pasting them.

## Anti-patterns
- Re-reading the whole memory tree every turn (context bloat). Load Tier-1 + the relevant domain slice only.
- Trusting stale memory over the code. Code wins; fix the memory.
- Auto-executing changes — bootstrap only *loads and recommends*.

## Validation checklist
- [ ] Tier-1 overview (or fallback) read
- [ ] Phase + WIP identified
- [ ] Risks relevant to the task surfaced
- [ ] At least one concrete next skill recommended with a reason
- [ ] Output is compact (no raw dumps)

## EasyFoods example
**User:** "vamos implementar o cancelamento de pedido"
**Bootstrap output:** Fase 4 (order flow) — views existem, ações de transição NÃO implementadas. Em
andamento: Phase 0 cleanup. Arquitetura: domínio Orders; transições vivem em `OrderStatus` enum; não há
`app/Actions/` ainda. Riscos: cancelamento exige motivo obrigatório + escrever `order_status_histories` +
scoping por restaurante. Skills sugeridas: `implement-feature` → que puxa `state-machine-review` +
`multi-tenant-review`.
