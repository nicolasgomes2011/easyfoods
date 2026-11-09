---
name: cacar-bugs
description: >
  Caçador de bugs da rotina automática do EasyFoods. Roda os testes e as revisões do projeto sobre a main,
  procura problemas reais (quebras, vazamento entre restaurantes, transições de pedido inválidas, migrations
  perigosas) e abre cards no Linear sem duplicar os que já existem. Não conserta nada. Use só quando a
  tarefa agendada ou o usuário digitar /cacar-bugs.
disable-model-invocation: true
allowed-tools:
  - Read
  - Glob
  - Grep
  - Skill
  - Bash(git *)
  - Bash(php *)
  - Bash(composer install*)
  - Bash(npm ci*)
  - Bash(npm run build*)
  - Bash(ls *)
  - Bash(cat *)
  - Bash(grep *)
  - Bash(date*)
  - Bash(find ../.rotina.lock*)
  - Bash(rm ../.rotina.lock)
  - mcp__linear
  - mcp__Linear
---

# cacar-bugs — testes + revisões → cards no Linear

**Não pergunte nada e não altere código.** O resultado desta skill são cards no Linear e um relatório.

## 0. Preparar
1. Trava `../.rotina.lock` (a mesma do `/rotina-executor`): existe e tem menos de 7 horas → encerre com
   `⏸ rotina ainda rodando`. Tem 7 horas ou mais → apague e siga. Crie a trava; apague sempre no fim.
2. `git status --porcelain`: pasta suja → encerre e relate (não mexa em arquivo de ninguém).
3. `git fetch origin` → `git switch main` → `git pull --ff-only`.
4. `composer install --no-interaction` e, se `public/build/manifest.json` não existir, `npm run build`.

## 1. Testes
`php artisan view:clear` → `php artisan test`. Cada teste falhando na `main` é um achado (agrupe as
falhas que têm a mesma causa).

## 2. Revisões
Escopo de hoje:
- **Sempre**: o que mudou na `main` nas últimas 24 horas (`git log --since="24 hours ago" --name-only origin/main`).
- **Mais uma área por dia da semana**, para cobrir o projeto inteiro toda semana. Use a skill do projeto
  indicada (`.claude/skills/<nome>/SKILL.md`) como checklist:
  - segunda: pedidos e máquina de estados → `state-machine-review`
  - terça: isolamento por restaurante → `multi-tenant-review`
  - quarta: banco, migrations e models → `database-review`
  - quinta: carrinho, checkout e pagamento → `payment-and-checkout` + `domain-consistency-review`
  - sexta: cozinha, balcão e telas operacionais → `ux-operational-review` + `debug-livewire`
  - sábado: cardápio e pedido do cliente → `customer-ordering-flow`
  - domingo: tempos de preparo, entrega e ETA → `eta-engine-review`

Só conta como achado o que dá para apontar no código (arquivo e linha) com o impacto explicado.
Estilo, preferência e "poderia ser melhor" não viram card.

## 3. Abrir cards
Para cada achado:
1. **Duplicata**: procure no Linear (time EasyFoods, cards abertos e concluídos nos últimos 30 dias) por
   título, arquivo e sintoma.
   - Já existe aberto → comente nele só se tiver informação nova.
   - Existe concluído e o problema voltou → card novo dizendo "regressão de EF-N".
2. **Card novo**: time EasyFoods, projeto Roadmap EasyFoods, marco pelo assunto (M0 para o que quebra
   fluxo real, dados ou isolamento), label **auto-detectado** + o tipo, status **Todo**. Tipo e prazo
   seguem a seção "Cards no Linear" do `CLAUDE.md`:
   - **Erro** (erro 500, exceção, tela quebrada, perda de dados, segurança, cobrança, vazamento entre
     restaurantes, fluxo principal impossível, teste da `main` falhando) → label **Erro**, prioridade
     **Urgent**, data de entrega = **amanhã**.
   - **Bug** (comportamento errado que não quebra o fluxo: formatação, texto, cálculo exibido, caso de
     borda) → label **Bug**, prioridade **High** se o usuário vê o erro no uso normal, **Medium** se é caso de
     borda, **Low** o resto; data de entrega = **hoje + 3 dias**.
   - Descrição: **Problema** → **Onde** (arquivo:linha) → **Como reproduzir** → **O que fazer** →
     **Critérios de aceite**.
   - O repositório é público e o Linear não: detalhe de vulnerabilidade fica só no card, nunca em commit,
     PR ou issue do GitHub.
3. No máximo **5 cards novos por dia** (os de maior prioridade). O resto vai listado no relatório.

## 3b. Guardião de prazos (todo dia, mesmo sem achados)
Passe por todos os cards abertos do time EasyFoods e aplique as regras do `CLAUDE.md`:
1. **Erro** ou **Bug** no Backlog → mova para **Todo**. Erro sem prioridade Urgent → Urgent.
2. Card em Todo, In Progress ou In Review **sem data de entrega**:
   - Erro → criação + 1 dia; Bug → criação + 3 dias (se a data calculada já passou, use **hoje**);
   - Feature/Improvement → **hoje + 10 dias**.
3. **Feature/Improvement** em Todo, In Progress ou In Review que **avançou** desde a data em que recebeu o
   prazo (mudou de status, ganhou PR ou commit) → prazo = data do avanço + 10 dias.
4. **Épico** (card com sub-cards) fora do Backlog ou com data → volte para Backlog e tire a data.
5. **Linha de prazo na descrição**: todo card com data de entrega começa com
   `> ⏰ **Prazo: DD/MM/AAAA** · <Tipo> (<regra>)`. Se faltar, acrescente no topo; se a data mudou, troque
   só essa linha. Card que voltou para o Backlog perde a linha.
6. Fora a linha de prazo, não mude título, descrição nem prioridade de Feature/Improvement; não feche nem
   cancele nada.
7. Liste no relatório os **prazos estourados** (data de entrega antes de hoje e não Done), do mais
   atrasado para o mais recente, com o motivo provável (PR esperando merge, `precisa-decisao`, nunca pego).

## 4. Fim
`git switch main` → apague `../.rotina.lock` → relatório:
```
🐞 Caçador de bugs — DD/MM
🧪 Testes: N passando · F falhando na main
🔎 Área do dia: <área>
🆕 Cards: EF-41 (Erro, prazo DD/MM) — … · EF-42 (Bug, prazo DD/MM) — …
⏰ Prazos estourados: EF-7 (Bug, 2 dias, nunca pego) · …
🗂 Prazos ajustados: …
♻️ Já existiam: EF-7 (comentado) …
📝 Achados que ficaram para amanhã: …
```

## Nunca
Editar código, commitar ou abrir PR · criar card duplicado · criar card de estilo ou preferência ·
colocar detalhe de vulnerabilidade no GitHub · deixar a trava para trás.
