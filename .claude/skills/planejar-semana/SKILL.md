---
name: planejar-semana
description: >
  Planejamento semanal da rotina automática do EasyFoods. Mede quantos cards a rotina realmente entrega por
  dia, ordena todas as pendências do Linear e do roadmap, quebra cards grandes demais, distribui os cards
  da semana pelos dias (data de entrega no Linear) e publica o documento "Plano da semana". Use quando a
  tarefa agendada ou o usuário digitar /planejar-semana ou pedir "planeja a semana do EasyFoods".
argument-hint: "[--dry-run]"
---

# planejar-semana — capacidade → fila → dias da semana → documento

**Não pergunte nada.** Decida pelas regras abaixo e registre as decisões no documento.
O executor (`/rotina-executor`, de hora em hora) pega os cards pela data de entrega que você define aqui.

## Argumentos (`$ARGUMENTS`)
- vazio → planeja e grava as datas no Linear.
- `--dry-run` → monta o plano e o documento, mas **não** altera nada no Linear.

## 0. Fontes
- **Linear**: time **EasyFoods** (EF), projeto **Roadmap EasyFoods**, marcos **M0 → M1 → M2 → M3**.
  Status: Backlog, Todo, In Progress, In Review, Done, Canceled, Duplicate.
  Labels: Bug, Improvement, Feature, `precisa-decisao`, `auto-detectado`.
- **Repositório**: `docs/progress/ROADMAP.md` (fase ativa, itens `[ ]` e `[~]`), `ADJUSTMENTS.md`,
  `TESTING_ROADMAP.md`.
- **Memória privada** (`docs/memory/` ou `easyfoods-brain/memory/`), se existir: só contexto (bugs
  conhecidos, riscos, decisões). Nunca copie esse conteúdo para o repositório público, PRs ou issues.

## 1. Período
A rotina roda **todos os dias**. O plano vai de hoje até o domingo desta semana (rodando na segunda,
são 7 dias).

## 2. Capacidade (cota diária)
1. Conte os cards do time EF que foram para **Done** nos últimos 7 dias (`completedAt`).
2. Cota diária = esse número ÷ 7, arredondado, limitado entre **2** e **10** (10 é o máximo de ciclos de
   card por dia do executor: das 08h às 17h).
3. Primeira semana da rotina (não existe documento "Plano da semana" anterior) → cota **10**, a meta
   do Nicolas. Nas semanas seguintes vale a medição.
4. Se na semana anterior a rotina entregou menos da metade do planejado, use a média real mesmo que fique
   bem abaixo da meta, e explique no documento.

## 3. Fila
1. Todos os cards abertos (Backlog, Todo, In Progress), **menos**: os com `precisa-decisao`, os
   bloqueados (algum `blockedBy` não concluído) e os em **In Review** (já têm PR; o executor faz o merge).
2. **Roadmap sem card**: item `[ ]` ou `[~]` da fase ativa do `ROADMAP.md` que não tem card correspondente
   → crie o card (Todo, label Feature ou Improvement, marco pela fase). No máximo **10** por semana.
3. **Tamanho** (pela descrição e pelo código que o card toca):
   - **P**: um ajuste localizado, 1–3 arquivos;
   - **M**: alguns arquivos e testes novos, sem mudar o modelo de dados;
   - **G**: migration, fluxo novo ou várias telas.
   - **Grande demais para um ciclo** (vários fluxos, ~15+ arquivos, ou critérios de aceite que são na
     verdade várias entregas): quebre em 2–4 sub-cards P/M com critérios de aceite próprios (card original
     vira pai). Agende os sub-cards, não o pai.
4. **Ordem**: prioridade (Urgent → High → Medium → Low) → marco (M0 → M3) → Bug antes de Improvement
   antes de Feature → tamanho (P → M → G) → menor número EF. Card que bloqueia outros sobe para antes deles.

## 4. Distribuir pelos dias
1. Vagas = cota × dias do período.
2. Primeiro os **atrasados** (data de entrega já passou e não estão Done), no dia de hoje.
3. Depois a fila, dia a dia: cada card recebe `dueDate` = o dia. No máximo **2 cards G** por dia.
4. Cards agendados que estão em **Backlog** → **Todo**.
5. Cards que não couberem ficam **sem data**. Se tinham data dentro desta semana (de um plano antigo),
   tire a data.
6. Não mude prioridade, título ou descrição de card existente (exceto o card pai que você quebrou:
   só um comentário apontando os sub-cards).

## 5. Documento "Plano da semana DD/MM a DD/MM"
Crie com a ferramenta de documentos disponível nesta sessão (Claude Docs; se não houver, um Google Doc no
Drive do Nicolas; se nenhuma, só no relatório final). Conteúdo:
- **Resumo**: pendências abertas, cota diária e como foi calculada, quantos cards cabem na semana e
  quantos ficam para depois.
- **Uma seção por dia**: `EF-N — título — prioridade — tamanho — link`.
- **Precisa de você**: cards com `precisa-decisao` (motivo do último comentário) e PRs em revisão manual.
- **Ficou para depois**: os 10 primeiros da fila que não couberam.
- **Mudanças feitas pelo plano**: cards criados do roadmap, cards quebrados, datas removidas.
- **Divergências**: item `[x]` no roadmap com card aberto; card concluído com item ainda `[ ]`.

## 6. Relatório final
```
🗓 Plano da semana DD/MM–DD/MM — <link do documento>
📊 Cota: N/dia (<como calculou>) · agendados: X · ficaram para depois: Y
🆕 Cards criados: … · ✂️ quebrados: …
👉 Precisa de você: …
```

## Nunca
Implementar código · fazer merge · apagar, cancelar ou arquivar cards · mudar prioridade definida pelo
Nicolas · agendar card com `precisa-decisao` · copiar memória privada para o repositório público.
