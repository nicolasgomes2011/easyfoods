---
name: save-memory
description: >
  Persist important EasyFoods knowledge to the local project memory (docs/memory/), auto-categorized by
  type and domain. Use when a decision is made, a business rule/invariant is established, a bug is found or
  fixed, the focus/phase changes, or a reusable pattern emerges — and whenever the user says "salva isso",
  "registra essa decisão", "guarda esse contexto", "anota", "lembra disso", "save this to memory". Detects
  what kind of information it is, dedups against existing files, writes structured entries with frontmatter,
  and keeps summaries/index lean (context economy). Project memory is local-only and may hold sensitive
  intelligence; user/preference facts go to the Claude Code auto-memory instead.
---

# save-memory

Writes durable EasyFoods knowledge into `docs/memory/` (local-only, gitignored — safe for sensitive detail).

## When to use
- An architectural decision, business rule, confirmed bug/debt, phase change, or reusable pattern emerged.
- The user explicitly asks to remember/record something about the codebase.
- End of `implement-feature` / `bug-investigation` to persist what changed.

## When NOT to save here
- **User/personal preferences or working style** → Claude Code auto-memory (`~/.claude/projects/.../memory/`), not here.
- Anything trivially re-derivable from current code (don't snapshot what `git`/the code already says).
- Ephemeral in-conversation state (use tasks/plan, not memory).
- Secrets/credentials.

## Step 1 — Categorize (auto-detect type → destination)
| Information | File |
|-------------|------|
| Architectural decision / tradeoff chosen | `decisions/architectural-decisions.md` (ADR, newest on top) |
| Business rule / invariant | `business-rules/<topic>.md` |
| Confirmed defect / debt / its fix | `bugs/known-issues.md` |
| WIP / focus / phase change | `active-work/current.md` |
| Reusable code pattern / convention | `patterns/<topic>.md` |
| Roadmap/status shift | `summaries/roadmap-status.md` |
| Stack / layer / routing fact | `architecture/<topic>.md` |

## Step 2 — Dedup first
Glob `docs/memory/**/*`. If a file already covers the topic, **update it** (and bump `last_verified`)
rather than creating a near-duplicate. Merge, don't fork.

## Step 3 — Write structured entry
Frontmatter on every file:
```markdown
---
domain: <orders|catalog|cross-cutting|architecture|overview|...>
authority: <code|scope|design|mixed>
last_verified: <YYYY-MM-DD>
---
```
Body: lead with the fact/decision; for decisions/rules add **Why** + **Consequence/How to apply**. Be
concise — tables/bullets over prose. Cross-link related memory with `[[name]]`.

## Step 4 — Keep it lean (context economy)
- Long/old detail → compress into `summaries/`, leave a link.
- Superseded decisions → mark `superseded`, don't delete (archival not erasure).
- Keep `system-overview.md` and `active-work/current.md` short; they're read most often.
- Trim shipped items from `active-work/current.md` into `summaries/roadmap-status.md`.

## Step 5 — Maintain links
If you created a new file, add a `[[link]]` from the most relevant existing file (and `README.md`/
`system-overview.md` map if it's a major topic).

## Architecture concerns
- Never write current vulnerabilities/debt into the *versioned skills* — that intelligence lives here, in
  local memory. (See [[../workflows/agent-behavior]] / AD-007.)
- Respect authority: mark `authority: code` when the fact mirrors code (future-you must re-verify it).

## Anti-patterns
- Duplicating an existing memory instead of updating it.
- Saving re-derivable code facts or secrets.
- Dumping a wall of prose with no frontmatter/links.
- Putting user preferences in project memory (wrong store).

## Validation checklist
- [ ] Correct category/destination chosen
- [ ] Deduped against existing files (updated if present)
- [ ] Frontmatter + Why/Consequence where relevant
- [ ] Cross-links added
- [ ] Lean (summarized/archived as needed)
- [ ] Told the user what was saved + where (one line)

## EasyFoods example
After implementing `CancelOrder`: save an ADR ("first `app/Actions/Orders/` action; transition actions
write history + milestone + reason") in `decisions/architectural-decisions.md`; move BUG/feature items in
`active-work/current.md`; if a transition-action convention emerged, note it in
`patterns/laravel-livewire-conventions.md`. Report: "Salvei a ADR-008 e atualizei active-work."
