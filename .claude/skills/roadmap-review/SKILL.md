---
name: roadmap-review
description: >
  Report where EasyFoods stands against its roadmap and recommend the highest-leverage next step. Use when
  the user asks "em que fase estamos", "o que falta", "próximos passos", "status do projeto", "o que fazer
  agora", "revisa o roadmap", "prioridades". Reconciles docs/progress/ROADMAP.md + ADJUSTMENTS.md with the
  memory snapshot and the real code state, flags blockers, and proposes a prioritized next action — without
  starting implementation.
---

# roadmap-review

Gives an honest status read and a prioritized recommendation. Analysis only — no code.

## When to use
- Status questions, prioritization, "what should we do next", sprint/scope planning.

## When NOT to use
- A concrete build request (`implement-feature`) or design (`feature-planning`).

## Execution workflow
1. Read `docs/memory/summaries/roadmap-status.md` (snapshot) + `docs/memory/active-work/current.md` (WIP).
2. Cross-check the authoritative `docs/progress/ROADMAP.md` and `ADJUSTMENTS.md` — the memory snapshot may be
   stale; the roadmap files win for phase detail. If they disagree, note it and refresh the snapshot.
3. **Validate against code** for anything you're about to recommend acting on (authority: code). E.g. "kitchen
   actions missing" → confirm the component still shows no transition action before advising.
4. Identify **blockers** (Phase 0 cleanup blocks feature trust; tenant scoping blocks multi-restaurant) and
   **leverage** (what unlocks the most: e.g. the order/kitchen transition Actions turn existing UIs into a
   working core loop).
5. Recommend the next step + the skill to use for it. Keep it to a short ranked list.
6. Offer to refresh `summaries/roadmap-status.md` if it drifted (via `save-memory`).

## Architecture concerns
- Respect the stated sequencing: Phase 0 before new features; tenant isolation before a 2nd restaurant.
- Prefer steps that complete the core loop over breadth.

## Anti-patterns
- Reporting from the stale snapshot without checking the roadmap files / code.
- Recommending net-new features while Phase 0 blockers remain.
- Starting implementation (this skill is read-only analysis).

## Expected output
```
📊 Status por fase: <compact table or bullets>
🚧 Bloqueios: <…>
🎯 Maior alavancagem agora: <1–3 ranked, each with the skill to use>
🔄 Snapshot desatualizado? <sim/não + offer to refresh>
```

## Validation checklist
- [ ] Snapshot + roadmap files reconciled
- [ ] Code-verified any actionable claim
- [ ] Blockers identified
- [ ] Prioritized next step + skill named
- [ ] Offered snapshot refresh if drifted

## EasyFoods example
"o que falta?" → Phase 0 (cleanup) active and blocking; Phase 1 ~70% (tenant scoping pending); Phase 2
partial (employees 0%, settings not wired, order/kitchen actions missing). Highest leverage: (1) finish
Phase 0 so testing is trustworthy; (2) implement order/kitchen transition Actions (`implement-feature`) —
turns existing views into a working core loop; (3) enforce tenant scoping (`multi-tenant-review`) before a
2nd restaurant. Snapshot matches ROADMAP.md as of 2026-05-26.
