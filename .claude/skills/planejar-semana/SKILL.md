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
2. Cota diária = esse número ÷ 7, arredondado, limitado entre **2** e **8** (8 é o máximo do executor por
   noite: até 4 cards no ciclo das 00h e até 4 no das 03h).
3. Primeira semana da rotina (não existe documento "Plano da semana" anterior) → cota **8**, perto da meta
   de 10 do Nicolas. Nas semanas seguintes vale a medição.
4. Se na semana anterior a rotina entregou menos da metade do planejado, veja o motivo nos comentários dos
   PRs e dos cards antes de baixar a cota:
   - travas da própria rotina (merge bloqueado por permissão, PC desligado ou dormindo, ciclos pulados) →
     **não** reduza: use a cota máxima (8) e explique no documento;
   - cards travando de verdade (testes falhando, `precisa-decisao`, cards grandes demais) → use a média real,
     mesmo que fique bem abaixo da meta, e explique no documento.

## 3. Fila
1. Todos os cards abertos (Backlog, Todo, In Progress), **menos**: os com `precisa-decisao`, os
   bloqueados (algum `blockedBy` não concluído) e os em **In Review** (já têm PR; o executor faz o merge).
2. **Roadmap sem card**: item `[ ]` ou `[~]` da fase ativa do `ROADMAP.md` que não tem card correspondente
   → crie o card (Backlog, label Feature ou Improvement, marco pela fase). No máximo **10** por semana.
3. **Tamanho** (pela descrição e pelo código que o card toca):
   - **P**: um ajuste localizado, 1–3 arquivos;
   - **M**: alguns arquivos e testes novos, sem mudar o modelo de dados;
   - **G**: migration, fluxo novo ou várias telas.
   - **Grande demais para um ciclo** (vários fluxos, ~15+ arquivos, ou critérios de aceite que são na
     verdade várias entregas): quebre em 2–4 sub-cards P/M com critérios de aceite próprios (card original
     vira épico). Agende os sub-cards; o épico fica no Backlog, sem data.
4. **Ordem**: Erro → Bug → Feature/Improvement; dentro de cada tipo: prioridade (Urgent → Low) → marco
   (M0 → M3) → tamanho (P → M → G) → menor número EF. Card que bloqueia outros sobe para antes deles.
5. **Marcos**: Feature/Improvement só entram na semana se forem do marco mais baixo que ainda tem cards
   abertos ou do seguinte (hoje: M0 e M1). Os de marcos depois desses ficam no Backlog. Erro e Bug entram
   sempre, de qualquer marco.

## 4. Montar a semana
A **data de entrega** do card é o **prazo** pelas regras do `CLAUDE.md` (Erro 1 dia, Bug 3 dias,
Feature/Improvement 10 dias sem avanço), não o dia do plano. O dia do plano vai só no documento.
1. Vagas = cota × dias do período.
2. Primeiro todos os **Erros** e **Bugs** abertos (mesmo que passem das vagas), depois a fila do passo 3.
3. Distribua pelos dias **no documento** (no máximo **2 cards G** por dia), por ordem de prazo e depois da fila.
4. Cards da semana que estão no **Backlog** → **Todo**. Feature/Improvement que entra no Todo agora recebe
   prazo = **hoje + 10 dias**; Erro/Bug sem prazo recebe o da regra (se já passou, **hoje**).
5. **Todo = só a semana**: Feature/Improvement no Todo que **não** entrou no plano desta semana e não
   avançou nos últimos 10 dias → volta para o **Backlog** e perde a data. Erro e Bug nunca voltam.
6. Épicos ficam no Backlog, sem data.
6. Não mude prioridade, título ou descrição de card existente (exceto o card pai que você quebrou:
   só um comentário apontando os sub-cards).

## 5. Documento "Plano da semana DD/MM a DD/MM"
Crie com a ferramenta de documentos disponível nesta sessão (Claude Docs; se não houver, um Google Doc no
Drive do Nicolas; se nenhuma, só no relatório final). Conteúdo:
- **Resumo**: pendências abertas, cota diária e como foi calculada, quantos cards cabem na semana e
  quantos ficam para depois.
- **Uma seção por dia**: `EF-N — título — tipo — prioridade — tamanho — prazo — link`.
- **Prazos estourados**: cards com data de entrega antes de hoje, e o motivo.
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
