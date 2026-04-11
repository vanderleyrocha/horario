# Arquitetura Atual do Solver AG

## Objetivo deste documento

Este arquivo descreve o que está efetivamente implementado hoje no motor do solver de horários.

O foco aqui é o wiring real do código: pipeline operacional, componentes ativos no fluxo principal, mecanismos de observabilidade, políticas de repair, ALNS, landscape, ilhas e persistência final.

Quando existir diferença entre infraestrutura disponível e componente realmente usado no caminho principal, o documento prioriza o caminho principal.

## Visão geral

O solver atual é um pipeline assíncrono em Laravel que combina:

- construção inicial por GRASP adaptativo com quality gate
- modelo de ilhas com perfis distintos
- algoritmo genético com crossover fixo e mutação adaptativa
- repair guloso com orçamento e telemetria detalhada
- intensificação local por ALNS/LNS
- análise de landscape para modular ALNS, mutação e pressão de seleção
- migração periódica entre ilhas
- persistência transacional da melhor solução
- observabilidade em cache, banco e logs

Na prática, o solver não é um AG simples. A construção inicial é sofisticada, a evolução incorpora mecanismos adaptativos, e a operação é fortemente instrumentada para diagnóstico de travas, degradação e baixa qualidade de sementes.

## Pipeline operacional

O fluxo de execução ativo hoje é:

1. ExecutionCenter cria a execução e despacha o job.
2. GerarHorarioJob sobe limites operacionais, inicia telemetria e reporters.
3. GenerateScheduleAction delega para RunGeneticAlgorithm.
4. RunGeneticAlgorithm monta problema, engine, ilhas e operadores.
5. IslandModelEngine executa a evolução sincronizada das ilhas.
6. RunGeneticAlgorithm aplica repair final na melhor solução.
7. PersistBestSolutionService valida integridade estrutural e persiste em transação.
8. GerarHorarioJob fecha a execução como finished, cancelled ou failed, com contexto operacional e status_health.

## Camada de job, telemetria e status final

GerarHorarioJob é o ponto central de operação. Hoje ele:

- roda em fila com timeout de 3600 segundos
- remove limite de memória e tempo do processo PHP
- cria ExecutionMetricsRecorder para banco
- cria CacheProgressReporter para feedback rápido da UI
- cria CacheAndDbProgressReporter para sincronizar cache, banco e logs
- usa GATelemetryLogger para logs estruturados
- executa GenerateScheduleAction
- em sucesso, grava best_fitness e contexto final
- em cancelamento, grava status cancelled
- em falha, grava status failed e relança a exceção

O status final inclui um contexto operacional mais rico, com:

- título e razão da finalização
- fase e estágio da última telemetria disponível
- resumo de counters operacionais
- sugestões de diagnóstico
- relatório pós-execução
- status_health com health, dominant_phase e recommendations

O builder de health hoje usa regras simples e explícitas:

- critical se houve fail-fast na população inicial
- critical se quality gate rejeitou tentativas até o limite
- warn se repair acumulou pressão alta
- warn se ALNS não ativou em janela longa com gerações suficientes
- ok nos demais casos

## RunGeneticAlgorithm: wiring real do solver

RunGeneticAlgorithm é a composição principal do motor.

Hoje ele faz o seguinte:

1. Lê configuração via GeneticAlgorithmConfigDTO.
2. Resolve island_count a partir da execução já criada ou do config.
3. Carrega constraints customizadas ativas do horário.
4. Converte essas constraints para snapshots solver-safe.
5. Monta ScheduleData via ScheduleDataBuilder.
6. Monta ConstraintEvaluationPipeline com os avaliadores customizados.
7. Monta FitnessEvaluator com regras hard e soft.
8. Instancia GreedyRepairOperator, opcionalmente com CustomConstraintRepairExtension.
9. Instancia ScheduleProblem.
10. Cria selection, crossover, elitism, termination, ALNS, landscape e hiper-heurística.
11. Cria uma engine por ilha, com perfil próprio.
12. Executa IslandModelEngine.
13. Aplica repair final na melhor solução antes da persistência.

### Constraints customizadas realmente carregadas

O pipeline ativo de constraints customizadas trabalha com três tipos:

- SYNC_SAME_TIMESLOT
- MUTUAL_EXCLUSION
- TIME_PLACEMENT

Essas constraints entram em três pontos:

- avaliação hard e soft
- diagnóstico de viabilidade durante a construção inicial
- repair especializado via CustomConstraintRepairExtension

## Fitness e regras ativas

O FitnessEvaluator é montado com este conjunto de regras ativas:

### Hard

- TeacherConflictRule
- ClassConflictRule
- WorkloadExceededRule
- MandatoryBlockViolationRule
- CustomConstraintHardRule

### Soft

- WindowPenaltyRule
- DistributionRule
- MaxLessonsPerDayRule
- ConsecutiveLessonRule
- PreferredTimeRule
- CustomConstraintSoftRule

O ScheduleProblem ainda expõe infraestrutura para avaliação incremental e cache de fitness, usada em partes do ciclo de repair e ALNS.

## Construção da população inicial

O ScheduleProblem concentra a fase mais sofisticada do solver.

### Fluxo atual de criação de indivíduo

Ao criar um indivíduo, o problema tenta, nesta ordem:

1. accepted seed da própria execução, se existir
2. historical seed reaproveitada de execuções anteriores
3. sync hybrid seed
4. salvage de genes aproveitáveis de tentativas rejeitadas
5. construção GRASP completa com limite adaptativo de tentativas

### O que está implementado na construção inicial

- fila de alocação por dificuldade
- diagnóstico preventivo antes de construir
- alpha adaptativo por contexto e por histórico recente
- portfólio de construtores com viés epsilon-greedy
- estratégias de construção identificadas por motivo e histórico
- reorder dinâmico da fila durante o preenchimento
- regret-based selection na fronteira da RCL
- telemetria periódica a cada bloco de alocações
- fallback controlado para alocações forçadas
- fail-fast operacional
- quality gate individual por tentativa
- salvage de genes livres de conflito de tentativas rejeitadas
- nogoods persistentes entre execuções
- reaproveitamento de sementes históricas e da própria execução

### Quality gate e relaxamentos ativos

O quality gate da população inicial hoje não é fixo; ele relaxa de forma controlada ao longo das tentativas.

O código mantém:

- limites base de hard penalty e conflito hard
- janela de fail-fast
- modo relaxado após várias tentativas
- modo emergencial quando as rejeições se acumulam cedo
- possibilidade de pular repair quando a semente já nasce muito degradada

O objetivo é evitar gastar tempo com candidatos estruturalmente ruins, mas sem abandonar cedo demais cenários difíceis.

### Repair na população inicial

Na população inicial, o repair é usado com orçamento operacional próprio. Hoje existem limites explícitos para:

- tempo máximo do repair da semente
- número máximo de passes sem progresso
- critérios de abort por falta de progresso

Quando uma tentativa falha, o solver registra explainability e bottlenecks, o que alimenta tanto logs quanto o contexto operacional final.

## ScheduleProblem como núcleo do problema de horários

Além da construção inicial, o ScheduleProblem faz:

- criação de cromossomos iniciais
- avaliação de viabilidade e fitness
- repair com telemetria e orçamento por origem
- cache de fitness para reuso
- manutenção de nogoods
- tradução de progresso para a camada operacional
- suporte a perfis de ilha, afetando a faixa efetiva do alpha

Hoje o problema também contém lógica operacional explícita de cancelamento e heartbeat para evitar execuções silenciosamente travadas.

## Modelo de ilhas

O solver usa IslandModelEngine para orquestrar ilhas de forma sincronizada.

Cada ilha encapsula:

- uma GeneticAlgorithmEngine própria
- seu tamanho de população
- seu replacement
- seu perfil de exploração

### Perfis de ilha ativos

Os perfis ativos são definidos por IslandProfile:

- Conservative
- Balanced
- Exploratory

Hoje os perfis controlam:

- faixa de alpha do GRASP
- mutation base rate
- mutation amplification
- mutation max rate

### Atribuição atual dos perfis

No wiring atual de RunGeneticAlgorithm:

- ilha 0 recebe Conservative
- ilha 1 recebe Exploratory
- ilhas 2+ recebem Balanced

O perfil é propagado para a GeneticAlgorithmEngine e desta para o ScheduleProblem.

Detalhe importante da implementação atual:

- se AG_ISLANDS = 1, a única ilha ativa é a ilha 0, portanto a execução roda somente com o perfil Conservative
- nesse cenário, não há ilha Balanced nem Exploratory participando da busca
- como consequência, a busca fica mais conservadora e a migração deixa de ter efeito prático

### Migração entre ilhas

O motor suporta duas políticas:

- BestIndividualsMigration
- ProfileAwareBestIndividualsMigration

A política padrão atual é profile-aware, controlada por config.

Na política profile-aware, o motor:

- define número de migrantes por perfil de origem
- pode usar matriz direcional por perfil
- faz fallback para round-robin se não houver destino direcional
- mede o delta de fitness causado na ilha receptora
- registra histórico consolidado por rodada de migração

Se houver apenas uma ilha, a migração é naturalmente inerte.

## Loop evolutivo principal

O caminho principal da evolução hoje é este:

1. seleção por torneio com fitness sharing
2. crossover principal fixo
3. mutação adaptativa
4. repair do descendente quando necessário
5. avaliação da população nova
6. análise de landscape
7. possível ativação de ALNS
8. replacement com niching
9. registro de métricas e telemetria

### Seleção ativa

O operador de seleção usado no fluxo principal é TournamentSelection com apoio de FitnessSharingCalculator e SharingFunction.

### Crossover ativo no fluxo principal

O crossover usado no fluxo principal hoje é ConflictGraphCrossoverOperator.

Importante: SinglePointCrossover e BlockPreservingCrossover existem e são registrados na camada de hiper-heurística para telemetria e aprendizado, mas não são o crossover principal do loop atual.

### Mutação ativa no fluxo principal

O operador principal de mutação é AdaptiveDiversityMutation, composto por:

- StructuredSwapMutation
- GeneSwapMutation
- ConflictGuidedMutation

O AdaptiveMutationController calcula a taxa de mutação em função de diversidade e entropia, respeitando os limites do perfil da ilha.

### Choque temporário de mutação e redução de pressão de seleção

O motor atual também possui mecanismos temporários para:

- mutation shock
- selection pressure reduction
- stagnation burst

Esses mecanismos são armados pelo processamento de landscape e pela política de estagnação consolidada no IslandModelEngine e na GeneticAlgorithmEngine.

Eles alteram temporariamente:

- mutation rate efetiva
- multiplicador de pressão de seleção
- tamanho efetivo do torneio
- elegibilidade e prioridade de ALNS em momentos de estagnação

## Avaliação da população

Após montar a nova geração, a engine inicia uma etapa explícita de evaluating_population.

Hoje essa etapa publica heartbeats estruturados para subestágios como:

- trajectory_signals_started
- trajectory_signals_completed
- generation_step_completed
- local_metrics_recording_started
- local_metrics_recorded
- landscape_evaluation_started
- landscape_evaluation_completed
- trigger_resolution_completed
- evolution_generation_completed

Detalhe importante de numeração:

- nos logs da GeneticAlgorithmEngine, o campo generation é zero-based dentro da ilha
- o campo local_generation é one-based e corresponde à contagem operacional humana da ilha
- nas métricas persistidas e nos resumos agregados, a geração aparece no formato consolidado esperado pela operação
- por isso, a quarta geração operacional pode aparecer no heartbeat da engine como generation = 3 e local_generation = 4

Isso é importante porque a avaliação não é um bloco opaco; o motor já expõe subetapas suficientes para diferenciar latência normal de travamento real.

### Avaliação paralela

RunGeneticAlgorithm escolhe entre:

- AsyncFitnessEvaluator
- PopulationFitnessEvaluator

A avaliação paralela só é ligada quando:

- a config permite parallel evaluation
- o tamanho da população ultrapassa o threshold configurado

Caso contrário, a avaliação permanece no caminho não paralelo.

## Landscape analysis e respostas adaptativas

O solver usa LandscapeEngine com estes componentes:

- LandscapeAnalyzer
- LandscapeDetector
- LandscapeResponseStrategy
- LandscapeMemory

O landscape atual influencia diretamente:

- mutation multiplier
- selection pressure multiplier
- ativação dinâmica de ALNS
- observação publicada em telemetria

O motor registra:

- landscape_state
- landscape_phenomenon
- landscape_observation

Essa observação é incorporada às métricas e usada para enriquecer o contexto operacional.

## Hiper-heurística

O solver possui uma camada real de aprendizado de operadores com:

- LearningHyperHeuristicController
- OperatorPerformanceTracker
- OperatorRewardCalculator
- EpsilonGreedySelector

Hoje essa camada registra e atualiza recompensa de operadores, incluindo operadores usados em mutação, crossover e ALNS.

Ela não transforma o fluxo principal em seleção dinâmica completa de todos os operadores do AG; no wiring atual, alguns componentes continuam fixos no loop principal e a hiper-heurística atua principalmente como camada de aprendizado e tracking.

## Repair atual

O repair principal é o GreedyRepairOperator.

### Estratégias ativas do repair

Hoje ele executa:

- priorização de repair targets
- relocação
- swap
- local rebuild
- ranking local com cache de fitness por assinatura
- integração com extensões de repair
- telemetria por passe
- abort por orçamento de tempo
- abort por falta de progresso
- abort por ausência de movimentos estruturais

### Targets de repair ativos

O repair prioriza uma mistura de:

- conflitos estruturais de professor e turma
- violações de bloco obrigatório
- targets adicionais vindos de extensões

Quando o CustomConstraintRepairExtension está habilitado, ele acrescenta lógica para violações de constraints customizadas.

### Limites operacionais do repair

O GreedyRepairOperator hoje usa:

- até 4 passes
- heartbeat de progresso por lote de genes inválidos
- keepalive heartbeat em loops caros
- deadline cooperativa quando recebe orçamento
- payload de progresso sincronizado para heartbeats intermediários

Essa camada foi reforçada para não ficar silenciosa em loops caros de relocação, swap, rebuild local e probing de fitness.

### Heartbeats do repair na evolução

No ScheduleProblem, o repair da evolução usa orçamento operacional próprio, com destaque para:

- heartbeat interval de 5 segundos para repair_runtime
- budget de 3000 ms para repair da evolução
- máximo de 2 passes sem progresso

Esse heartbeat de 5 segundos vale para repair da evolução. Ele não substitui o heartbeat operacional global da engine, que continua em 30 segundos para estágios gerais da evolução.

## ALNS/LNS

O solver tem implementação real de ALNS em AdaptiveLargeNeighborhoodSearch.

### Operadores configurados no ALNS

Destroy operators ativos:

- RandomDestroyOperator
- ConflictDestroyOperator
- ClusterDestroyOperator

Repair operators ativos:

- LNSRepairAdapter
- RegretInsertionOperator

Critério de aceitação ativo:

- StrictScoreImprovementAcceptance

### Como o ALNS é executado hoje

Na prática, o ALNS:

- recebe o melhor indivíduo corrente
- avalia o estado atual
- executa improve com contexto de landscape e trigger
- passa contexto cooperativo de timeout e heartbeat ao repair interno
- avalia o candidato uma única vez ao fim da melhoria
- decide aceitação pelo critério configurado
- atualiza replacement e telemetria se aceito
- registra destroy operator, repair operator, improvement e reward

### Timeout cooperativo do ALNS

O passo ALNS atual tem timeout explícito por etapa, com default de 15000 ms.

Esse timeout não é apenas externo. Hoje ele é propagado para dentro do repair por meio de:

- abort_if_timed_out
- progress_heartbeat
- limits com orçamento restante

Isso evita o problema de o ALNS entrar em improve e o repair interno ficar preso sem checkpoint cooperativo.

### O que acontece quando o ALNS estoura timeout

Se o passo ALNS excede o tempo permitido:

- a engine registra alns_timeout
- grava warning ga.alns.step_timeout
- considera o passo como não aceito
- continua a geração normalmente

Ou seja, timeout de ALNS hoje é um evento operacional recuperável, não uma falha fatal da execução.

## Replacement, elitismo e diversidade

O motor usa:

- TopEliteStrategy para preservar elites
- AdaptiveNichingReplacement para replacement
- PopulationStatistics para variância, diversidade e entropia
- HashDiversityCalculator
- PopulationEntropyCalculator
- GeneticDistance para diversidade e niching

## Critérios de término

O critério principal é VarianceBasedTerminationCriterion.

Hoje ele considera:

- número máximo de gerações
- target fitness
- gerações sem melhoria
- threshold de variância
- janela mínima antes de ativar corte por variância
- diversidade mínima
- entropia mínima

O motor ainda respeita cancelamento operacional durante a execução.

## Heartbeats, cache, banco e logs

O sistema atual observa o solver em três níveis:

- cache para UI em tempo quase real
- banco para histórico de execução e gerações
- logs estruturados para investigação profunda

### Frequências e sinais importantes

Na GeneticAlgorithmEngine, hoje existem:

- heartbeat operacional geral a cada 30 segundos
- warning de long_running_operation a cada 300 segundos
- verificação de cancelamento em intervalos curtos

No repair da evolução, existe keepalive específico de 5 segundos.

### CacheAndDbProgressReporter: regra importante

Nem todo heartbeat de evolução vira métrica no banco.

O comportamento real hoje é:

- todo progresso recebido é cacheado e toca o heartbeat da execução
- snapshots incompletos de geração ficam só no cache
- somente payloads completos viram ScheduleGenerationMetric no banco

Isso é intencional. Heartbeats operacionais intermediários não devem ser confundidos com métricas consolidadas de geração.

### Métricas gravadas no banco

Quando o snapshot está completo, o reporter persiste:

- generation
- best_fitness
- avg_fitness
- variance
- diversity
- entropy
- mutation_rate
- stagnation
- landscape_state
- operator_used e reward
- destroy e repair operator do ALNS
- alns_improvement
- landscape_phenomenon e observation

## Persistência final da solução

Ao fim da evolução, RunGeneticAlgorithm ainda executa repair final da melhor solução.

### Repair final

O fluxo final tenta até 3 ciclos de repair.

Se encontrar solução sem hard penalty e factível, encerra como resultado viável.

Se não conseguir zerar as violações, mantém o melhor candidato encontrado e sinaliza que o resultado é parcial.

### PersistBestSolutionService

Antes de gravar no banco, o serviço:

- valida ausência de conflitos estruturais por turma e professor
- remove as alocações antigas do horário
- persiste a nova solução em transação
- associa execution_id a cada alocação criada

Importante: resultado parcial ainda pode ser persistido, mas nunca com conflito estrutural direto detectado por essa validação final.

## O que está realmente ativo hoje no fluxo principal

Resumo objetivo do wiring principal:

- seleção: TournamentSelection com fitness sharing
- crossover principal: ConflictGraphCrossoverOperator
- mutação principal: AdaptiveDiversityMutation
- repair principal: GreedyRepairOperator
- ALNS: ativo, com destroy e repair adaptativos
- acceptance do ALNS: StrictScoreImprovementAcceptance
- landscape: ativo e influenciando ALNS, mutação e seleção
- hiper-heurística: ativa para tracking e rewards
- replacement: AdaptiveNichingReplacement
- elitismo: TopEliteStrategy
- término: VarianceBasedTerminationCriterion
- migração: profile-aware por padrão, configurável

## O que existe na base mas não é o operador principal do loop

Estes componentes existem e participam de partes do ecossistema, mas não são o operador principal fixado no loop evolutivo atual:

- SinglePointCrossover
- BlockPreservingCrossover
- operadores adicionais usados principalmente via registro hiper-heurístico ou infra de comparação

## Estado atual da observabilidade operacional

Hoje a base já consegue distinguir bem:

- execução saudável porém lenta
- operação longa mas com heartbeat contínuo
- timeout controlado de ALNS
- degradação da população inicial
- encerramento por cancelamento
- falha de execução real

Essa distinção existe porque o solver publica progresso suficiente por fase, estágio, subestágio e contexto de repair.

## Limites e riscos atuais

Os principais riscos ainda visíveis no código atual são:

- população inicial continua sendo fase cara e predominantemente serial
- evolução ainda pode passar vários minutos em building_offspring em cenários difíceis
- ALNS pode esgotar o timeout sem gerar candidato aceito, embora hoje isso seja tratado sem travar a execução
- a calibração entre perfis de ilha, landscape, mutation shock e selection pressure continua sensível a configuração

## Conclusão

O motor atual do solver é um AG híbrido operacionalmente maduro, com forte ênfase em:

- qualidade da população inicial
- observabilidade em tempo real
- resposta adaptativa a estagnação
- repair cooperativo com timeout e heartbeat
- integração explícita de constraints customizadas

Ele já possui mecanismos importantes de defesa contra travas silenciosas, principalmente em ALNS e repair. O comportamento real de produção hoje é o de um pipeline robusto, embora ainda custoso em latência na construção inicial e em certos trechos da evolução.
