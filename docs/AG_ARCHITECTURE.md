# Arquitetura do Algoritmo Genético - Projeto Horário

## Resumo Executivo

O Projeto Horário implementa um solver híbrido para timetabling escolar baseado em:

- algoritmo genético multioperador
- construção inicial guiada por GRASP
- intensificação por ALNS/LNS
- modelo de ilhas com migração periódica
- avaliação lexicográfica hard/soft com suporte a delta fitness
- telemetria operacional detalhada
- monitoramento Livewire em tempo real

Na prática, o sistema não é apenas um AG clássico. Ele combina construção gulosa randomizada, repair iterativo, hiper-heurísticas, análise de landscape e observabilidade operacional suficiente para diagnosticar gargalos na população inicial e na evolução.

## Escopo Real Implementado

Hoje a arquitetura cobre, de ponta a ponta:

- acionamento pela UI de execução
- criação de registro de execução
- despacho assíncrono via fila
- orquestração do solver na camada de aplicação
- montagem do problema de horários com ScheduleData
- evolução em ilhas com migração
- repair durante a construção inicial e durante a evolução
- ALNS para intensificação local
- análise de landscape e resposta adaptativa
- persistência transacional da melhor solução
- cache, banco e logs para progresso e métricas
- integração com constraints customizadas no solver, fitness, viabilidade e preparação de repair

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

- fila de alocação orientada por dificuldade
- diagnóstico preventivo antes da construção
- RCL com alpha aleatório em faixa configurada internamente
- fallback controlado para alocações forçadas
- quality gate para rejeitar sementes ruins cedo
- fail-fast para interromper tentativas estruturalmente degradadas
- reaproveitamento de semente aceita da própria execução
- reaproveitamento de semente histórica de execuções anteriores
- repair com orçamento operacional
- telemetria detalhada da construção

### Heurísticas observadas

Entre os sinais encontrados no código, a construção inicial já considera:

- interseção de disponibilidade de professor e turma
- dificuldade estrutural por aula
- pressão de nogoods aprendidos
- reorder dinâmico da fila
- uso de regret em partes da instrumentação de construção
- limites adaptativos de tentativas

### Quality Gate

Antes de aceitar a semente inicial, o solver avalia:

- hard penalty total
- razão de conflitos hard
- degradação acumulada na tentativa
- capacidade de repair dentro do orçamento

Isso impede que a evolução comece a partir de sementes já muito ruins.

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

Além do fitness, elas também entram no diagnóstico prévio de viabilidade e na preparação de repair futuro.

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

### Extensão futura já preparada

Foi introduzido um contrato de extensão:

- RepairHeuristicExtension

Ele permite:

- acrescentar repair targets
- filtrar slots candidatos
- injetar penalidade de ranking
- contar violações remanescentes específicas

O módulo Horários já registra CustomConstraintRepairExtension como placeholder para repair especializado de constraints.

## Modelo de Ilhas

O solver roda em modelo de ilhas via:

- IslandModelEngine
- Island
- BestIndividualsMigration

O RunGeneticAlgorithm monta múltiplas ilhas e configura migração periódica. Isso aumenta exploração paralela do espaço e reduz convergência prematura local.

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

- pipeline completo, assíncrono e observável
- construção inicial bem mais avançada que um random initializer simples
- separação razoável entre AG genérico e problema de horários
- integração madura de métricas, cache e banco
- landscape e hyper-heuristics já acoplados ao fluxo principal
- ALNS real, não apenas planejado
- proteção operacional contra estagnação e sementes ruins
- suporte formal a constraints customizadas sem contaminar o núcleo do AG

## Limitações e Riscos Atuais

- a construção inicial continua sendo a área mais crítica do solver
- parte da documentação histórica descreve operadores ou frequências que podem não refletir exatamente o wiring atual
- repair especializado para constraints ainda é preparatório, não efetivo
- a quantidade de heurísticas cresceu bastante, aumentando custo de calibração e risco de interação difícil de explicar
- a evolução depende fortemente de uma boa semente inicial; se a construção degrada, o resto do pipeline trabalha em desvantagem

## Sugestões de Melhoria

### Melhorias gerais

- consolidar uma matriz oficial de operadores realmente ativos por execução, para reduzir distância entre documentação e wiring real
- adicionar um relatório automático pós-execução com custo por fase: população inicial, evolução, ALNS, repair final e persistência
- separar mais explicitamente telemetria operacional de telemetria científica do AG, facilitando leitura por perfis diferentes
- instrumentar melhor tempo por operador, não apenas por geração
- criar benchmark reproduzível com cenários padronizados para comparar ajustes de heurística

### Melhorias prioritárias na geração da população inicial

Esta é a principal recomendação.

#### 1. Portfólio de construtores iniciais

Hoje o GRASP é o centro da construção. Vale adicionar um portfólio de construtores e distribuir sementes entre estratégias diferentes, por exemplo:

- GRASP padrão
- construtor por regret inserção desde o início
- construtor por blocos obrigatórios primeiro
- construtor orientado a professores críticos
- construtor orientado a turmas críticas

Isso reduz correlação entre sementes e aumenta diversidade estrutural real, não apenas diversidade superficial.

#### 2. Alpha adaptativo por contexto, não só aleatório

Em vez de apenas sortear alpha numa faixa fixa, usar sinais da própria tentativa:

- se a fila está muito apertada, reduzir aleatoriedade
- se a tentativa anterior colapsou cedo, aumentar diversidade
- se há reaproveitamento de semente, usar alpha mais conservador

Na prática, isso transforma o GRASP em um construtor mais responsivo ao estado do problema.

#### 3. Lookahead curto no momento da alocação

O custo local de um slot não deveria considerar apenas conflito imediato. Sugestão:

- penalizar slots que destroem opções das próximas aulas mais críticas
- medir consumo de capacidade compartilhada por professor e turma
- incorporar risco de bloquear blocos consecutivos futuros

Um lookahead de 1 ou 2 passos já tende a melhorar bastante a qualidade das sementes.

#### 4. Reaproveitamento parcial de tentativas fracassadas

Atualmente há fail-fast, quality gate e repair, mas ainda há espaço para uma estratégia de salvage mais explícita:

- preservar prefixos viáveis da tentativa
- congelar alocações estruturalmente boas
- reconstruir apenas o subproblema restante

Isso evita jogar fora tentativas quase úteis quando o colapso acontece tarde.

#### 5. Seeds estratificadas para as ilhas

As ilhas se beneficiariam mais se recebessem populações iniciais com perfis diferentes, por exemplo:

- ilha mais conservadora, baixa aleatoriedade
- ilha mais exploratória, alta aleatoriedade
- ilha orientada a blocos consecutivos
- ilha orientada a distribuição por turma

Hoje o modelo de ilhas já existe; falta explorar melhor a diversidade no nascimento das populações.

#### 6. Candidate slots com ranking mais rico

O ranking de slots candidatos da população inicial pode evoluir para considerar explicitamente:

- pressão de constraints customizadas
- saturação do primeiro tempo
- elasticidade restante da turma e do professor
- probabilidade de repair posterior bem-sucedido

Isso aproximaria construção inicial e repair, reduzindo decisões localmente baratas, mas globalmente ruins.

#### 7. Aprendizado de nogoods mais forte entre execuções

Já existe sinal de reaproveitamento histórico. O próximo passo seria consolidar memória mais útil sobre:

- padrões de slot que colapsam repetidamente
- combinações aula-professor-turma com alto índice de falha
- seeds históricas por perfil de configuração

Isso pode reduzir dramaticamente o custo das primeiras tentativas em cenários recorrentes.

#### 8. Meta de qualidade da população, não só do indivíduo

Além de aceitar um indivíduo que passa no quality gate, vale medir a população inicial como conjunto:

- diversidade estrutural mínima
- cobertura de diferentes regiões do espaço
- distribuição de hard penalty entre sementes

Hoje é possível começar a evolução com sementes viáveis, mas excessivamente parecidas. Isso tende a reduzir o ganho do modelo de ilhas e do fitness sharing.

## Próximos Passos Recomendados

1. Tratar a população inicial como subsistema próprio, com benchmark e tuning dedicados.
2. Consolidar relatório operacional por execução com custo por fase e principais gargalos.
3. Ativar, em fases futuras, repair especializado para constraints customizadas usando a infraestrutura de extensão já preparada.
4. Revisar periodicamente a documentação para mantê-la aderente ao wiring real do RunGeneticAlgorithm e do GeneticAlgorithmEngine.

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

## Conclusão

A arquitetura do AG do Projeto Horário já está em um estágio avançado. Ela vai muito além de um algoritmo genético simples e incorpora mecanismos modernos de intensificação, diversidade, observabilidade e adaptação.

O principal ponto de atenção não é a ausência de técnicas, mas sim a necessidade de calibrar e fortalecer a geração da população inicial, porque ela continua sendo o principal determinante da eficiência do restante do pipeline.

Se a próxima rodada de evolução arquitetural focar nisso, o ganho esperado é duplo:

- menor custo operacional na fase mais cara e frágil
- melhor ponto de partida para ilhas, ALNS, repair e hyper-heuristics
