# task.md

## Contexto do projeto

Este projeto será um software web para gestão de pedidos de comida, desenvolvido com **Laravel + Livewire**, com foco principal em permitir que **restaurantes tenham melhor controle operacional dos pedidos** e, ao mesmo tempo, oferecer uma **área para os clientes realizarem seus pedidos** de forma simples, rápida e intuitiva.

O sistema deve ser pensado como um produto profissional, com base sólida, arquitetura organizada, domínio bem modelado, regras de negócio claras e código preparado para crescer.

A prioridade inicial **não é construir um marketplace completo estilo iFood**, mas sim um sistema robusto que possa atender um restaurante ou evoluir para um modelo SaaS multi-restaurante no futuro.

---

## Objetivo geral

Construir a base completa do sistema desde o zero, com:

* arquitetura Laravel profissional
* interface administrativa para o restaurante
* interface de pedidos para o cliente
* fluxo operacional de pedidos bem definido
* modelagem de dados consistente
* separação clara entre domínio, interface e regras de negócio
* base pronta para escalar futuramente

---

## Stack obrigatória

O projeto deve ser construído com:

* **Laravel**
* **Livewire**
* **Blade**
* **Alpine.js** apenas como suporte leve de interatividade
* **Tailwind CSS** para a interface
* banco de dados relacional com migrations bem estruturadas
* testes automatizados para fluxos críticos

Evitar dependências excessivas no início.

---

## Direção arquitetural obrigatória

O projeto deve seguir princípios de arquitetura limpa e organização por domínio.

### Regras principais

* Não concentrar regra de negócio em componentes Livewire
* Não criar componentes gigantes com múltiplas responsabilidades
* Não colocar lógica complexa diretamente em Blade
* Não usar strings soltas para status importantes
* Não misturar fluxo de pedido com fluxo de pagamento
* Não misturar regra operacional com detalhe visual
* Não deixar cálculo de preço dependente apenas do frontend
* Não criar estrutura improvisada orientada apenas por telas

### Estrutura esperada

Organizar o projeto com foco em domínio de negócio, não apenas por páginas.

O sistema deve ser dividido em contextos como:

* Restaurants / Stores
* Customers
* Catalog
* Cart
* Orders
* Checkout
* Payments
* Delivery
* Notifications
* Reports
* Admin / Settings
* Auth / Permissions

Deve haver uso consistente de:

* Models
* Actions / Services
* Enums
* Policies
* Form Requests ou validações organizadas
* Jobs
* Events / Listeners
* Livewire Components com responsabilidade clara

---

## Escopo do produto

O sistema possui dois grandes lados:

### 1. Área do cliente

A área do cliente é onde o pedido nasce.

Ela deve permitir que o cliente:

* visualize o cardápio do restaurante
* navegue por categorias
* veja detalhes dos produtos
* adicione itens ao carrinho
* personalize itens com adicionais, observações e variações
* informe endereço ou escolha retirada
* escolha forma de pagamento
* finalize o pedido
* acompanhe o status do pedido
* visualize pedidos anteriores

### 2. Área do restaurante

A área do restaurante é o centro operacional.

Ela deve permitir que o restaurante:

* gerencie cardápio, categorias e produtos
* controle disponibilidade de itens
* visualize pedidos em tempo real
* confirme pedidos
* altere status dos pedidos
* acompanhe fila de produção
* organize retirada e entrega
* configure horários, taxas e áreas de entrega
* cadastre equipe e permissões
* visualize relatórios operacionais e gerenciais
* altere configurações da loja sem depender de desenvolvedor

---

## Visão do MVP

O Claude deve construir primeiro um **MVP profissional e funcional**, sem tentar implementar tudo de uma vez.

### O MVP deve conter obrigatoriamente

#### Área pública / cliente

* página inicial da loja
* listagem de categorias
* listagem de produtos
* detalhes do produto
* seleção de adicionais e observações
* carrinho
* checkout
* escolha entre entrega e retirada
* cálculo básico de entrega
* seleção de forma de pagamento
* criação de pedido
* tela de acompanhamento de pedido

#### Área administrativa / restaurante

* login
* dashboard inicial
* gerenciamento de categorias
* gerenciamento de produtos
* gerenciamento de adicionais
* gerenciamento de pedidos
* alteração manual de status do pedido
* configurações básicas da loja
* configuração de horários de funcionamento
* configuração de taxa/área de entrega
* gerenciamento básico de usuários internos

#### Núcleo de negócio

* fluxo de status do pedido
* fluxo de status do pagamento
* cálculo de subtotal
* cálculo de adicionais
* cálculo de taxa de entrega
* cálculo de desconto futuro preparado, mesmo que ainda simples
* persistência de snapshot de preço no pedido
* histórico de status do pedido

#### Base técnica

* migrations organizadas
* seeders iniciais
* factories
* testes dos fluxos críticos
* autorização por papéis/permissões
* logs adequados para ações importantes

---

## O que NÃO deve entrar agora

Para evitar overengineering, deixar fora da primeira fase:

* marketplace completo com vários restaurantes públicos competindo entre si
* app mobile nativo
* sistema complexo de comissão
* repasse financeiro avançado
* loyalty program complexo
* múltiplos gateways simultâneos logo no início
* BI avançado
* multi-idioma completo
* integrações grandes antes do núcleo estar estável
* microserviços
* arquitetura excessivamente complexa sem necessidade real

O projeto deve nascer simples, sólido e extensível.

---

## Como o sistema deve ser pensado

O software deve resolver problemas reais do restaurante.

### Problemas operacionais que o sistema deve atacar

* demora ou confusão no recebimento de pedidos
* erros de comunicação entre cliente e restaurante
* dificuldade para controlar status do pedido
* falta de organização na cozinha ou produção
* dificuldade para atualizar cardápio
* dependência manual para acompanhar operação
* baixa visibilidade sobre pedidos, horários de pico e ticket médio

### Como o sistema deve agilizar os pedidos

* permitir pedido digital direto pelo cliente
* reduzir atrito no checkout
* exibir pedidos em painel operacional claro
* permitir atualização rápida do status
* manter histórico e rastreabilidade
* evitar retrabalho e erros de anotação
* organizar visualmente a fila de pedidos
* facilitar reconfiguração do cardápio e disponibilidade

---

## Fluxo operacional do pedido

O fluxo de pedidos é um dos pontos mais importantes do projeto.

Ele deve ser modelado de forma explícita, consistente e auditável.

### Status de pedido sugeridos

Criar enums e regras formais para status, sem strings soltas.

Fluxo inicial sugerido:

* `draft`
* `pending_confirmation`
* `confirmed`
* `in_preparation`
* `ready_for_pickup`
* `out_for_delivery`
* `delivered`
* `completed`
* `canceled`

O Claude pode adaptar esse fluxo, mas deve justificar mudanças e manter coerência com retirada, entrega e operação do restaurante.

### Regras importantes

* um pedido não deve mudar de status sem validação
* mudanças de status devem ser auditáveis
* status do pedido e status do pagamento devem ser independentes
* um pedido pode exigir confirmação antes de entrar em preparo
* pedidos para retirada e entrega podem ter etapas diferentes
* o sistema deve permitir rastrear o histórico de mudanças

---

## Fluxo de pagamento

O sistema deve tratar pagamento como um domínio separado.

### Status de pagamento sugeridos

* `pending`
* `authorized`
* `paid`
* `failed`
* `refunded`
* `partially_refunded`
* `canceled`

### Regras obrigatórias

* não confiar apenas nos totais do frontend
* recalcular totais no backend
* armazenar snapshot de preço no pedido
* tratar pagamento e pedido como fluxos separados
* permitir expansão futura para integração com gateway
* deixar estrutura preparada para webhook ou conciliação futura

---

## Modelagem de domínio esperada

O Claude deve começar modelando corretamente o domínio do sistema.

### Entidades mínimas esperadas

#### Restaurante e operação

* Restaurant
* Branch ou unidade, se fizer sentido já deixar preparado
* OperatingHour
* DeliveryZone
* StoreSetting

#### Usuários e acesso

* User
* Role / Permission
* Customer
* CustomerAddress

#### Catálogo

* Category
* Product
* ProductVariant
* ProductAddon ou AddonGroup
* ProductAddonOption
* ProductAvailability

#### Carrinho e pedido

* Cart
* CartItem
* Order
* OrderItem
* OrderStatusHistory

#### Pagamento

* Payment
* PaymentAttempt ou estrutura equivalente se necessário

#### Comunicação e operação

* Notification
* talvez OrderTimeline / ActivityLog se fizer sentido

### Observações de modelagem

* preços dos itens do pedido devem ser gravados no momento da compra
* adicionais devem registrar valores no pedido
* mudanças futuras de produto não podem quebrar pedidos antigos
* endereços do cliente devem ser tratados corretamente
* dados operacionais importantes precisam de índice
* a modelagem deve considerar futura evolução para multi-restaurante

---

## Organização de código esperada

O Claude deve propor e implementar uma organização clara de diretórios e responsabilidades.

### Exemplo de direção esperada

* `app/Models`
* `app/Enums`
* `app/Actions`
* `app/Services`
* `app/Policies`
* `app/Jobs`
* `app/Events`
* `app/Listeners`
* `app/Livewire`
* `app/Support` se realmente necessário
* `database/migrations`
* `database/seeders`
* `database/factories`
* `tests/Feature`
* `tests/Unit`

### Regras de implementação

* Actions/Services devem conter regras relevantes de negócio
* Models devem focar em persistência, relações e escopos úteis
* Enums devem centralizar status e valores fixos
* Policies devem controlar acesso administrativo
* Jobs devem processar tarefas assíncronas
* Events/Listeners devem desacoplar efeitos secundários
* Livewire deve ser usado com responsabilidade clara por tela/contexto

---

## Interface do cliente

A área do cliente deve ser simples, objetiva e com foco em conversão.

### Páginas e experiências mínimas

* home da loja
* cardápio
* produtos por categoria
* detalhes do produto
* carrinho
* checkout
* confirmação do pedido
* acompanhamento do pedido
* histórico do cliente, se possível no MVP

### Diretrizes de UX

* mobile first
* poucas etapas
* valores claros
* taxa de entrega visível
* ações de adicionar/remover fáceis
* personalização de itens intuitiva
* feedback visual claro
* evitar formulários longos e desnecessários

---

## Interface administrativa do restaurante

A área interna deve priorizar operação.

### Módulos mínimos

* dashboard
* pedidos
* categorias
* produtos
* adicionais
* horários de funcionamento
* áreas/taxas de entrega
* usuários internos
* configurações da loja

### Diretrizes

* rapidez operacional
* clareza dos status
* pouco clique para ações comuns
* formulários organizados
* filtros úteis na listagem de pedidos
* foco em uso diário real

---

## Regras de autorização

O sistema deve possuir autorização desde o início.

### Papéis mínimos sugeridos

* admin
* manager
* attendant
* kitchen
* delivery
* customer

O Claude pode ajustar, mas deve implementar uma estratégia clara de acesso.

### Exemplos de separação

* admin: acesso total
* manager: gestão operacional ampla
* attendant: gestão de pedidos e atendimento
* kitchen: visualização operacional da produção
* delivery: visualização de pedidos em rota, se existir
* customer: apenas sua própria área

---

## Requisitos de tempo real e notificações

O sistema deve ser projetado para comportamento responsivo.

### Cenários importantes

* novo pedido chegando no painel do restaurante
* alteração de status do pedido
* atualização para o cliente acompanhar o pedido
* futura possibilidade de alertas sonoros, WhatsApp, e-mail ou browser notification

### Direção inicial

No MVP, pode ser usado polling ou estratégia simples, mas a arquitetura deve ficar pronta para evolução futura.

### Regras

* notificações não devem nascer acopladas ao componente visual
* preferir eventos, jobs e listeners quando necessário
* evitar duplicidade de notificações
* garantir rastreabilidade em ações importantes

---

## Relatórios mínimos esperados futuramente, com base já preparada

Mesmo que nem todos os relatórios sejam feitos agora, o sistema deve nascer preparado para isso.

Exemplos:

* quantidade de pedidos por período
* ticket médio
* produtos mais vendidos
* pedidos cancelados
* tempo médio de preparo
* horários de pico
* desempenho operacional

Deixar a estrutura preparada para essas leituras.

---

## Estratégia de implementação

O projeto deve ser construído por etapas, com entregas pequenas e consistentes.

### Ordem sugerida de execução

#### Fase 1 — Fundação do projeto

* instalar e configurar Laravel + Livewire + Tailwind
* preparar autenticação
* definir estrutura base
* definir enums e padrões
* definir autorização
* criar layout base
* preparar seeders e dados iniciais

#### Fase 2 — Modelagem do domínio

* criar migrations principais
* criar models e relações
* criar factories
* criar seeders de base
* garantir integridade e índices

#### Fase 3 — Catálogo

* categorias
* produtos
* adicionais
* disponibilidade
* telas administrativas correspondentes

#### Fase 4 — Cliente e carrinho

* navegação do cardápio
* detalhes do produto
* carrinho
* personalização
* fluxo de checkout inicial

#### Fase 5 — Pedidos

* criação do pedido
* itens do pedido
* snapshot de valores
* status do pedido
* histórico
* painel operacional do restaurante

#### Fase 6 — Pagamentos

* modelagem de pagamento
* lógica interna de checkout
* preparação para integração futura
* status e consistência

#### Fase 7 — Operação e experiência

* atualização de status
* acompanhamento do cliente
* refinamento do painel de pedidos
* configuração da loja

#### Fase 8 — Testes e refinamento

* testes de fluxos críticos
* revisão de arquitetura
* revisão de queries
* validação de permissões
* hardening do projeto

---

## Qualidade de código exigida

O Claude deve manter padrão profissional.

### Regras obrigatórias

* nomes claros
* métodos pequenos e objetivos
* classes com responsabilidade clara
* validações explícitas
* queries eficientes
* evitar N+1
* evitar duplicação de regra
* não criar métodos gigantes como `save()` fazendo tudo
* status com enum
* erros tratados de forma clara
* código legível acima de código “esperto”

---

## Testes obrigatórios

O projeto deve possuir testes para os fluxos críticos.

### Prioridades de teste

* criação de pedido
* cálculo de total
* cálculo com adicionais
* cálculo de taxa de entrega
* separação entre status do pedido e status do pagamento
* mudança válida de status
* bloqueio de mudança inválida de status
* autorização de áreas administrativas
* CRUD crítico do catálogo

---

## Entregáveis esperados do Claude

O Claude não deve apenas “gerar código”. Ele deve estruturar o projeto.

### Para cada fase, espera-se:

* implementação da feature
* explicação objetiva do que foi criado
* arquivos criados e alterados
* justificativa técnica curta quando necessário
* observações de arquitetura
* riscos ou pontos pendentes
* próximos passos sugeridos

---

## Comportamento esperado durante a execução

Ao trabalhar neste projeto, o Claude deve agir como um engenheiro senior com foco em Laravel + Livewire.

### Deve:

* pensar antes de sair criando arquivos
* respeitar a arquitetura definida
* propor melhorias quando encontrar incoerências
* quebrar trabalho em etapas pequenas
* manter consistência de nomenclatura
* preservar clareza do domínio
* justificar decisões quando houver tradeoff relevante

### Não deve:

* improvisar arquitetura fraca
* espalhar regra de negócio em Livewire
* gerar sistema “mockado” sem domínio real
* misturar implementação com gambiarra
* acoplar demais UI e regra
* criar estrutura confusa só para entregar rápido

---

## Primeira entrega que deve ser feita agora

Comece pela **fundação do projeto**, entregando:

1. estrutura base do projeto
2. proposta final de organização de diretórios
3. definição inicial das entidades principais
4. enums iniciais de status
5. estratégia de autenticação e autorização
6. migrations iniciais prioritárias
7. seeders iniciais
8. planejamento técnico da ordem de implementação

Antes de avançar para telas complexas, garantir que a base do domínio esteja correta.

---

## Resultado esperado

Ao final da primeira fase, o projeto deve estar com base sólida o suficiente para começar a implementar catálogo, carrinho, checkout e fluxo de pedidos sem retrabalho estrutural.

Este projeto deve nascer como um sistema profissional, organizado, extensível e coerente com boas práticas de Laravel e Livewire.
