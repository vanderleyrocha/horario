# Especificacao — Constraints Customizadas

## Status da Fase 1

- Fase 1: DONE
- Dependencia da Fase 0: OK
- Implementacao de codigo iniciada: NAO
- Validacao da fase: OK

## Objetivo

Definir a modelagem de dominio para constraints customizadas do horario com foco em extensibilidade, tipagem forte e integracao limpa com o solver.

## Escopo desta fase

- Tipos iniciais
- Enums principais
- Payload minimo por tipo
- Campos obrigatorios
- Validacoes semanticas
- Distincao hard versus soft
- Regra de weight
- Estrutura recomendada para DTOs e entidades

## Principios de modelagem

1. Toda constraint pertence a um Horario.
2. O dominio nao deve depender de Eloquent.
3. O tipo da constraint deve ser explicito por enum.
4. O payload deve ser validado semanticamente por tipo.
5. HARD e SOFT precisam ter comportamento formalmente distinto no fitness.
6. A expansao futura deve ocorrer por novos tipos e novos evaluators, nao por ifs espalhados.

## Estrutura de alto nivel recomendada

Namespace alvo:

- app/Modules/Horarios/Domain/Constraints

Subestruturas recomendadas:

- Enums
- DTOs
- Entities
- ValueObjects
- Validators
- Factories
- Evaluators
- Results

## Enums recomendados

### ConstraintType

Valores iniciais:

- SYNC_SAME_TIMESLOT
- MUTUAL_EXCLUSION
- TIME_PLACEMENT

Responsabilidade:

- Definir o tipo principal da regra.

### ConstraintLevel

Valores:

- HARD
- SOFT

Responsabilidade:

- Controlar o destino da penalidade no fitness.

Regra:

- HARD impacta hardPenalty.
- SOFT impacta softPenalty e usa weight.

### TimePlacementMode

Valores iniciais:

- REQUIRED
- PREFERRED
- FORBIDDEN

Responsabilidade:

- Definir como dias e periodos permitidos devem ser interpretados.

### SyncOccurrenceMode

Valores iniciais:

- ALL
- AT_LEAST_ONE

Responsabilidade:

- Para sincronizacao, define se todas as ocorrencias semanais devem sincronizar ou se basta ao menos uma coincidencia valida.

### SyncMatchMode

Valores iniciais:

- ALL_TO_ALL
- FIRST_WITH_FIRST

Responsabilidade:

- Definir o criterio de casamento entre grupos sincronizados.

Observacao:

- Na implementacao inicial, ALL_TO_ALL deve ser o caminho principal.
- FIRST_WITH_FIRST pode existir como enum desde ja para expansao futura, mesmo que a primeira entrega suporte apenas ALL_TO_ALL.

## Campos gerais comuns a qualquer constraint

Esses campos devem existir fora do payload tipado, como metadata padrao da constraint.

- id
- horarioId
- name
- description
- type
- level
- weight
- isActive
- payload
- createdBy
- updatedBy
- createdAt
- updatedAt

## Regras globais para level e weight

### HARD

- weight pode ser omitido e normalizado internamente para 1.
- o evaluator deve somar a violacao em hardPenalty.
- violacao hard deve ser considerada estruturalmente relevante para diagnostico.

### SOFT

- weight e obrigatorio.
- faixa recomendada inicial: 1 a 100.
- o evaluator deve converter weight em impacto sobre softPenalty.

### Validacao semantica global

- HARD com weight informado pode ser aceito, mas nao deve alterar a semantica de hardPenalty alem do contrato definido pela implementacao.
- SOFT sem weight deve ser rejeitado.
- weight menor que 1 deve ser rejeitado.

## Tipo 1 — SYNC_SAME_TIMESLOT

### Intencao

Garantir que grupos de aulas ocorram no mesmo dia e tempo, conforme a estrategia de sincronizacao escolhida.

### Casos de uso tipicos

- Duas turmas diferentes tendo determinada disciplina simultaneamente.
- Aulas correlatas que precisam acontecer no mesmo bloco horario.

### Payload minimo recomendado

- left_group
- right_group
- occurrence_mode
- match_mode

### Estrutura recomendada do payload

```json
{
  "left_group": {
    "lesson_ids": [101, 102]
  },
  "right_group": {
    "lesson_ids": [205, 206]
  },
  "occurrence_mode": "ALL",
  "match_mode": "ALL_TO_ALL"
}
```

### Campos obrigatorios

- left_group.lesson_ids
- right_group.lesson_ids
- occurrence_mode
- match_mode

### Validacoes semanticas

- cada grupo deve conter pelo menos um lesson_id
- os grupos nao podem ser vazios
- os grupos nao podem ter interseccao
- todos os lesson_ids devem pertencer ao mesmo horario
- lesson_ids inexistentes devem ser rejeitados
- occurrence_mode deve ser um valor valido do enum
- match_mode deve ser um valor valido do enum

### Regras funcionais iniciais

- ALL + ALL_TO_ALL:
  toda ocorrencia alocada de cada lado deve respeitar sincronizacao esperada com o grupo oposto.
- AT_LEAST_ONE + ALL_TO_ALL:
  basta existir ao menos uma coincidencia valida entre os grupos.

### HARD versus SOFT

- HARD: quebra de sincronizacao soma em hardPenalty
- SOFT: quebra de sincronizacao soma em softPenalty ponderado por weight

## Tipo 2 — MUTUAL_EXCLUSION

### Intencao

Impedir que dois grupos de aulas coexistam no mesmo timeslot.

### Casos de uso tipicos

- Aulas que nao podem acontecer simultaneamente por decisao pedagogica.
- Exclusoes entre grupos de interesse compartilhado.

### Payload minimo recomendado

- left_group
- right_group

### Estrutura recomendada do payload

```json
{
  "left_group": {
    "lesson_ids": [301, 302]
  },
  "right_group": {
    "lesson_ids": [401, 402]
  }
}
```

### Campos obrigatorios

- left_group.lesson_ids
- right_group.lesson_ids

### Validacoes semanticas

- cada grupo deve conter pelo menos um lesson_id
- os grupos nao podem ser vazios
- os grupos nao podem ter interseccao
- todos os lesson_ids devem pertencer ao mesmo horario
- lesson_ids inexistentes devem ser rejeitados

### Regra funcional inicial

- se qualquer aula do grupo esquerdo e qualquer aula do grupo direito ocuparem o mesmo dia e tempo, ha violacao.

### HARD versus SOFT

- HARD: coexistencia proibida impacta hardPenalty
- SOFT: coexistencia indesejada impacta softPenalty por weight

## Tipo 3 — TIME_PLACEMENT

### Intencao

Controlar a colocacao temporal de um conjunto de aulas em dias e periodos especificos.

### Casos de uso tipicos

- Aula obrigatoriamente no primeiro tempo.
- Aula preferencialmente em determinados dias.
- Aula proibida em certos periodos.

### Payload minimo recomendado

- target_group
- mode
- allowed_days
- allowed_periods

### Estrutura recomendada do payload

```json
{
  "target_group": {
    "lesson_ids": [501, 502]
  },
  "mode": "FORBIDDEN",
  "allowed_days": [1, 2, 3],
  "allowed_periods": [1, 2]
}
```

### Observacao semantica importante

- para REQUIRED e PREFERRED, allowed_days e allowed_periods representam o conjunto desejado
- para FORBIDDEN, allowed_days e allowed_periods devem ser interpretados como conjunto proibido ou renomeados na implementacao para blocked_days e blocked_periods

Recomendacao para a implementacao:

- usar um VO ou DTO normalizado que nao dependa do nome cru do payload
- o payload persistido pode continuar simples, mas a normalizacao deve gerar uma semantica clara para o evaluator

### Campos obrigatorios

- target_group.lesson_ids
- mode
- pelo menos um entre allowed_days e allowed_periods

### Validacoes semanticas

- target_group deve conter ao menos um lesson_id
- todos os lesson_ids devem pertencer ao mesmo horario
- mode deve ser valido
- allowed_days, quando informado, deve usar apenas dias validos no contexto do horario
- allowed_periods, quando informado, deve usar apenas periodos validos no contexto do horario
- listas vazias simultaneas devem ser rejeitadas

### Regras funcionais iniciais

- REQUIRED:
  cada ocorrencia da aula deve cair dentro do conjunto exigido
- PREFERRED:
  ocorrencias fora do conjunto desejado geram penalidade soft ou hard conforme level
- FORBIDDEN:
  ocorrencias dentro do conjunto proibido geram violacao

### HARD versus SOFT

- HARD: violacao do posicionamento temporal vai para hardPenalty
- SOFT: violacao vai para softPenalty multiplicado por weight

## Estrutura recomendada para DTOs

### CreateScheduleConstraintInput

Responsabilidade:

- representar entrada validada para criacao

Campos recomendados:

- horarioId
- name
- description
- type
- level
- weight
- isActive
- payload
- actorId

### UpdateScheduleConstraintInput

Responsabilidade:

- representar entrada validada para atualizacao

Campos recomendados:

- id
- horarioId
- name
- description
- level
- weight
- isActive
- payload
- actorId

### ScheduleConstraintData

Responsabilidade:

- DTO neutro para trafego entre repositorio, actions, factory e humanizer

Campos recomendados:

- id
- horarioId
- name
- description
- type
- level
- weight
- isActive
- payload
- createdBy
- updatedBy
- createdAt
- updatedAt

## Estrutura recomendada para entidades de dominio

### ScheduleConstraint

Entidade base com metadados comuns.

### SyncSameTimeslotConstraint

Entidade especifica para o tipo SYNC_SAME_TIMESLOT.

### MutualExclusionConstraint

Entidade especifica para o tipo MUTUAL_EXCLUSION.

### TimePlacementConstraint

Entidade especifica para o tipo TIME_PLACEMENT.

## Estrutura recomendada para grupos alvo

Como padrao, os payloads acima usam lesson_ids porque essa e a unidade solver mais objetiva hoje.

Recomendacao de expansao futura:

- criar TargetGroup como Value Object
- suportar no futuro seletores mais expressivos, como disciplina_ids, class_ids ou professor_ids
- a normalizacao desses seletores deve ocorrer fora do solver, convertendo para lesson_ids antes da avaliacao

## Regras de validacao transversais

Todas as constraints devem validar:

- type conhecido
- level conhecido
- payload consistente com o type
- horarioId obrigatorio
- integridade de pertencimento ao horario
- coerencia entre level e weight
- nome nao vazio

## Estrategia recomendada de expansao futura

Para adicionar um novo tipo depois:

1. incluir novo valor em ConstraintType
2. criar DTO ou VO de payload especifico
3. criar validator especifico
4. criar entidade especifica
5. criar evaluator especifico
6. registrar na factory
7. registrar no humanizer

## Exemplos resumidos de payloads JSON

### SYNC_SAME_TIMESLOT

```json
{
  "left_group": {"lesson_ids": [10, 11]},
  "right_group": {"lesson_ids": [20, 21]},
  "occurrence_mode": "AT_LEAST_ONE",
  "match_mode": "ALL_TO_ALL"
}
```

### MUTUAL_EXCLUSION

```json
{
  "left_group": {"lesson_ids": [30]},
  "right_group": {"lesson_ids": [40, 41]}
}
```

### TIME_PLACEMENT

```json
{
  "target_group": {"lesson_ids": [50]},
  "mode": "REQUIRED",
  "allowed_days": [1, 3, 5],
  "allowed_periods": [1, 2]
}
```

## Checklist de aceite da Fase 1

- Documento de especificacao criado: OK
- Tipos iniciais definidos: OK
- Payload minimo documentado: OK
- Validacoes semanticas definidas: OK
- Hard versus soft definido: OK
- Regras de weight definidas: OK
- Expansao futura suportada sem ifs espalhados: OK

## Proxima fase recomendada

Fase 2 — Persistencia.

Decisao a preservar:

- A persistencia continua vinculada a horario_id.
