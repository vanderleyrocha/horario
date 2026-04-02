# Constraints Customizadas

## Visão geral

O módulo de constraints customizadas permite cadastrar regras explícitas de horário por contexto de `Horario`, sem acoplar a semântica do domínio ao núcleo genérico do AG.

A feature cobre:

- persistência das constraints por `horario_id`
- modelagem forte em `Modules/Horarios/Domain/Constraints`
- CRUD e ativação/desativação pela UI Livewire
- carregamento das constraints ativas no fluxo de geração
- impacto formal no fitness hard/soft
- diagnóstico prévio de viabilidade antes da população inicial
- pontos de extensão para repair futuro

## Arquitetura

### Contexto e agregado

- Toda constraint pertence a um `Horario`.
- O domínio de constraints fica isolado em `app/Modules/Horarios/Domain/Constraints`.
- O AG recebe apenas snapshots solver-safe em `CustomConstraintData`, sem Eloquent e sem dependência direta do domínio rico.

### Camadas principais

#### Persistência

- tabela `schedule_constraints`
- model `App\Models\ScheduleConstraint`
- mapper `ScheduleConstraintMapper`
- repositório `ScheduleConstraintRepository` com implementação `EloquentScheduleConstraintRepository`

#### Domínio

- enums: `ConstraintType`, `ConstraintLevel`, `SyncOccurrenceMode`, `SyncMatchMode`, `TimePlacementMode`
- entidades: `ScheduleConstraint`, `SyncSameTimeslotConstraint`, `MutualExclusionConstraint`, `TimePlacementConstraint`
- value objects: `ConstraintTargetGroup`, `TimePlacementWindow`
- validators por tipo e factory central `ScheduleConstraintFactory`
- humanizer `ScheduleConstraintHumanizer`
- evaluators e pipeline de avaliação
- analyzer de viabilidade pré-AG

#### Aplicação

- `CreateScheduleConstraintAction`
- `UpdateScheduleConstraintAction`
- `DeleteScheduleConstraintAction`
- `ToggleScheduleConstraintStatusAction`
- `ListScheduleConstraintsAction`
- `LoadActiveScheduleConstraintsAction`
- `ConstraintSolverPayloadMapper`

#### UI

- aba `constraints` em `Manage`
- componente `GerenciarConstraints`
- view `gerenciar-constraints.blade.php`

## Tipos suportados

### 1. SYNC_SAME_TIMESLOT

Objetivo: sincronizar grupos de aulas no mesmo dia e tempo.

Payload base:

```json
{
  "left_group": {"lesson_ids": [101, 102]},
  "right_group": {"lesson_ids": [201, 202]},
  "occurrence_mode": "ALL",
  "match_mode": "ALL_TO_ALL"
}
```

Semântica inicial:

- `ALL`: todas as ocorrências devem respeitar o sincronismo
- `AT_LEAST_ONE`: basta ao menos uma coincidência válida
- `ALL_TO_ALL`: compara grupos completos
- `FIRST_WITH_FIRST`: já previsto para expansão

### 2. MUTUAL_EXCLUSION

Objetivo: impedir coincidência entre grupos de aulas.

Payload base:

```json
{
  "left_group": {"lesson_ids": [301, 302]},
  "right_group": {"lesson_ids": [401, 402]}
}
```

Semântica inicial:

- qualquer sobreposição de timeslot entre os grupos gera violação

### 3. TIME_PLACEMENT

Objetivo: obrigar, preferir ou proibir dias/períodos para um grupo de aulas.

Payload base:

```json
{
  "target_group": {"lesson_ids": [501, 502]},
  "mode": "FORBIDDEN",
  "allowed_days": [5],
  "allowed_periods": [5, 6]
}
```

Semântica inicial:

- `REQUIRED`: a alocação deve cair dentro da janela
- `PREFERRED`: cair fora da janela penaliza
- `FORBIDDEN`: cair dentro da janela proibida penaliza

## Fluxo da UI ao solver

### 1. Cadastro e edição

- O usuário gerencia as regras pela aba `constraints` do gerenciamento do horário.
- O formulário muda dinamicamente conforme o tipo.
- O payload salvo é reidratado na edição.
- O humanizer gera a descrição amigável exibida na própria UI.

### 2. Persistência e leitura

- As actions de aplicação validam a entrada, acionam a factory e persistem via repositório.
- O payload JSON fica salvo na tabela `schedule_constraints`.
- Constraints inativas continuam persistidas, mas não entram no solver.

### 3. Carregamento para o solver

- `RunGeneticAlgorithm` chama `LoadActiveScheduleConstraintsAction`.
- `ConstraintSolverPayloadMapper` converte o domínio para `CustomConstraintData`.
- `ScheduleDataBuilder` injeta os snapshots em `ScheduleData`.
- O evento `solver.custom_constraints.loaded` registra a telemetria de carregamento.

## Integração com fitness

### Pipeline

- `ConstraintEvaluationPipeline` percorre as constraints ativas de `ScheduleData`.
- Cada tipo é delegado ao evaluator apropriado:
  - `SyncSameTimeslotConstraintEvaluator`
  - `MutualExclusionConstraintEvaluator`
  - `TimePlacementConstraintEvaluator`

### Resultado

- `ConstraintViolationResult` representa cada conflito/violação encontrada.
- `ConstraintEvaluationSummary` agrega:
  - `hardPenalty()`
  - `softPenalty()`
  - agrupamento por constraint
  - conflitos por nível

### Regras de fitness

- `CustomConstraintHardRule` aplica o total HARD em `hardPenalty`.
- `CustomConstraintSoftRule` aplica o total SOFT em `softPenalty` respeitando `weight`.

## Diagnóstico prévio de viabilidade

Antes da população inicial, o `ScheduleProblem` executa um diagnóstico preventivo.

O `ConstraintFeasibilityAnalyzer` detecta, entre outros:

- referências a aulas inexistentes no snapshot
- `occurrence_mode` ou `match_mode` inválidos
- sincronismos sem slots compartilhados
- sincronismos com folga nula ou flexibilidade mínima
- exclusões mútuas sem escape
- janelas temporais fora da configuração
- `REQUIRED` impossível
- `FORBIDDEN` inviável
- preferência inalcançável
- pressão excessiva no primeiro tempo

O resultado fica encapsulado em `ConstraintFeasibilityReport` e entra no diagnóstico com:

- `constraint_infeasibilities`
- `constraint_warnings`
- `constraint_risk_contribution`

Se houver inviabilidade estrutural, o AG aborta antes da construção cara da população inicial.

## Repair e extensão futura

Nesta fase não existe repair especializado por tipo de constraint, mas a arquitetura já foi preparada.

O `GreedyRepairOperator` agora aceita extensões via `RepairHeuristicExtension`, que podem:

- acrescentar repair targets
- filtrar slots candidatos para relocação
- aplicar penalidade extra no ranking de candidatos
- reportar violações remanescentes para targets customizados

O módulo Horários já registra `CustomConstraintRepairExtension` como placeholder para heurísticas futuras de:

- sincronização
- posicionamento temporal
- exclusão temporal

## Como adicionar um novo tipo de constraint

1. Adicionar o novo caso em `ConstraintType`.
2. Criar validator específico.
3. Criar entidade específica no domínio.
4. Registrar o tipo na `ScheduleConstraintFactory`.
5. Criar evaluator específico e registrá-lo no `ConstraintEvaluationPipeline`.
6. Atualizar o humanizer.
7. Se necessário, ampliar o analyzer de viabilidade.
8. Se necessário, implementar heurística futura em `RepairHeuristicExtension`.
9. Cobrir o novo tipo com testes unitários e de integração.

## Limitações atuais

- O solver ainda não executa repair especializado por constraint; há apenas pontos de extensão preparados.
- O analyzer de viabilidade trabalha sobre o snapshot disponível no `ScheduleData`, não sobre modelagem global adicional.
- A documentação de progresso das fases está consolidada separadamente em `docs/custom-constraints-progress.md`.

## Testes relevantes

- `tests/Unit/Modules/Horarios/ConstraintEnumsAndValidatorsTest.php`
- `tests/Unit/Modules/Horarios/ScheduleConstraintFactoryTest.php`
- `tests/Unit/Modules/Horarios/ScheduleConstraintHumanizerTest.php`
- `tests/Unit/Modules/Horarios/CustomConstraintFitnessPipelineTest.php`
- `tests/Unit/Modules/Horarios/ConstraintFeasibilityAnalyzerTest.php`
- `tests/Unit/Modules/AG/RunGeneticAlgorithmConstraintLoadingTest.php`
- `tests/Unit/Modules/AG/GreedyRepairOperatorTest.php`
- `tests/Feature/Modules/Horarios/ScheduleConstraintRepositoryTest.php`
- `tests/Feature/Modules/Horarios/ScheduleConstraintActionsTest.php`
- `tests/Feature/Modules/Horarios/Livewire/GerenciarConstraintsTest.php`
