# EasyFoods — instruções para o Claude

Plataforma de operação + pedidos para restaurante. Laravel 12 · Livewire 4 + Volt · Alpine · Tailwind 4 · SQLite (dev) / MySQL (prod).
Visão geral em `README.md`; escopo em `docs/scope/`; status em `docs/progress/`.

## Comece por aqui
1. Rode a skill **`bootstrap-project`** no início da sessão (lê `docs/memory/system-overview.md`).
2. Use **`load-memory`** para puxar só o domínio da tarefa; **`save-memory`** ao fim de cada decisão/bug/feature.
3. Features: **`implement-feature`** em modo PLAN-AND-APPROVE — apresente o plano e espere aprovação antes de editar.
4. Bugs: `bug-investigation`. Status/prioridade: `roadmap-review`. Lista completa em `.claude/skills/`.

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
