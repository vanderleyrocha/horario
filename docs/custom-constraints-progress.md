# Progresso — Constraints Customizadas

## Status consolidado

- Fase 9: DONE
- Fase 10: DONE
- Fase 11: DONE
- Fase 12: DONE
- Fase 13: DONE
- Fase 14: DONE
- Fase 15: DONE

## Evidências por fase

### Fase 9 — Integração Solver

- Constraints ativas são carregadas por `LoadActiveScheduleConstraintsAction`.
- O mapper `ConstraintSolverPayloadMapper` converte o domínio para snapshots solver-safe.
- O `RunGeneticAlgorithm` injeta as constraints em `ScheduleData` e registra `solver.custom_constraints.loaded`.
- Validação: `tests/Unit/Modules/AG/RunGeneticAlgorithmConstraintLoadingTest.php`.

### Fase 10 — Fitness

- O pipeline `ConstraintEvaluationPipeline` e os evaluators iniciais estão ativos.
- `CustomConstraintHardRule` e `CustomConstraintSoftRule` já influenciam `hardPenalty` e `softPenalty`.
- Os detalhes diagnósticos podem ser agrupados por constraint.
- Validação: `tests/Unit/Modules/Horarios/CustomConstraintFitnessPipelineTest.php`.

### Fase 11 — Avaliação por tipo

- `SYNC_SAME_TIMESLOT` cobre `ALL` e `AT_LEAST_ONE`.
- `MUTUAL_EXCLUSION` avalia sobreposição por timeslot.
- `TIME_PLACEMENT` cobre `REQUIRED`, `PREFERRED` e `FORBIDDEN`.
- Validação: `tests/Unit/Modules/Horarios/CustomConstraintFitnessPipelineTest.php`.

### Fase 12 — Viabilidade

- `ConstraintFeasibilityAnalyzer` e `ConstraintFeasibilityReport` detectam problemas antes da execução.
- O diagnóstico preventivo do `ScheduleProblem` já incorpora `constraint_infeasibilities`, `constraint_warnings` e `constraint_risk_contribution`.
- Validação: `tests/Unit/Modules/Horarios/ConstraintFeasibilityAnalyzerTest.php`.

### Fase 13 — Repair (Preparação)

- Foi criado o contrato `RepairHeuristicExtension` para permitir extensão controlada do repair.
- `GreedyRepairOperator` agora aceita hooks para ampliar repair targets, filtrar slots candidatos e ajustar ranking de candidatos sem alterar o comportamento padrão.
- O módulo Horários já registra o placeholder `CustomConstraintRepairExtension`, deixando a arquitetura pronta para heurísticas futuras de sincronização, posicionamento temporal e exclusão temporal.
- Validação: `tests/Unit/Modules/AG/GreedyRepairOperatorTest.php`.

### Fase 14 — Testes

- Há testes explícitos para enums, validators, factory, humanizer, evaluators, feasibility analyzer e integração com solver/fitness.
- CRUD, persistência de payload e carregamento de constraints ativas já estão cobertos por testes feature e unitários focados.
- Validação: `tests/Unit/Modules/Horarios/ConstraintEnumsAndValidatorsTest.php`, `tests/Unit/Modules/Horarios/ScheduleConstraintFactoryTest.php`, `tests/Unit/Modules/Horarios/ScheduleConstraintHumanizerTest.php`, `tests/Unit/Modules/Horarios/CustomConstraintFitnessPipelineTest.php`, `tests/Unit/Modules/Horarios/ConstraintFeasibilityAnalyzerTest.php`, `tests/Unit/Modules/AG/RunGeneticAlgorithmConstraintLoadingTest.php`, `tests/Feature/Modules/Horarios/ScheduleConstraintRepositoryTest.php`, `tests/Feature/Modules/Horarios/ScheduleConstraintActionsTest.php` e `tests/Feature/Modules/Horarios/Livewire/GerenciarConstraintsTest.php`.

### Fase 15 — Documentação

- A documentação final ponta a ponta foi consolidada em `docs/custom-constraints.md`.
- O documento cobre arquitetura, tipos suportados, payloads, fluxo UI → solver, integração com fitness, viabilidade, repair futuro e expansão.

## Pendências imediatas

- Fase 16 ainda não foi formalmente consolidada em um checklist final de revisão arquitetural.
