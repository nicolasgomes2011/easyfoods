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
1. Trava `../.rotina.lock` (a mesma do `/rotina-executor`): existe e tem menos de 3 horas → encerre com
   `⏸ rotina ainda rodando`. Tem 3 horas ou mais → apague e siga. Crie a trava; apague sempre no fim.
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
   fluxo real, dados ou isolamento), labels **Bug** + **auto-detectado**, status **Todo**.
   - Prioridade: **Urgent** = vazamento entre restaurantes, perda ou corrupção de dados, falha de segurança,
     fluxo principal de pedido quebrado; **High** = tela ou fluxo quebrado com contorno, teste da `main`
     falhando; **Medium** = comportamento errado em caso de borda; **Low** = o resto.
   - **Urgent** recebe data de entrega = amanhã (o executor pega primeiro).
   - Descrição: **Problema** → **Onde** (arquivo:linha) → **Como reproduzir** → **O que fazer** →
     **Critérios de aceite**.
   - O repositório é público e o Linear não: detalhe de vulnerabilidade fica só no card, nunca em commit,
     PR ou issue do GitHub.
3. No máximo **5 cards novos por noite** (os de maior prioridade). O resto vai listado no relatório.

## 4. Fim
`git switch main` → apague `../.rotina.lock` → relatório:
```
🐞 Caçador de bugs — DD/MM
🧪 Testes: N passando · F falhando na main
🔎 Área do dia: <área>
🆕 Cards: EF-41 (Urgent) — … · EF-42 (High) — …
♻️ Já existiam: EF-7 (comentado) …
📝 Achados que ficaram para amanhã: …
```

## Nunca
Editar código, commitar ou abrir PR · criar card duplicado · criar card de estilo ou preferência ·
colocar detalhe de vulnerabilidade no GitHub · deixar a trava para trás.
