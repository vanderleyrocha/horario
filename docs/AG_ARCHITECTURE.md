# Arquitetura do Algoritmo Genético - Projeto Horário

## Resumo Executivo

O Projeto Horário implementa um solver híbrido para timetabling escolar baseado em:

- algoritmo genético multioperador com ilhas diferenciadas por perfil
- construção inicial guiada por GRASP com alpha adaptativo, portfólio e salvage
- intensificação por ALNS/LNS com seleção adaptativa de operadores
- modelo de ilhas com migração periódica e perfis Conservative/Balanced/Exploratory
- avaliação lexicográfica hard/soft com suporte a delta fitness
- nogoods persistentes entre execuções (memória de longo prazo)
- lookahead de custo crítico na alocação GRASP
- meta de qualidade da população inicial (batch quality, diversidade e dispersão)
- telemetria operacional detalhada em três camadas (cache, banco, logs)
- monitoramento Livewire em tempo real

Na prática, o sistema não é apenas um AG clássico. Ele combina construção gulosa randomizada, repair iterativo, hiper-heurísticas, análise de landscape, portfólio adaptativo de estratégias de construção e observabilidade operacional suficiente para diagnosticar gargalos na população inicial e na evolução.

## Escopo Real Implementado

Hoje a arquitetura cobre, de ponta a ponta:

- acionamento pela UI de execução
- criação de registro de execução
- despacho assíncrono via fila
- orquestração do solver na camada de aplicação
- montagem do problema de horários com ScheduleData
- evolução em ilhas com migração periódica (intervalo 5) e perfis diferenciados
- repair durante a construção inicial e durante a evolução
- ALNS para intensificação local com seleção epsilon-greedy de operadores
- análise de landscape e resposta adaptativa
- persistência transacional da melhor solução com validação final de integridade
- cache, banco e logs para progresso e métricas
- integração com constraints customizadas no solver, fitness, viabilidade e repair especializado
- nogoods persistentes entre execuções (cache com TTL de 7 dias)
- batch quality report da população inicial com veredito automatizado
- perfis de ilha com parâmetros GRASP e de mutação diferenciados

## Arquitetura em Camadas

### Camada de UI e Operação

Os principais componentes operacionais ficam em app/Modules/AG/UI/Livewire:

- ExecutionCenter: inicia execuções, permite cancelamento e exibe histórico recente
- ExecutionDashboard: central de acompanhamento da execução
- ExecutionStatusPanel: monitora heartbeat, fases, possíveis travamentos e recomendações operacionais
- ExecutionMetricsStream: stream de métricas ao longo da execução
- DiagnosticoInviabilidade: apoio visual para análise de inviabilidade

Essa camada não executa o solver diretamente. Ela cria uma ScheduleExecution, despacha um job e acompanha o estado por cache, banco e logs.

### Camada de Job e Orquestração

O pipeline operacional passa por:

1. ExecutionCenter::runSolver()
2. GerarHorarioJob
3. GenerateScheduleAction
4. RunGeneticAlgorithm
5. PersistBestSolutionService

O GerarHorarioJob é a peça que conecta observabilidade e solver. Ele:

- sobe limite de memória e tempo
- inicia ExecutionMetricsRecorder
- cria CacheProgressReporter
- cria CacheAndDbProgressReporter
- usa GATelemetryLogger
- executa a action de geração
- persiste o resultado
- fecha execução como finished, cancelled ou failed
- publica `status_health` (`health`, `dominant_phase`, `recommendations`) no contexto operacional final

### Health Check Operacional por Heartbeat/Stage

O ciclo atual incorpora um health check aditivo no status final da execução para reduzir diagnóstico manual em logs.

Componentes:

- `app/Modules/AG/Infrastructure/Health/ExecutionHealthBuilder.php`
- `app/Modules/AG/Infrastructure/Health/DTO/ExecutionHealthDTO.php`
- integração no `app/Jobs/GerarHorarioJob.php`

Regras de classificação:

- `critical`: `quality_gate_fail_fast > 0` ou `quality_gate_rejections >= attempt_limit`
- `warn`: `repair_passes >= 3` ou `alns_activations == 0` com `generations_recorded >= 30`
- `ok`: demais cenários

Este campo é aditivo e não quebra consumidores antigos do payload de status.

### Camada de Aplicação do AG

O ponto principal é RunGeneticAlgorithm.

Ele é responsável por:

- carregar GeneticAlgorithmConfigDTO
- construir ScheduleData
- carregar constraints ativas e convertê-las para snapshots solver-safe
- montar regras hard e soft do fitness
- instanciar o ScheduleProblem
- montar engine de ilhas
- compor crossover, mutação, replacement, ALNS, hyper-heuristics e landscape engine
- executar repair final na melhor solução

## Fluxo de Execução Ponta a Ponta

### 1. Disparo e preparação

- a UI cria um ScheduleExecution
- o job recebe Horario e executionId
- a geração é iniciada de forma assíncrona

### 2. Construção do problema

- ScheduleDataBuilder transforma o contexto do horário em value objects imutáveis
- RunGeneticAlgorithm injeta nesse snapshot as constraints customizadas ativas
- o ScheduleProblem passa a operar em cima de ScheduleData

### 3. Geração da população inicial

- o ScheduleProblem roda diagnóstico preventivo
- tenta reaproveitar sementes aceitas ou históricas
- constrói indivíduos por GRASP com limite adaptativo de tentativas
- aplica quality gate
- usa repair quando necessário

### 4. Evolução

Cada ilha executa um GeneticAlgorithmEngine com loop de evolução contendo:

- seleção
- crossover
- mutação
- repair
- avaliação
- ALNS em frequência configurada
- landscape analysis em checkpoints
- replacement com niching
- atualização de hiper-heurísticas
- verificação de término

### 5. Persistência final

Ao final:

- a melhor solução passa por repair final
- PersistBestSolutionService valida conflitos estruturais antes de gravar
- as alocações antigas são removidas
- a nova melhor solução é persistida em transação

## Representação da Solução

### Cromossomo

Cromossomo é a representação da solução completa.

Ele mantém:

- coleção de genes
- assinatura estrutural para cache
- índices auxiliares de professor, turma, período e blocos
- estruturas derivadas para conflitos e janelas

Esses índices são essenciais para reduzir custo de avaliação, repair e detecção de conflito.

### Gene

Gene representa uma aula alocada em um dia e período, com duração e metadados suficientes para validações, swap, relocation e rebuild local.

## Construção da População Inicial

### O que está implementado hoje

A construção inicial está concentrada em ScheduleProblem e é um dos trechos mais sofisticados do solver.

Elementos implementados:

- fila de alocação orientada por dificuldade com live_tightness e avg_slot_contention
- diagnóstico preventivo antes da construção
- RCL com alpha adaptativo por contexto (portfólio de perfis aprendidos via epsilon-greedy)
- lookahead crítico no score de slot: opções=0 gera penalidade de +15, opções=1 penalidade de +2.5
- fallback controlado para alocações forçadas
- quality gate para rejeitar sementes ruins cedo
- fail-fast para interromper tentativas estruturalmente degradadas
- reaproveitamento de semente aceita da própria execução
- reaproveitamento de semente histórica de execuções anteriores
- salvage: genes livres de conflito de tentativas rejeitadas são preservados e reusados como base
- repair com orçamento operacional
- nogoods persistentes entre execuções (carregados de cache com TTL de 7 dias)
- batch quality report publicado após inicialização da população
- telemetria detalhada da construção

### Alpha Adaptativo e Portfólio de Perfis (Sprint 2 + Melhoria 2)

O alpha do GRASP foi evoluído de um sorteio simples para um sistema em dois níveis:

**Nível 1 — pressão adaptativa:** o alpha é ajustado com base na pressão de seleção corrente. Quando a tentativa está travando, o alpha se afasta do valor de pressão para romper bloqueio.

**Nível 2 — portfólio epsilon-greedy:** o sistema mantém um histórico de resultados por perfil de alpha (conservative/balanced/exploratory). Com probabilidade 0.85 escolhe o perfil de maior taxa de sucesso histórica; com 0.15 explora aleatoriamente. Para ativar o portfólio são necessárias ao menos 3 tentativas por perfil.

**Nível 3 — clamping por perfil de ilha (Sprint 4):** os bounds finais de alpha são recortados pelo perfil atribuído a cada ilha (Conservative: 0.15–0.185; Balanced: 0.175–0.215; Exploratory: 0.21–0.25).

### Lookahead Crítico de Alocação (Melhoria 3)

A função `futureFlexibilityScore()` avalia, para cada slot candidato, o impacto sobre as próximas aulas mais críticas da fila. Slots que eliminam todas as opções de uma aula futura recebem penalidade de +15.0; slots que deixam apenas uma opção recebem +2.5. Slots que preservam 7 ou mais opções encerram a avaliação antecipada (early-exit para economizar CPU).

O score final do slot é: `rewardScore - blockingPenalty`. Valores negativos penalizam o slot diretamente no ranking da RCL.

### Salvage de Tentativas Fracassadas (Melhoria 1)

Quando uma tentativa é rejeitada pelo quality gate, o solver não a descarta inteiramente. O método `updateSalvageFromCandidate()` avalia se a tentativa contém ao menos 40% de genes livres de conflito com hard penalty abaixo de 4× o baseline. Se sim, esses genes são extraídos por `extractConflictFreeGenes()` e armazenados em `$bestSalvageGenes`.

Na próxima tentativa, `tryCreateIndividualFromSalvage()` pré-aloca os genes do salvage e roda o GRASP apenas sobre o subproblema restante, reduzindo custo e aumentando a chance de obter uma semente viável.

### Nogoods Persistentes (Melhoria 4)

O sistema mantém memória de padrões que colapsam repetidamente entre execuções. No início de `createIndividual()` o solver chama `loadNogoodsFromPersistentCache()` para mesclar nogoods de execuções anteriores (chave `ag.nogoods.{horarioId}`, TTL 7 dias) ao mapa em memória. Ao rejeitar uma semente, `persistNogoodsToPersistentCache()` salva os nogoods atualizados (limitados a 500 entradas por tipo, via `capNogoodsForPersistence()`).

### Quality Gate e Batch Quality (Quality Gate + Sprint 3)

**Quality gate individual:** antes de aceitar cada semente, o solver avalia hard penalty total, razão de conflitos hard, degradação acumulada e capacidade de repair dentro do orçamento.

**Batch quality report:** após inicializar toda a população, o `GeneticAlgorithmEngine` computa e publica um report estruturado com:
- `uniqueness_ratio` — fração de assinaturas estruturais únicas
- `fitness_min/max/avg/std_dev` — estatísticas de fitness
- `fitness_coefficient_of_variation` — dispersão relativa
- `verdict` — `ok`, `low_signature_diversity`, `fitness_collapsed`, `low_fitness_dispersion` ou `empty`
- `issues[]` — lista de problemas detectados

O report é publicado via `progress.report(phase='initial_population', stage='batch_quality')` e logado em `schedule.initial_population.batch_quality`.

## Fitness e Avaliação

### Modelo de score

O sistema usa score lexicográfico.

- soluções inviáveis ficam em [0, 50)
- soluções viáveis ficam em [50, 100]
- qualquer solução viável domina qualquer inviável

Isso preserva a hierarquia entre viabilidade estrutural e qualidade fina.

### Regras hard

As regras hard atuais incluem:

- TeacherConflictRule
- ClassConflictRule
- WorkloadExceededRule
- MandatoryBlockViolationRule
- CustomConstraintHardRule

### Regras soft

As regras soft atuais incluem:

- WindowPenaltyRule
- DistributionRule
- MaxLessonsPerDayRule
- ConsecutiveLessonRule
- PreferredTimeRule
- CustomConstraintSoftRule

### Constraints customizadas

O solver já recebe e aplica constraints customizadas em três grupos:

- SYNC_SAME_TIMESLOT
- MUTUAL_EXCLUSION
- TIME_PLACEMENT

Além do fitness, elas também entram no diagnóstico prévio de viabilidade e no repair especializado durante a evolução.

### Delta Fitness

Há infraestrutura para avaliação incremental baseada em região afetada e dependências de regras. O objetivo é recalcular apenas o que foi realmente impactado por mutações e operadores locais, reduzindo custo em relação a uma reavaliação global integral.

## Operadores Genéticos e Estratégias Evolutivas

### Seleção

- TournamentSelection
- integração com fitness sharing para diversidade

### Crossover

O código monta múltiplos operadores e, no fluxo principal observado, privilegia composição que preserva estrutura útil da solução, como:

- BlockPreservingCrossover
- ConflictGraphCrossoverOperator
- SinglePointCrossover

### Mutação

Há um pool adaptativo de mutações com destaque para:

- StructuredSwapMutation
- GeneSwapMutation
- ConflictGuidedMutation
- AdaptiveDiversityMutation
- AdaptiveMutationController

### Replacement e diversidade

- AdaptiveNichingReplacement
- TopEliteStrategy
- cálculo de diversidade e entropia da população

## Intensificação com ALNS/LNS

### O que existe

O sistema possui uma implementação real de ALNS em AdaptiveLargeNeighborhoodSearch.

Componentes associados:

- destroy operators: RandomDestroyOperator, ConflictDestroyOperator, ClusterDestroyOperator
- repair operators: LNSRepairAdapter, RegretInsertionOperator
- política de aceitação: StrictScoreImprovementAcceptance

### Como opera

O ALNS é disparado com frequência configurável ao longo da evolução para intensificação local. Ele remove parte da solução e tenta reinserir os genes com operadores distintos, escolhidos adaptativamente a partir de histórico de desempenho.

## Landscape Analysis

### Elementos implementados

- LandscapeAnalyzer
- LandscapeDetector
- LandscapeEngine
- LandscapeMemory
- LandscapeResponseStrategy

### Papel na arquitetura

O solver observa o estado recente da população para detectar padrões como estagnação, vales e sinais de convergência prematura. Essa observação influencia a intensidade e o momento de respostas adaptativas, especialmente em ALNS e hyper-heuristics.

Também existe persistência e cache de landscape_state, landscape_phenomenon e landscape_observation.

## Hiper-heurísticas

O código já inclui uma camada de aprendizado para operadores:

- LearningHyperHeuristicController
- OperatorPerformanceTracker
- OperatorRewardCalculator
- EpsilonGreedySelector

Essa camada mede o efeito dos operadores e ajusta a exploração versus explotação com base em recompensa observada.

## Repair

### Repair atual

O GreedyRepairOperator já executa:

- priorização de genes inválidos
- relocação
- swap
- local rebuild
- telemetria por passe
- abort por falta de progresso
- abort por orçamento de tempo

### Extensão especializada para constraints customizadas

Foi introduzido um contrato de extensão:

- RepairHeuristicExtension

Ele permite:

- acrescentar repair targets
- filtrar slots candidatos
- injetar penalidade de ranking
- contar violações remanescentes específicas

O módulo Horários registra `CustomConstraintRepairExtension` com heurísticas efetivas para:

- gerar targets de repair para violações de `TIME_PLACEMENT`, `MUTUAL_EXCLUSION` e `SYNC_SAME_TIMESLOT`
- filtrar slots candidatos com base em janelas de `TIME_PLACEMENT` (REQUIRED/FORBIDDEN)
- contar violações remanescentes customizadas no ranking interno do repair

Esse comportamento roda dentro do `GreedyRepairOperator` sem acoplar regras de domínio no núcleo do AG.

## Modelo de Ilhas

O solver roda em modelo de ilhas via:

- IslandModelEngine
- Island
- BestIndividualsMigration
- **IslandProfile** (Conservative / Balanced / Exploratory) — implementado no Sprint 4

### Perfis de Ilha (Sprint 4)

Cada ilha recebe um perfil diferenciado em `RunGeneticAlgorithm`, determinado pelo índice `$i`:

| Ilha | Perfil | Alpha GRASP | Mutation base | Mutation max |
|------|--------|-------------|---------------|--------------|
| 0 | Conservative | 0.15 – 0.185 | 0.015 | 0.28 |
| 1 | Exploratory | 0.21 – 0.25 | 0.030 | 0.42 |
| ≥2 | Balanced | 0.175 – 0.215 | 0.020 | 0.35 |

O perfil é propagado via `GeneticAlgorithmEngine::setIslandProfile()` → `ScheduleProblem::setIslandProfile()`. O `AdaptiveMutationController` de cada ilha é instanciado com os parâmetros do perfil. O ScheduleProblem usa o perfil para recortar os bounds finais do alpha GRASP em `resolveAdaptiveAlpha()`.

O log `solver.islands.profiles_configured` é emitido após a configuração de todas as ilhas, com a lista de perfis atribuídos.

### Migração

A migração periódica usa `BestIndividualsMigration(2)` com intervalo de 5 gerações. Isso garante troca de material genético entre ilhas sem suprimir diversidade prematuramente.

## Critérios de Término

O término considera combinação de:

- número máximo de gerações
- target fitness
- gerações sem melhoria
- variância da população
- diversidade mínima
- entropia mínima
- cancelamento operacional

Essa lógica é centralizada em VarianceBasedTerminationCriterion com apoio das métricas populacionais.

## Telemetria e Observabilidade

### Telemetria operacional

O sistema possui observabilidade em três níveis:

- cache para feedback rápido da UI
- banco para histórico de métricas por execução e geração
- logs estruturados para diagnóstico profundo

### Componentes principais

- GATelemetryLogger
- ExecutionMetricsRecorder
- CacheProgressReporter
- CacheAndDbProgressReporter

### O que é registrado

Entre os dados observados no código e logs:

- início, falha, cancelamento e conclusão da execução
- geração atual
- melhor fitness, média, variância, diversidade e entropia
- operador usado e recompensa
- destroy/repair operators do ALNS
- landscape state, phenomenon e observation
- heartbeats de progresso
- métricas e bottlenecks da população inicial
- tentativas GRASP, fail-fast, repair e quality gate
- carregamento de constraints customizadas no solver

### Heartbeat e monitoramento

ExecutionStatusPanel monitora heartbeat por fase e estágio. Quando o sinal envelhece demais, a UI destaca possibilidade de estagnação operacional e oferece mensagens orientativas específicas.

Isso é um diferencial importante: o sistema já distingue travamento lógico, demora esperada em repair e ausência de sinal do worker.

## UI e Experiência Operacional

### ExecutionCenter

É o centro de disparo da execução. Permite:

- configurar população, gerações e ilhas
- iniciar execução
- cancelar execução em andamento
- consultar histórico recente
- exibir relatórios de readiness e impacto para search response

### ExecutionDashboard e painéis

O acompanhamento operacional é composto por:

- status textual e traduzido da execução
- elapsed time
- contagem de métricas gravadas
- snapshot do progresso corrente
- summary de status final ou de interrupção
- recomendações operacionais geradas a partir do contexto

### Diagnóstico de inviabilidade

Há suporte explícito na UI para leitura de causas prováveis de falha, principalmente quando o problema ocorre antes da evolução, na população inicial.

## Persistência e Segurança da Solução Final

PersistBestSolutionService não apenas salva. Antes disso, ele valida conflitos estruturais finais por turma e professor. Se detectar incoerência, interrompe a persistência com exceção.

Isso cria uma última barreira de integridade antes de gravar alocações finais.

## Pontos Fortes da Arquitetura Atual

- pipeline completo, assíncrono e observável de ponta a ponta
- construção inicial com GRASP adaptativo, lookahead, salvage, portfólio epsilon-greedy e nogoods persistentes
- ilhas diferenciadas por perfil (Conservative/Balanced/Exploratory) com parâmetros autônomos de alpha e mutação
- meta de qualidade do batch com veredito automatizado e publicação via progress event
- separação clara entre AG genérico e problema de horários — ScheduleProblem não vaza para GeneticAlgorithmEngine além da interface GeneticProblem
- integração madura de métricas, cache, banco e logs estruturados
- landscape e hyper-heuristics acoplados ao fluxo principal de evolução
- ALNS real com seleção adaptativa de destroy/repair e critério de aceitação configurável
- proteção operacional contra estagnação, sementes ruins e execuções degradadas
- suporte formal a constraints customizadas sem contaminar o núcleo do AG
- cobertura de testes unitários validada: 104 passed, 0 failed

## Limitações e Riscos Atuais

- repair especializado para constraints customizadas já está ativo, mas ainda sem benchmark A/B dedicado por cenário
- a quantidade de heurísticas e parâmetros cresceu bastante; calibração e diagnóstico de interações complexas são custosos sem benchmark reproduzível
- avaliação delta fitness existe na infraestrutura, mas o ganho real de performance depende de medir cobertura real de uso nos operadores
- a construção inicial, embora muito mais sofisticada, ainda é serial — em cenários grandes, é o principal gargalo de latência
- nogoods persistentes crescem com execuções; o cap de 500 por tipo é conservador e pode limitar o ganho em cenários com muitas repetições

## Sugestões de Melhoria

### Melhorias gerais

- consolidar uma matriz oficial de operadores realmente ativos por execução, para reduzir distância entre documentação e wiring real
- instrumentar melhor tempo por operador (cpu/wall), não apenas por geração
- separar mais explicitamente telemetria operacional de telemetria científica do AG, facilitando leitura por perfis diferentes
- revisar periodicamente a documentação para mantê-la aderente ao wiring real do RunGeneticAlgorithm e do GeneticAlgorithmEngine
- considerar um modo "debug verboso" ativável por config para logar decisões internas do portfólio e do salvage em diagnóstico

### Status de Implementação (Abr/2026)

Todas as melhorias de alto ROI para a população inicial e para o modelo de ilhas foram implementadas:

| Ciclo | Item | Status | Arquivo principal |
|-------|------|--------|-------------------|
| Sprint 1 | Ranking rico de candidate slots (live_tightness, contention) | ✅ Implementado | ScheduleProblem |
| Sprint 2 | Alpha adaptativo por contexto e pressão | ✅ Implementado | ScheduleProblem::resolveAdaptiveAlpha() |
| Sprint 3 | Meta de qualidade da população (batch quality) | ✅ Implementado | GeneticAlgorithmEngine::computeInitialPopulationBatchQuality() |
| Sprint 4 | Perfis de ilha (Conservative/Balanced/Exploratory) | ✅ Implementado | IslandProfile, RunGeneticAlgorithm, GeneticAlgorithmEngine |
| Melhoria 1 | Salvage de tentativas fracassadas | ✅ Implementado | ScheduleProblem::tryCreateIndividualFromSalvage() |
| Melhoria 2 | Portfólio adaptativo de alpha (epsilon-greedy) | ✅ Implementado | ScheduleProblem::portfolioBiasedAlphaProfile() |
| Melhoria 3 | Lookahead crítico de alocação (1-2 passos) | ✅ Implementado | ScheduleProblem::futureFlexibilityScore() |
| Melhoria 4 | Nogoods persistentes entre execuções | ✅ Implementado | ScheduleProblem::loadNogoodsFromPersistentCache() |

### Cobertura de Testes

| Arquivo de teste | Escopo |
|-----------------|--------|
| GeneticAlgorithmEngineStandaloneTest | Engine standalone, batch_quality report, Sprint 3 |
| IslandProfileTest | Enum IslandProfile, ranges alpha/mutação, Sprint 4 |
| RunGeneticAlgorithmConstraintLoadingTest | Carregamento de constraints no solver |
| AlnsTelemetryTest | Telemetria ALNS |
| FitnessEvaluatorLexicographicTest | Score lexicográfico hard/soft |
| VarianceBasedTerminationCriterionTest | Critério de término por variância |
| LandscapeObservationTest | Observação e memória de landscape |
| HyperHeuristicContractTest | Contratos da hiper-heurística |
| ConstraintFeasibilityAnalyzerTest | Analyzer de viabilidade de constraints |

## Próximos Passos Recomendados


O ciclo atual (Sprints 1–4 + Melhorias 1–4) foi concluído. Todas as melhorias de alta prioridade para a população inicial e para o modelo de ilhas estão implementadas e cobertas por testes. As oportunidades abaixo são o próximo horizonte natural de evolução.

### A. Benchmark do repair customizado por cenário (Alta viabilidade / Alto ROI)

O repair especializado para constraints customizadas já está ativo via `CustomConstraintRepairExtension`. O próximo passo é medir, com cenários reproduzíveis, o impacto em latência e convergência quando há alta densidade de constraints `SYNC_SAME_TIMESLOT`, `MUTUAL_EXCLUSION` e `TIME_PLACEMENT`.

**Esforço:** Baixo-Médio | **Risco:** Baixo | **ROI:** Alto

### B. Benchmark A/B reproduzível por cenário (Alta viabilidade / ROI operacional)

Criar um conjunto de cenários canônicos (ex: escola pequena, escola média com constraints, escola com blocos obrigatórios pesados) e um runner comparativo que mede custo por fase: população inicial, evolução, ALNS, repair, persistência. Isso permitiria quantificar o ganho de cada ciclo de melhoria e orientar decisões de configuração.

**Esforço:** Médio | **Risco:** Baixo | **ROI:** Alto (operacional e diagnóstico)

### C. Portfólio de construtores estruturalmente distintos (Alta viabilidade / Alto ROI futuro)

O portfólio atual diferencia apenas pelo alpha do GRASP. O próximo passo é ter construtores com estratégias distintas de ordenação inicial:

- construtor orientado a blocos obrigatórios desde a primeira rodada
- construtor orientado a professores com disponibilidade restrita
- construtor orientado a turmas com maior número de conflitos potenciais
- construtor por regret inserção pura desde o início (sem GRASP)

Isso aumenta a diversidade estrutural real entre ilhas, não apenas diversidade superficial de alpha.

**Esforço:** Alto | **Risco:** Médio | **ROI:** Alto (em cenários complexos)

### D. Estratégia de migração diferenciada por perfil de ilha (Média viabilidade / Médio ROI)

Hoje a migração é simétrica (BestIndividualsMigration entre todas as ilhas). Um refinamento natural é:

- ilha Exploratory envia apenas para Balanced (não para Conservative)
- ilha Conservative recebe apenas de Balanced (filtra o "ruído" exploratório)
- medir e logar impacto de cada evento de migração no fitness da ilha receptora

**Esforço:** Médio | **Risco:** Baixo-Médio | **ROI:** Médio-Alto

### E. Nogoods por perfil de configuração / turno (Média viabilidade / Médio ROI)

Os nogoods persistentes hoje são salvos por `horarioId`. Uma versão mais poderosa salvaria por "perfil de problema" (ex: número de turmas, turnos, constraints ativas), permitindo transferência de conhecimento entre horários estruturalmente similares de anos letivos diferentes.

**Esforço:** Médio | **Risco:** Baixo | **ROI:** Médio-Alto

### F. Relatório pós-execução estruturado com custo por fase (Alta viabilidade / Alto ROI operacional)

A telemetria em logs já está rica. Falta um relatório consolidado ao final de cada execução que mostre:

- tempo e custo (tentativas, rejeições) da fase de população inicial por ilha
- gerações totais e contribuição do ALNS versus evolução pura
- número de migrações e seu efeito no fitness
- resumo de nogoods aprendidos e persistidos
- perfil de island utilizado e taxa de qualidade de batch por ilha

Esse relatório informaria tanto operadores quanto o desenvolvimento do solver.

**Esforço:** Baixo-Médio | **Risco:** Nenhum | **ROI:** Alto (operacional)

### G. Avaliação paralela da população inicial (Alta viabilidade técnica / Médio-Alto ROI)

A construção dos indivíduos da população inicial é hoje serial. Com coroutines ou pools de workers, seria possível construir os N indivíduos em paralelo, reduzindo o tempo da fase mais lenta do solver.

**Pré-requisito:** garantir imutabilidade de ScheduleData e isolamento do estado de ScheduleProblem por instância.

**Esforço:** Alto | **Risco:** Médio (concorrência) | **ROI:** Alto (latência)

### H. Adaptive ALNS com frequência orientada por landscape (Média viabilidade / Médio ROI)

Hoje o ALNS opera com frequência fixa (`lnsFrequency`). Uma evolução natural é disparar ALNS com frequência maior quando o landscape detecta estagnação e menor quando há progresso consistente. A infraestrutura de landscape já produz `LandscapeMetrics` que poderiam orientar esse ajuste.

**Esforço:** Médio | **Risco:** Baixo | **ROI:** Médio

## Arquivos Mais Relevantes

### Orquestração

- app/Modules/AG/Application/RunGeneticAlgorithm.php
- app/Modules/Horarios/Application/GenerateScheduleAction.php
- app/Jobs/GerarHorarioJob.php

### Problema e construção inicial

- app/Modules/Horarios/Domain/Problem/ScheduleProblem.php
- app/Modules/AG/Infrastructure/Population/PopulationGenerator.php
- app/Modules/AG/Domain/Repair/GreedyRepairOperator.php

### Evolução e ilhas

- app/Modules/AG/Application/GeneticAlgorithmEngine.php
- app/Modules/AG/Domain/Evolution/IslandModel/IslandModelEngine.php
- app/Modules/AG/Domain/Evolution/IslandModel/BestIndividualsMigration.php

### Fitness e diversidade

- app/Modules/AG/Domain/Fitness/FitnessEvaluator.php
- app/Modules/AG/Domain/Fitness/FitnessWeights.php
- app/Modules/AG/Domain/Fitness/Delta
- app/Modules/AG/Domain/Operators/Selection/FitnessSharing

### ALNS e landscape

- app/Modules/AG/Domain/Intensification/LNS/ALNS/AdaptiveLargeNeighborhoodSearch.php
- app/Modules/AG/Domain/Landscape/LandscapeEngine.php
- app/Modules/AG/Domain/HyperHeuristic/LearningHyperHeuristicController.php

### Telemetria e UI

- app/Modules/AG/Infrastructure/Progress/CacheAndDbProgressReporter.php
- app/Modules/AG/Infrastructure/Metrics/ExecutionMetricsRecorder.php
- app/Modules/AG/UI/Livewire/ExecutionCenter.php
- app/Modules/AG/UI/Livewire/ExecutionDashboard.php
- app/Modules/AG/UI/Livewire/ExecutionStatusPanel.php

### Constraints customizadas

- app/Modules/Horarios/Domain/Constraints
- app/Modules/Horarios/Application/LoadActiveScheduleConstraintsAction.php
- app/Modules/Horarios/Application/Constraints/ConstraintSolverPayloadMapper.php

### Perfis de ilha

- app/Modules/AG/Domain/Evolution/IslandModel/IslandProfile.php

## Conclusão

A arquitetura do AG do Projeto Horário completou um ciclo completo de maturação (Sprints 1–4 + Melhorias 1–4). Ela vai muito além de um algoritmo genético simples e incorpora mecanismos modernos de intensificação, diversidade, observabilidade e adaptação.

O foco do ciclo concluído foi a fase de construção inicial e o modelo de ilhas, que eram os principais determinantes da eficiência do pipeline. Com portfólio adaptativo, lookahead, salvage, nogoods persistentes, perfis de ilha diferenciados e meta de qualidade do batch, essa fase está substancialmente mais robusta do que uma inicialização aleatória ou GRASP simples.

O próximo horizonte de evolução é diferente: não se trata mais de cobrir técnicas ausentes, mas de:

1. **Medir o que foi ativado** — repair especializado para constraints customizadas já está em produção no `GreedyRepairOperator`; o próximo passo é benchmark A/B por cenário.
2. **Medir com rigor** — benchmark reproduzível por cenário para quantificar ganho real de cada ajuste e orientar calibração.
3. **Diversificar construtores além do alpha** — portfólio estruturalmente heterogêneo (regret puro, blocos obrigatórios first, professores críticos first) para aumentar diversidade real entre ilhas.
4. **Reduzir latência** — construção paralela da população inicial como próximo salto de performance em cenários grandes.
