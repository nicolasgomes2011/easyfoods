# EasyFoods — instruções para o Claude

Plataforma de operação + pedidos para restaurante. Laravel 12 · Livewire 4 + Volt · Alpine · Tailwind 4 · SQLite (dev) / MySQL (prod).
Visão geral em `README.md`; escopo em `docs/scope/`; status em `docs/progress/`.

## Comece por aqui
1. Rode a skill **`bootstrap-project`** no início da sessão (lê `docs/memory/system-overview.md`).
2. Use **`load-memory`** para puxar só o domínio da tarefa; **`save-memory`** ao fim de cada decisão/bug/feature.
3. Features: **`implement-feature`** em modo PLAN-AND-APPROVE — apresente o plano e espere aprovação antes de editar.
4. Bugs: `bug-investigation`. Status/prioridade: `roadmap-review`. Lista completa em `.claude/skills/`.
5. **Autopilot: `/ship-next`** — o próprio comando é a aprovação. Faz o fluxo completo (card do Linear → código → testes → PR → merge) **sem perguntar**, e substitui o PLAN-AND-APPROVE do item 3 enquanto roda. Regras em `.claude/skills/ship-next/SKILL.md`.
6. **Rotina automática**: `/planejar-semana` (segunda, monta a fila da semana no Todo), `/rotina-executor` (a cada 3 horas, em qualquer horário: merge dos PRs prontos via `/merge-prs` + até 2 cards via `/ship-next --sem-merge`) e `/cacar-bugs` (toda manhã às 06:30, antes do planejador e do executor: testes + revisões → cards `auto-detectado`). Rodam na pasta exclusiva do piloto, nunca na pasta de trabalho do Nicolas.

## Cards no Linear (time EasyFoods): tipos, prazos e colunas
- **Tipo** (label, um por card):
  - **Erro**: erro 500, exceção, tela quebrada, perda de dados, falha de segurança ou de cobrança, fluxo principal impossível → prioridade **Urgent**, prazo = **1 dia** a partir da criação.
  - **Bug**: comportamento errado que não quebra o fluxo (ex.: salvei 2.000,00 e apareceu 2000.00) → prazo = **3 dias** a partir da criação.
  - **Feature** e **Improvement** → prazo = **10 dias** a partir da entrada no Todo ou do último avanço (mudança de status, PR, commit). O prazo estoura se ficar 10 dias sem nada ser feito.
- O prazo vai no campo **data de entrega** (dueDate) do card **e** na primeira linha da descrição, no formato `> ⏰ **Prazo: DD/MM/AAAA** · <Tipo> (<regra>)` — ex.: `> ⏰ **Prazo: 01/11/2026** · Bug (3 dias)`. Quem mudar a data atualiza a linha junto. Erro e Bug nunca ficam no Backlog nem sem prazo.
- **Colunas**: Backlog = ideias e próximas semanas, sem prazo · Todo = fila desta semana · In Progress = sendo feito agora · In Review = PR aberto, esperando o merge automático · Done = na `main`.
- **Épico** = card com sub-cards: fica no Backlog, sem prazo, e nunca é implementado direto — o trabalho são os sub-cards.
- Todo card tem marco (M0 → M3). Problema achado fora do escopo do card atual vira card novo seguindo estas regras.

## Memória do projeto (privada)
- `docs/memory/` é uma junction para o repo **privado** `easyfoods-brain` (ver o README de lá).
- Se `docs/memory/` não existir: avise o usuário que o brain não está ligado (`easyfoods-brain\scripts\setup.ps1`) e use `docs/scope/` + `docs/progress/` como fallback.
- Bugs, vulnerabilidades, riscos e débitos atuais vão **só** para `docs/memory/` — nunca para skills, README, docs/ ou commits deste repo (ele é público).
- Nunca `git add -f docs/memory`. Um hook pre-commit bloqueia isso.

## Invariantes
- Status de pedido muda só via `App\Actions\Orders\TransitionOrderStatus` (enum `OrderStatus` + histórico append-only + milestone).
- Itens de pedido guardam snapshot de nome/preço; totais sempre calculados no servidor.
- `status` e `payment_status` são independentes.
- Toda query/tabela nova escopada por `restaurant_id`.
- Rotas em `routes/default_routes_web.php`, `admin.php`, `customer.php`, `auth.php` (não existe `routes/web.php`).
- Migrations reversíveis; sem SQL específico de MySQL.

## Definition of Done
`php artisan view:clear` → testar a URL real (200, sem `Undefined`/exceção) → feature test + teste do componente → `php artisan test`.

## Convenções
UI e mensagens em PT-BR; código, nomes e commits em inglês. Uma branch por feature; nunca commitar direto na `main`.
