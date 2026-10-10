---
name: ship-next
description: >
  Autopilot do EasyFoods. Pega o próximo card do Linear (ou acha trabalho no roadmap e abre o card),
  avisa o usuário do que vai fazer, implementa com testes, abre o pull request e faz o merge — tudo sem
  perguntar. Use só quando o usuário digitar /ship-next.
argument-hint: "[quantidade de cards | EF-123]"
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
  - Bash(composer dump-autoload*)
  - Bash(npm ci*)
  - Bash(npm install*)
  - Bash(npm run build*)
  - Bash(ls *)
  - Bash(cat *)
  - Bash(grep *)
  - Bash(powershell -File ../easyfoods-brain/scripts/sync.ps1*)
  - mcp__linear
---

# ship-next — autopilot (card → código → PR → merge)

**Digitar `/ship-next` é a aprovação prévia do usuário para o fluxo inteiro.** Neste modo:

- **Não pergunte nada.** Nada de "sigo?", "posso continuar?", "qual prefere?". Quando houver escolha,
  decida pelo padrão deste arquivo, pelo `docs/scope/` e pelo `CLAUDE.md`, e registre a decisão no card e no PR.
- O `PLAN-AND-APPROVE` do `implement-feature` **não se aplica aqui**: o plano vira comentário no card e
  descrição do PR, e você segue direto para o código.
- Fale com o usuário só em 2 momentos: o **briefing** (passo 2) e o **relatório final** (passo 9).
  Se precisar parar, use o formato de "Parada" no fim deste arquivo.

## Argumentos (`$ARGUMENTS`)
- vazio → 1 card.
- número `N` (ex. `/ship-next 3`) → até N cards em sequência, um PR por card.
- id do card (ex. `/ship-next EF-7`) → só esse card.

## 0. Pré-voo (silencioso)
1. `git status --porcelain` e `git branch --show-current`. Anote os arquivos já sujos: **eles não são seus,
   nunca os inclua em commit** (ex.: `composer.lock`, `.claude/settings.local.json`).
2. `git fetch origin` → `git switch main` → `git pull --ff-only`. Se a troca de branch falhar por causa de
   arquivos sujos que conflitam, siga a seção **Parada**.
3. `gh auth status`. Se falhar → **Parada** com o comando `gh auth login`.
4. Linear: as ferramentas `mcp__linear__*` precisam responder (ex. listar times). Se não → **Parada** pedindo
   para rodar `/mcp` e autenticar o servidor `linear`.
5. Se `public/build/manifest.json` não existir: `npm run build` (os testes HTTP precisam do manifest do Vite).
6. Baseline: `php artisan view:clear` e `php artisan test`. Guarde quais testes já falham na `main`.
   - Se algum card do Linear trata exatamente dessas falhas, ele vira o card prioritário.
7. Memória: se `docs/memory/` existir, rode `load-memory` do domínio do card; se não, use `docs/scope/` +
   `docs/progress/` (não pare por isso; só cite no relatório final).

## 1. Escolher o card
Fonte: Linear, time **EasyFoods**, projeto **Roadmap EasyFoods**.
1. Se `$ARGUMENTS` for um id → use esse card.
2. Senão, liste os cards em **Todo** e **Backlog** e escolha o primeiro que:
   - **não** está bloqueado (todos os `blockedBy` em Done/Canceled);
   - **não** tem o label `precisa-decisao`;
   - na ordem: prioridade (Urgent → Low) → marco (M0 → M1 → M2 → M3) → menor número EF.
3. Se não houver card elegível: rode `roadmap-review`, pegue a melhoria de maior alavancagem e vá para o
   passo 3 criando o card.
4. Leia o card inteiro (descrição + comentários). Confirme no código que o problema ainda existe
   (`Grep`/`Read`). Se já foi resolvido na `main`: mova para **Done** com um comentário dizendo o commit
   que resolveu, e volte para o item 2.

## 2. Briefing para o usuário (sem esperar resposta)
Escreva no chat, em até ~10 linhas, e **continue imediatamente**:
```
🎯 Card: EF-X — <título> (<prioridade>, <marco>)
📍 Por que agora: <1 linha>
🛠 Plano: <3–5 bullets: arquivos/camadas, migration?, testes>
⚠️ Riscos: <estado do pedido / tenant / schema — só se houver>
▶️ Seguindo para a implementação.
```

## 3. Abrir / atualizar o card
- Card existente: mova para **In Progress**, atribua a `me` e comente o plano (mesmo conteúdo do briefing).
- Card novo (veio do `roadmap-review` ou de algo achado agora): crie no time EasyFoods, projeto
  Roadmap EasyFoods, marco certo, label Bug/Improvement/Feature, prioridade, descrição no formato
  Problema → O que fazer → Critérios de aceite. Já crie em **In Progress**.
- Achou outro problema fora do escopo durante o trabalho? **Não conserte junto**: crie um card novo
  (Todo) e siga.

## 4. Branch
`git switch -c <tipo>/ef-<n>-<slug-curto>` a partir da `main` atualizada.
`<tipo>`: `fix` (Bug), `feat` (Feature), `chore`/`refactor` (Improvement). Nunca trabalhe na `main`.

## 5. Implementar
Siga as fases A, B, D e E do `implement-feature` (a fase C — parar para aprovação — **é pulada**):
- Rode as revisões que o card pede: `state-machine-review` (status de pedido), `multi-tenant-review`
  (queries/tabelas), `database-review` (migrations), `eta-engine-review` (tempos), `ux-operational-review`
  (telas de cozinha/balcão), `debug-livewire` (erros de Livewire).
- **Teste primeiro**: escreva o teste que reproduz o bug / descreve o critério de aceite e veja falhar.
- Implemente em passos pequenos. Respeite as Invariantes do `CLAUDE.md` (status só via
  `TransitionOrderStatus`, snapshots, `restaurant_id`, migrations reversíveis, SQL portátil SQLite+MySQL).
- Escopo = critérios de aceite do card. Nada de "já que estou aqui".

## 6. Validar (Definition of Done)
1. `php artisan view:clear`
2. Testes do card passando + teste HTTP da rota real (200, sem `Undefined`/exceção no HTML).
3. `php artisan test` inteiro: **nenhuma falha nova** em relação ao baseline do passo 0.
4. Se mexeu em view/CSS/JS: `npm run build` sem erro.
5. Auto-revisão do diff (`git diff main...HEAD`) com o checklist do `engineering:code-review` /
   `refactor-review`: segurança, tenant, N+1, validação no servidor, código morto, nomes.
- Falhou? Corrija e repita. **Máximo 3 tentativas** → depois disso, **Parada**.

## 7. Pull request
1. `git add` **somente os arquivos que você criou/alterou** (lista explícita). Nunca `git add -A`/`.`.
   Nunca inclua: `docs/memory/`, `.env*`, `.claude/settings.local.json`, `composer.lock` (a não ser que o
   card mude dependências), arquivos que já estavam sujos no passo 0.
2. Commit em inglês, convencional: `fix: <o que mudou> (EF-X)`.
3. `git push -u origin <branch>`.
4. `gh pr create --base main --title "<tipo>: <resumo> (EF-X)" --body-file <arquivo temporário>` com:
   **Card** (link do Linear) · **O que mudou** · **Como testei** (comandos + resultado) ·
   **Decisões tomadas** · **Riscos** · checklist do Definition of Done.
   Lembrete: o repositório é **público** — nada de detalhes de vulnerabilidade além do necessário, nada
   copiado de `docs/memory/`.
5. Comente no PR o resultado da auto-revisão (`gh pr comment`).
6. Linear: adicione o link do PR ao card e mova para **In Review**.

## 8. "Aprovar" e fazer o merge
O GitHub **não permite aprovar o próprio PR**; aqui "aprovar" = passar nos portões abaixo e fazer o merge.
- Portões (todos obrigatórios):
  - testes do passo 6 verdes;
  - checks do GitHub: se o repo tiver CI, `gh pr checks <n> --watch` precisa terminar verde;
  - o PR **não** apaga dados nem dropa coluna/tabela com dados, **não** muda URL pública existente,
    **não** mexe em autenticação/senha de forma irreversível.
- Passou → `gh pr merge <n> --squash --delete-branch` → `git switch main` → `git pull --ff-only`.
- Não passou em algum portão de risco → deixe o PR aberto, comente o motivo, card fica em **In Review**
  e isso entra no relatório como "precisa do seu olho".
- Depois do merge: card → **Done** com comentário (PR, commit, o que foi testado).
- Se `docs/memory/` existir: rode `save-memory` (decisões, bug resolvido, active-work) e depois
  `powershell -File ../easyfoods-brain/scripts/sync.ps1`.

## 9. Relatório final (e próximo card)
Se `$ARGUMENTS` pediu mais cards, volte ao passo 1. Ao terminar, escreva:
```
✅ Entregue: EF-X — <título> → PR #<n> (merge feito | aberto para revisão)
🧪 Testes: <total> passando (<novos> novos)
🗂 Cards criados: <EF-Y …> (problemas achados fora do escopo)
⏭ Próximo da fila: EF-Z — <título>
```

## Parada (único caso em que você para)
Use quando: 3 tentativas sem passar nos testes; arquivo sujo do usuário conflita com o card; `gh`/Linear
sem acesso; decisão de produto irreversível sem resposta em `docs/scope/`.
1. Se houver código: commit + push da branch e abra o PR como **draft** (`gh pr create --draft`).
2. Comente no card o que travou e o que foi tentado; adicione o label `precisa-decisao` (crie se não
   existir) e volte o card para **Todo**.
3. Escreva ao usuário:
```
⛔ Parei em EF-X — <título>
Motivo: <1–2 linhas>
O que você precisa fazer: <ação exata ou comando para colar>
Estado: branch <nome> · PR draft #<n> · card em Todo com label precisa-decisao
```
4. Se `$ARGUMENTS` pedia mais cards, **pule este e siga para o próximo** em vez de encerrar.

## Nunca
`git push --force` · commit/push direto na `main` · `git add -A` · apagar dados ou tabelas · editar `.env` ·
commitar `docs/memory/` · perguntar "posso seguir?".
