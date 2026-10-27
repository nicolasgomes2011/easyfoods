---
name: merge-prs
description: >
  Fila de merge do EasyFoods. Pega todos os pull requests abertos, testa cada um em uma pasta separada
  (git worktree) junto com a main atual, revisa o diff e faz o merge dos que passam — sem perguntar.
  Os que falham ganham comentário e o label precisa-decisao. Use só quando o usuário digitar /merge-prs.
argument-hint: "[--dry-run] [números dos PRs]"
disable-model-invocation: true
allowed-tools:
  - Read
  - Edit
  - Write
  - Glob
  - Grep
  - Skill
  - Bash(git *)
  - Bash(gh *)
  - Bash(php *)
  - Bash(composer install*)
  - Bash(npm ci*)
  - Bash(npm run build*)
  - Bash(cd *)
  - Bash(cp *)
  - Bash(touch *)
  - Bash(ls *)
  - Bash(cat *)
  - Bash(grep *)
  - mcp__linear
---

# merge-prs — testar e fazer merge de todos os PRs abertos

**Digitar `/merge-prs` é a aprovação prévia do usuário para testar e fazer merge.** Não pergunte nada:
decida pelas regras abaixo e registre o resultado em comentário no PR (e no card do Linear, se houver).
Fale com o usuário só no relatório final.

## Argumentos (`$ARGUMENTS`)
- vazio → todos os PRs abertos contra a `main`.
- números (ex. `/merge-prs 12 15`) → só esses PRs (inclusive os que têm o label `precisa-decisao`).
- `--dry-run` → testa, revisa e comenta, mas **não faz merge**.

## Regra de ouro: não mexer na pasta de trabalho do usuário
Todo o teste acontece numa **pasta separada** (`../easyfoods-pr-check`, um `git worktree`). Na pasta
principal do projeto você **não** troca de branch, **não** faz stash, **não** edita arquivos — o usuário
(ou outra sessão) pode estar trabalhando nela agora.

## 0. Pré-voo
1. `gh auth status` — se falhar, vá para o relatório com o comando `gh auth login`.
2. Linear: as ferramentas `mcp__linear__*` respondem? Se não, siga sem Linear e avise no relatório
   (rode `/mcp` → linear).
3. `git fetch origin --prune`.
4. Pasta de teste (`W = ../easyfoods-pr-check`):
   - Se não existir: `git worktree add --detach ../easyfoods-pr-check origin/main`, depois
     `cp .env ../easyfoods-pr-check/.env` e, **no `.env` da pasta de teste** (nunca no original),
     troque `DB_CONNECTION`/`DB_DATABASE` para `sqlite` e o caminho absoluto de
     `../easyfoods-pr-check/database/pr-check.sqlite` (use `pwd -W` para o caminho Windows);
     `touch ../easyfoods-pr-check/database/pr-check.sqlite`;
     `cd ../easyfoods-pr-check && composer install --no-interaction && npm ci && npm run build`.
   - Se existir: `cd ../easyfoods-pr-check && git checkout --detach origin/main` (descarte qualquer
     resto de execução anterior com `git merge --abort` / `git reset --hard origin/main` — **só nesta pasta**).
5. Labels no GitHub: `gh label create precisa-decisao --color F2C94C --force`.
6. **Baseline da main** (na pasta de teste): `php artisan view:clear` e `php artisan test`. Guarde a
   lista de testes que já falham na `main` — PRs não podem **aumentar** essa lista.

## 1. Montar a fila
`gh pr list --state open --json number,title,headRefName,baseRefName,isDraft,labels,createdAt,body,url --limit 100`
- **Pule**: drafts; PRs com label `precisa-decisao` (a não ser que o número tenha vindo em `$ARGUMENTS`).
- PR com base diferente da `main` (empilhado): só depois que o PR base entrar. Se o base continuar
  aberto no fim da fila, pule e explique.
- Ordem: mais antigo primeiro (`createdAt`).

## 2. Para cada PR da fila
Trabalhe sempre dentro de `../easyfoods-pr-check`.

**a. Montar o código "PR + main atual"**
- `git fetch origin main "pull/<n>/head:refs/remotes/origin/pr/<n>"`
- `git checkout --detach origin/pr/<n>` → `git merge --no-edit origin/main`
- Conflito? `git merge --abort` → **reprovado: conflito com a main** (vá para o passo e).

**b. Dependências e build** (compare com `git diff --name-only origin/main...origin/pr/<n>`)
- `composer.json`/`composer.lock` mudou → `composer install --no-interaction`.
- `package.json`/`package-lock.json` mudou → `npm ci`.
- Mudou algo em `resources/`, `vite.config.js` ou `package*.json` → `npm run build` (precisa passar).

**c. Testes**
- `php artisan view:clear` → `php artisan test`.
- Aprovado só se: **nenhuma falha nova** em relação ao baseline **e** os testes que o PR adicionou passam.
- PR com migration nova: além dos testes, rode na pasta de teste (banco `pr-check.sqlite`)
  `php artisan migrate:fresh --force` e `php artisan migrate:rollback --force --step=<qtd de migrations novas>`
  — os dois precisam passar (migration reversível).

**d. Revisão do diff** (`gh pr diff <n>`), com o checklist do `engineering:code-review`:
- Reprova se tiver: arquivo em `docs/memory/`; `.env*`; segredo/token/senha; `dd(`, `dump(`, `var_dump(`;
  arquivo de teste temporário/sonda (`Tmp*`, `*Probe*`, `scratch`); SQL específico de MySQL
  (ex. `CAST(... AS INTEGER)`); migration sem `down()`.
- Invariantes do `CLAUDE.md`: status de pedido só via `TransitionOrderStatus`; snapshots de preço/nome;
  `status` ≠ `payment_status`; queries/tabelas novas escopadas por `restaurant_id`
  (use `multi-tenant-review` / `state-machine-review` se o diff tocar nisso).
- Se o título, a branch ou o corpo citam um card (`EF-123`): leia o card no Linear e confira se os
  **critérios de aceite** foram atendidos. Faltou algo essencial → reprovado com a lista do que falta.
- **Portões de risco** (não faz merge automático, mesmo com tudo verde): apaga dados ou dropa
  coluna/tabela com dados; muda/remove URL pública existente (`routes/customer.php`, rotas de auth);
  mexe em login/senha/2FA; atualização de versão **major** de dependência.
  → Resultado: **revisão manual** (não é reprovação).

**e. Checks do GitHub**: `gh pr checks <n>` — se o PR tiver checks, todos precisam estar verdes
(use `--watch`). Sem checks configurados → vale só o teste local.

**f. Decisão**
- ✅ **Aprovado** e sem `--dry-run`:
  `gh pr merge <n> --squash` (sem `--delete-branch`: ele tentaria apagar a branch local que pode estar
  aberta na pasta do usuário — a branch remota some sozinha pela configuração do repo).
  Se o merge for recusado **só** por exigir revisão e todo o resto estiver verde, use `--admin`.
  Depois: `git fetch origin` e **refaça o baseline** (passo 0.6) com a nova `main` antes do próximo PR.
- ✅ Aprovado com `--dry-run`: só comente "pronto para merge".
- 🟡 **Revisão manual** (portão de risco): comente o motivo e adicione o label `precisa-decisao`.
- ❌ **Reprovado**: comente o motivo (testes que falharam com as primeiras linhas do erro, conflito,
  item do checklist) e adicione o label `precisa-decisao`. **Não tente consertar** o PR aqui.

**g. Comentário no PR** (sempre): `gh pr comment <n> --body-file <arquivo>` com resultado, testes
(total / falhas novas), itens da revisão e, se houver, o que precisa ser feito.

**h. Linear** (se o PR cita `EF-123`):
- Merge feito → card em **Done** com comentário "Merge do PR #n via /merge-prs".
- Revisão manual ou reprovado → comentário com o motivo e label `precisa-decisao` no card.

## 3. Final
1. Na pasta de teste: `git checkout --detach origin/main` (deixa limpa para a próxima vez).
2. Relatório para o usuário:
```
🔀 /merge-prs — <n> PRs analisados

| PR | Título | Resultado | Motivo / observação |
|----|--------|-----------|---------------------|
| #12 | … | ✅ merge | 221 testes, 0 falhas novas |
| #13 | … | 🟡 revisão manual | dropa coluna orders.foo |
| #14 | … | ❌ reprovado | 2 testes novos falhando (TableSessionTest…) |

📋 Cards atualizados: EF-1 → Done …
⚠️ Falhas que já existiam na main: <lista curta ou "nenhuma">
👉 Para você: <ações exatas, se houver>
```

## Nunca
Mexer na pasta principal do projeto (trocar branch, stash, editar) · `git push --force` · push direto
na `main` · fazer merge de draft · consertar código de PR dentro desta skill · perguntar "posso seguir?".
