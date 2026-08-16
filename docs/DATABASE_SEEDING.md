# Seeders — como recuperar o banco sem perder dados

## O problema que isso resolve

Antes, reconstruir o ambiente de desenvolvimento significava recriar restaurante,
categorias, produtos, mesas e clientes na mão pelo painel. Qualquer
`migrate:fresh` apagava tudo de novo.

Agora os seeders reconstroem um ambiente completo e realista em um comando —
e podem ser rodados **em cima de um banco que já tem dados**, sem duplicar nem
sobrescrever nada.

## Regra número 1: `migrate` não apaga nada

```bash
php artisan migrate          # aplica só as migrations pendentes — NÃO apaga dados
php artisan migrate:status   # mostra o que já rodou e o que está pendente
```

Quem apaga o banco é:

| Comando | O que faz |
| --- | --- |
| `php artisan migrate` | aplica migrations pendentes — **seguro**, preserva dados |
| `php artisan migrate:fresh` | **dropa todas as tabelas** e roda tudo de novo |
| `php artisan migrate:refresh` | roda todos os `down()` e depois todos os `up()` |
| `php artisan migrate:rollback` | desfaz o último batch de migrations |

Ou seja: para o dia a dia use `php artisan migrate`. Só use `migrate:fresh`
quando quiser mesmo zerar.

## Comandos do dia a dia

```bash
# Popular / completar o banco atual sem apagar nada (pode rodar quantas vezes quiser)
php artisan db:seed

# Zerar de propósito e reconstruir tudo do zero
php artisan migrate:fresh --seed

# Rodar um seeder isolado
php artisan db:seed --class=CatalogSeeder
```

## Os seeders

Ordem de execução definida em `database/seeders/DatabaseSeeder.php`. A ordem
importa: catálogo, mesas e pedidos dependem do restaurante existir.

| Seeder | O que cria | Chave de idempotência |
| --- | --- | --- |
| `UserSeeder` | admin + 1 usuário por papel (gerente, atendente, cozinha, entregador) | `email` |
| `RestaurantSeeder` | restaurante, 7 horários de funcionamento, 4 zonas de entrega, formas de pagamento | `slug`; filhos por `[restaurant_id, weekday]`, `[restaurant_id, name]`, `[restaurant_id, key]` |
| `CatalogSeeder` | 5 categorias, 14 produtos, 17 variantes, 7 grupos de adicionais, 25 opções | categoria: `[restaurant_id, slug]` · produto: `[restaurant_id, name]` |
| `DiningTableSeeder` | 12 mesas (10 mesas + 2 banquetas de balcão) | `[restaurant_id, number]` |
| `CustomerSeeder` | 3 clientes com endereço padrão | `email` |
| `DemoOrderSeeder` | 8 pedidos cobrindo todo o ciclo de vida, com itens, histórico e pagamento | `[restaurant_id, number]` |

`CustomerSeeder` e `DemoOrderSeeder` são dados de demonstração e são
**pulados automaticamente em produção** (`app()->environment('production')`).

## Credenciais criadas

Senha `12345` para todas as contas.

**Painel administrativo** (`/login`):

| E-mail | Papel |
| --- | --- |
| `gomes.nicolas.2011@gmail.com` | Administrador |
| `gerente@easyfoods.test` | Gerente |
| `atendente@easyfoods.test` | Atendente |
| `cozinha@easyfoods.test` | Cozinha |
| `entregador@easyfoods.test` | Entregador |

**Clientes da loja**: `maria@cliente.test`, `joao@cliente.test`,
`luiza@cliente.test`.

Restaurante: **Testando teste** (slug `testando-teste`) — o slug foi mantido
igual ao do banco atual para que as URLs da loja continuem funcionando depois
de um reset.

## Idempotência — por que rodar de novo é seguro

Todo seeder usa `firstOrCreate` / `firstOrNew` sobre uma **chave natural**
(nunca ID). Consequências práticas:

- rodar `db:seed` duas vezes não duplica nada (verificado: 3 execuções seguidas
  produzem exatamente as mesmas contagens);
- registros que **você editou na mão** no painel não são sobrescritos — o
  seeder encontra a linha e não toca nela;
- `CatalogSeeder` só cria variantes e adicionais de um produto **na primeira
  criação dele**, justamente para não recriar o que você ajustou depois;
- mesas já existentes mantêm o `uuid` — então QR codes já impressos continuam
  válidos.

## Invariantes respeitadas pelo `DemoOrderSeeder`

Os pedidos de demonstração seguem as mesmas regras de
`App\Actions\Orders\PlaceOrder`:

- **snapshot congelado**: `order_items` guarda `product_name`, `variant_name` e
  `unit_price` copiados no momento do pedido — nunca relidos do produto;
- **totais coerentes**: `subtotal` = soma dos itens, `total` = `subtotal` +
  `delivery_fee`;
- **histórico válido**: `order_status_histories` percorre apenas transições
  permitidas por `OrderStatus::allowedTransitions()`, e o último registro do
  histórico é sempre igual ao `status` atual do pedido;
- **marcos temporais**: `confirmed_at`, `ready_at`, `delivered_at`,
  `completed_at` e `canceled_at` são preenchidos conforme os status visitados;
- **pagamento independente do pedido**: dinheiro só é liquidado na entrega, Pix
  e cartão já vêm pagos, pedido aguardando confirmação fica `pending`, pedido
  cancelado nunca fica pago;
- **retirada e mesa não passam por entrega**: o caminho pula
  `out_for_delivery` / `delivered` e fecha direto de `ready_for_pickup`;
- **mesa com sessão aberta** é marcada como `occupied`.

Distribuição dos 8 pedidos: `completed`, `canceled`, `delivered`,
`out_for_delivery`, `ready_for_pickup` (retirada), `in_preparation` (mesa 7),
`confirmed`, `pending_confirmation`.

Os números dos pedidos são sequenciais (`00001`–`00008`), então o
`max(CAST(number AS INTEGER)) + 1` de `PlaceOrder` continua a contagem em
`00009` normalmente.
