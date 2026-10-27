---
name: rotina-executor
description: >
  Um ciclo da rotina automática do EasyFoods, feito para rodar de hora em hora pela tarefa agendada local
  do Claude Code Desktop. Faz o merge dos PRs prontos (/merge-prs), apaga as branches já mergeadas e
  implementa o próximo card do dia até abrir o PR (/ship-next --sem-merge) — sem perguntar nada.
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

# rotina-executor — um ciclo da rotina (merge → limpeza → próximo card)

**Digitar `/rotina-executor` é a aprovação prévia do usuário para o ciclo inteiro.** Não pergunte nada.
Cada ciclo é uma sessão nova, e isso é de propósito: quem faz o merge de um PR (passo 1) nunca é a sessão
que escreveu o código dele (passo 3 do ciclo anterior).

Pasta esperada: esta pasta (`easyfoods`) com `../easyfoods-brain` e `../easyfoods-pr-check` ao lado.
No PC do Nicolas: `C:\Users\User\EasyFoods-autopilot\easyfoods`.

## Argumentos (`$ARGUMENTS`)
- vazio → ciclo completo.
- `--so-merge` → só os passos 0, 1, 2 e 4 (nenhum card novo).

## 0. Janela e trava
1. Veja a hora local (America/Sao_Paulo).
   - Antes das 08:00 ou depois das 18:59 → encerre na hora com `⏸ fora da janela da rotina (HH:MM)`.
     (Protege contra a execução de recuperação quando o PC acorda de madrugada.)
   - Das 18:00 às 18:59 → ciclo só de merge (como `--so-merge`).
2. Trava em `../.rotina.lock` (na pasta acima do projeto):
   - Existe e foi modificada há **menos de 3 horas** → encerre com `⏸ ciclo anterior ainda rodando`.
     (Git Bash: `find ../.rotina.lock -mmin -180`; PowerShell: `(Get-Item ..\.rotina.lock).LastWriteTime`.)
   - Existe e tem **3 horas ou mais** → apague (ciclo anterior travou), siga e cite no relatório.
   - Crie a trava (`date > ../.rotina.lock`).
   - **Sempre** apague a trava no passo 4, inclusive depois de Parada ou erro.
3. Pré-voo do `/ship-next` (itens 1 a 5 do passo 0 dele): arquivos sujos anotados, `main` atualizada,
   `gh` logado, Linear respondendo, manifest do Vite. Falhou algum → relatório com o comando para o Nicolas.

## 1. Merge dos PRs prontos
Leia e siga `.claude/skills/merge-prs/SKILL.md` inteiro, **sem argumentos** (todos os PRs abertos contra a
`main`, menos drafts e os com `precisa-decisao`). Ela testa cada PR junto com a `main` atual na pasta
`../easyfoods-pr-check`, faz o merge dos aprovados, apaga a branch remota de cada um e atualiza o card.
- Nenhum PR aberto → siga.

## 2. Limpeza de branches
1. `git fetch origin --prune`
2. Branches remotas já contidas na `main`: `git branch -r --merged origin/main`, menos `origin/main` e
   `origin/HEAD` → `git push origin --delete <nome>` para cada uma.
3. **Nunca** apague branch não mergeada nem branch com PR aberto. Branch antiga sem PR e não mergeada:
   só liste no relatório ("parada há N dias, sem PR").

## 3. Próximo card
Pule este passo com `--so-merge` ou depois das 18:00.
1. `git switch main` → `git pull --ff-only`.
2. Leia e siga `.claude/skills/ship-next/SKILL.md` com os argumentos `1 --sem-merge`: **um card**, até o PR
   aberto e o card em **In Review**. A escolha do card já segue o plano da semana: Urgent → data de entrega
   até hoje (atrasados primeiro) → próximas datas → resto da fila.
3. O merge desse PR fica para o próximo ciclo (passo 1), feito por outra sessão.
4. Se o `/ship-next` cair em **Parada**, siga para o passo 4 (o card já fica com `precisa-decisao`).

## 4. Fim
1. Nesta pasta: `git switch main` (deixe a pasta na `main`, sem arquivos seus sobrando).
2. Apague `../.rotina.lock`.
3. Relatório curto (é o que aparece na notificação):
```
🔁 Ciclo HH:MM
🔀 Merge: PR #12 (EF-7) ✅ · PR #13 ❌ testes → precisa-decisao
🧹 Branches apagadas: fix/ef-7-fuso …
🛠 Card: EF-9 — <título> → PR #14 aberto (merge no próximo ciclo)
⛔ Paradas: EF-X — <motivo> (se houver)
⏭ Próximo da fila: EF-10 — <título>
```
Ciclo sem nada para fazer: uma linha só (`🔁 Ciclo HH:MM — nada pendente`).

## Nunca
`git push --force` · commit ou push direto na `main` · apagar branch não mergeada · mais de 1 card por
ciclo · fazer merge do PR que este mesmo ciclo abriu · perguntar "posso seguir?" · deixar a trava para trás ·
copiar conteúdo de `docs/memory/` para o repositório público.
