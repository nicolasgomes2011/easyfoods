---
name: rotina-executor
description: >
  Um ciclo da rotina automática do EasyFoods, feito para rodar a cada 3 horas (00h, 03h, 06h, 09h, 12h,
  15h, 18h e 21h) pela tarefa agendada local do Claude Code Desktop. Faz o merge dos PRs prontos
  (/merge-prs), apaga as branches já mergeadas e implementa até 2 cards por ciclo até abrir os PRs
  (/ship-next --sem-merge) — sem perguntar nada.
  Use só quando o usuário ou a tarefa agendada digitar /rotina-executor.
argument-hint: "[--so-merge]"
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
  - Bash(cd *)
  - Bash(cp *)
  - Bash(touch *)
  - Bash(ls *)
  - Bash(cat *)
  - Bash(grep *)
  - Bash(date*)
  - Bash(find ../.rotina.lock*)
  - Bash(rm ../.rotina.lock)
  - Bash(powershell -File ../easyfoods-brain/scripts/sync.ps1*)
  - mcp__linear
  - mcp__Linear
---

# rotina-executor — um ciclo da rotina (merge → limpeza → cards)

**Digitar `/rotina-executor` é a aprovação prévia do usuário para o ciclo inteiro.** Não pergunte nada.
Cada ciclo é uma sessão nova, e isso é de propósito: quem faz o merge de um PR (passo 1) nunca é a sessão
que escreveu o código dele (passo 3 do ciclo anterior).

Pasta esperada: esta pasta (`easyfoods`) com `../easyfoods-brain` e `../easyfoods-pr-check` ao lado.
No PC do Nicolas: `C:\Users\User\EasyFoods-autopilot\easyfoods`.

## Argumentos (`$ARGUMENTS`)
- vazio → ciclo completo.
- `--so-merge` → só os passos 0, 1, 2 e 4 (nenhum card novo).

## 0. Hora e trava
1. Anote a hora local (America/Sao_Paulo) em que o ciclo começou: é o **início do ciclo**. A rotina roda a
   cada 3 horas, em qualquer horário, e também numa execução de recuperação quando o PC acorda. **Não há
   janela de horário**: todo ciclo faz merge, limpeza e cards.
2. Trava em `../.rotina.lock` (na pasta acima do projeto; é a mesma do `/cacar-bugs`):
   - Existe e foi modificada há **menos de 4 horas** → encerre com `⏸ outra rotina ainda rodando`.
     (Git Bash: `find ../.rotina.lock -mmin -240`; PowerShell: `(Get-Item ..\.rotina.lock).LastWriteTime`.)
   - Existe e tem **4 horas ou mais** → apague (ciclo anterior travou), siga e cite no relatório.
   - Crie a trava (`date > ../.rotina.lock`).
   - **Sempre** apague a trava no passo 4, inclusive depois de Parada ou erro.
3. Pré-voo do `/ship-next` (itens 1 a 5 do passo 0 dele): arquivos sujos anotados, `main` atualizada,
   `gh` logado, Linear respondendo, manifest do Vite. Falhou algum → relatório com o comando para o Nicolas.

## 1. Merge dos PRs prontos
Leia e siga `.claude/skills/merge-prs/SKILL.md` inteiro, **sem argumentos** (todos os PRs abertos contra a
`main`, menos drafts e os com `precisa-decisao`). Ela testa cada PR junto com a `main` atual na pasta
`../easyfoods-pr-check`, faz o merge dos aprovados, apaga a branch remota de cada um e atualiza o card.
- Nenhum PR aberto → siga.
- **Merge bloqueado por permissão** (o `gh pr merge` é negado pelo modo automático do Claude Code, por
  exemplo "Merge Without Review"): o bloqueio vale para todos os PRs, então **não teste os outros PRs neste
  ciclo**. Comente no card do Linear do PR (só se o último comentário do card ainda não disser isso):
  "PR #N aprovado nos testes, merge bloqueado pela permissão do modo automático — falta liberar
  `Bash(gh pr merge *)` em `.claude/settings.json` da pasta do piloto". Cite no relatório e siga para o passo 2.

## 2. Limpeza de branches
1. `git fetch origin --prune`
2. Branches remotas já contidas na `main`: `git branch -r --merged origin/main`, menos `origin/main` e
   `origin/HEAD` → `git push origin --delete <nome>` para cada uma.
3. **Nunca** apague branch não mergeada nem branch com PR aberto. Branch antiga sem PR e não mergeada:
   só liste no relatório ("parada há N dias, sem PR").

## 3. Cards do ciclo
Pule este passo com `--so-merge`. Repita os itens abaixo, um card por vez:
1. **Antes de começar cada card**, confira. Se qualquer item valer, não comece card novo e vá para o passo 4:
   - já foram feitos **2 cards** neste ciclo;
   - já se passaram **2 horas** desde o início do ciclo — deixa este ciclo terminar antes do próximo
     (que começa 3 horas depois do início deste).
   Um card já começado não é interrompido por essas regras: termine-o até o PR.
2. `git switch main` → `git pull --ff-only`.
3. Leia e siga `.claude/skills/ship-next/SKILL.md` com os argumentos `1 --sem-merge`: **um card**, até o PR
   aberto e o card em **In Review**. Ao escolher o card, ajuste a regra do `/ship-next` assim:
   - **nunca** pegue um épico (card com sub-cards) — o trabalho são os sub-cards;
   - ordem: **Erro** → **Bug** → Feature/Improvement; dentro de cada um, prazo (data de entrega) mais
     próximo primeiro (os estourados antes), depois prioridade e marco;
   - só pegue card do **Backlog** se não houver nenhum card elegível no **Todo**;
   - card novo que você criar (problema fora do escopo) segue as regras de tipo e prazo do `CLAUDE.md`.
   - Se a `main` não mudou desde o baseline de testes deste ciclo, reaproveite esse baseline no pré-voo.
   - Do 2º card em diante: pule cards que vão mexer nos mesmos arquivos ou na mesma tela de um PR que este
     ciclo já abriu (os dois seriam mergeados no próximo ciclo e entrariam em conflito). Pegue o seguinte.
   - Linear em tempo real: mova o card para **In Progress** assim que escolher (antes de escrever código) e
     para **In Review** com o link do PR logo depois de abrir o PR.
4. Os merges desses PRs ficam para o próximo ciclo (passo 1), feito por outra sessão.
5. Se o `/ship-next` cair em **Parada** (o card já fica com `precisa-decisao`), volte ao item 1 para o próximo.

## 4. Fim
1. Nesta pasta: `git switch main` (deixe a pasta na `main`, sem arquivos seus sobrando).
2. Apague `../.rotina.lock`.
3. Relatório curto (é o que aparece na notificação):
```
🔁 Ciclo HH:MM
🔀 Merge: PR #12 (EF-7) ✅ · PR #13 ❌ testes → precisa-decisao
🧹 Branches apagadas: fix/ef-7-fuso …
🛠 Cards: EF-9 → PR #14 · EF-10 → PR #15 (merge no próximo ciclo)
⏸ Parou de pegar cards: <2 cards | 2 horas de ciclo | fila vazia>
⛔ Paradas: EF-X — <motivo> (se houver)
⏭ Próximo da fila: EF-10 — <título>
```
Ciclo sem nada para fazer: uma linha só (`🔁 Ciclo HH:MM — nada pendente`).

## Nunca
`git push --force` · commit ou push direto na `main` · apagar branch não mergeada · mais de 2 cards por
ciclo · começar card novo depois de 2 horas de ciclo · fazer merge de PR que este mesmo ciclo abriu ·
perguntar "posso seguir?" · deixar a trava para trás · copiar conteúdo de `docs/memory/` para o
repositório público.
