# Arquitetura do Algoritmo Genético - Projeto Horário

## 📋 Sumário Executivo

O projeto implementa um **Algoritmo Genético Híbrido com Intensificação LNS (Large Neighborhood Search - ALNS)** para resolução do problema de **agendamento de aulas (Timetabling)**. A arquitetura combina:

- ✅ **População inicial via GRASP** (Greedy Randomized Adaptive Search Procedure)
- ✅ **Operadores genéticos adaptativos** (seleção, crossover, mutação)
- ✅ **ALNS com seleção adaptativa** de operadores destroy/repair
- ✅ **Modelo de ilhas** para paralelização
- ✅ **Avaliação incremental (Delta Fitness)** com regras de conflito
- ✅ **Landscape Analysis** para detecção de plateaus e vales
- ✅ **Hiper-heurística com recompensa de operadores**

---

## 📁 Estrutura de Diretórios

```
app/Modules/AG/
├── Application/                    # Camada de aplicação
│   ├── RunGeneticAlgorithm.php    # Orquestrador principal ⭐
│   ├── GeneticAlgorithmEngine.php # Engine do AG (evolução)
│   ├── PopulationFitnessEvaluator.php
│   └── Progress/
│
├── Domain/                         # Lógica de negócio (núcleo)
│   ├── Representation/
│   │   └── Entities/
│   │       ├── Cromossomo.php      # Indivíduo (solução)
│   │       └── Gene.php             # Gene (aula alocada)
│   │
│   ├── Contracts/
│   │   ├── GeneticProblem.php      # Interface do problema
│   │   └── ProgressReporterInterface.php
│   │
│   ├── Fitness/
│   │   ├── FitnessEvaluator.php    # Avaliador de fitness ⭐
│   │   ├── FitnessResult.php
│   │   ├── FitnessWeights.php      # Pesos das penalidades
│   │   ├── Delta/                   # Avaliação incremental
│   │   │   ├── DeltaFitnessEvaluator.php
│   │   │   └── Dependency/
│   │   │       └── RuleDependencyGraph.php
│   │   └── Incremental/
│   │
│   ├── Evolution/
│   │   └── IslandModel/
│   │       ├── IslandModelEngine.php # Paralelização com ilhas
│   │       ├── Island.php
│   │       └── BestIndividualsMigration.php
│   │
│   ├── Operators/
│   │   ├── Selection/
│   │   │   ├── TournamentSelection.php
│   │   │   └── FitnessSharing/
│   │   ├── Crossover/
│   │   │   ├── SinglePointCrossover.php
│   │   │   ├── BlockPreservingCrossover.php
│   │   │   └── ConflictGraphCrossoverOperator.php
│   │   ├── Mutation/
│   │   │   ├── AdaptiveDiversityMutation.php
│   │   │   ├── GeneSwapMutation.php
│   │   │   ├── StructuredSwapMutation.php
│   │   │   └── ConflictGuidedMutation.php
│   │   ├── Replacement/
│   │   │   └── AdaptiveNichingReplacement.php
│   │   ├── Adaptive/
│   │   │   ├── AdaptiveMutationController.php
│   │   │   ├── AdaptiveMutationOperator.php
│   │   │   └── AdaptiveOperatorSelector.php
│   │   └── Elitism/
│   │       └── TopEliteStrategy.php
│   │
│   ├── Intensification/LNS/
│   │   ├── ALNS/
│   │   │   ├── AdaptiveLargeNeighborhoodSearch.php ⭐
│   │   │   ├── OperatorScoreManager.php
│   │   │   ├── OperatorSelectionStrategy.php
│   │   │   ├── RouletteWheelSelector.php
│   │   │   └── Acceptance/
│   │   │       └── StrictScoreImprovementAcceptance.php
│   │   ├── Destroy/
│   │   │   ├── RandomDestroyOperator.php
│   │   │   ├── ConflictDestroyOperator.php
│   │   │   └── ClusterDestroyOperator.php
│   │   ├── Repair/
│   │   │   ├── GreedyRebuildOperator.php
│   │   │   ├── RegretInsertionOperator.php
│   │   │   └── LNSRepairAdapter.php
│   │   └── Conflict/
│   │       └── ConflictDetector.php
│   │
│   ├── HyperHeuristic/
│   │   ├── LearningHyperHeuristicController.php
│   │   ├── OperatorPerformanceTracker.php
│   │   ├── OperatorRewardCalculator.php
│   │   ├── OperatorScore.php
│   │   └── Strategies/
│   │       └── EpsilonGreedySelector.php
│   │
│   ├── Landscape/
│   │   ├── LandscapeEngine.php
│   │   ├── LandscapeAnalyzer.php
│   │   ├── LandscapeDetector.php
│   │   └── LandscapeResponseStrategy.php
│   │
│   ├── Termination/
│   │   └── VarianceBasedTerminationCriterion.php
│   │
│   ├── Metrics/
│   │   ├── GeneticDistance.php
│   │   ├── HashDiversityCalculator.php
│   │   ├── PopulationStatistics.php
│   │   └── MetricsRecorder.php
│   │
│   ├── Diversity/
│   ├── Evaluation/
│   └── ConflictGraph/
│
├── Infrastructure/
│   ├── Metrics/
│   │   └── ExecutionMetricsRecorder.php
│   ├── Parallel/
│   │   └── AsyncFitnessEvaluator.php
│   ├── Logging/
│   └── Progress/
│
└── Support/
    ├── DTO/
    │   └── GeneticAlgorithmConfigDTO.php ⭐
    └── Exceptions/

app/Modules/Horarios/
└── Domain/
    └── Problem/
        └── ScheduleProblem.php ⭐ (Implementa GeneticProblem)
```

---

## 🎯 Classes Principais

### 1. **RunGeneticAlgorithm.php** - Orquestrador

**Responsabilidade**: Inicializar e executar todo o AG

```php
public function execute(Horario $horario): array
```

**Fluxo**:
1. Carrega configurações do `GeneticAlgorithmConfigDTO`
2. Constrói dados de agendamento (`ScheduleDataBuilder`)
3. Inicializa regras de fitness (hard/soft)
4. Cria 2 ilhas de evolução (modelo de ilhas)
5. Executa `IslandModelEngine->run()`
6. Aplica repair final à melhor solução

---

### 2. **Cromossomo.php** - Indivíduo (Solução)

**Representa**: Uma solução completa do agendamento

**Estrutura**:
```php
private array $genes;  // Gene[] (aulas alocadas)
private ConflictGraph $conflictGraph;  // Grafo de conflitos
private float $fitness = 0.0;
private string $signature = '';  // Hash estrutural para cache
```

**Índices de otimização**:
- `$professorIndex`: professor ID → índices de genes
- `$turmaIndex`: turma ID → índices de genes
- `$professorPeriodoIndex`: professor → dia → período → genes
- `$aulaSlotsIndex`: aula → slots alocados
- `$turmaJanelas`: turma → janelas (gaps entre aulas)
- `$professorJanelas`: professor → janelas
- `$aulaBlocos`: blocos de aulas (grupos consecutivos)

**Métodos críticos**:
- `rebuildIndexes()`: reconstrói índices após mutação
- `rebuildConflictGraph()`: atualiza grafo de conflitos
- `genes()`: retorna genes
- `fitness()`: retorna score lexicográfico

---

### 3. **ScheduleProblem.php** - Problema Específico ⭐

**Implementa**: `GeneticProblem`

**Responsabilidades**:
- ✅ Gerar população inicial via **GRASP**
- ✅ Reparar indivíduos inviáveis
- ✅ Finalizar soluções

#### 3.1 População Inicial com GRASP

```php
public function createIndividual(): Cromossomo
```

**Processo**:
1. **Construir fila de alocação** (`buildPlacementQueue`)
   - Ordena aulas por dificuldade (restrições)

2. **Tentar reutilizar seed aceito**
   - Se `$lastAcceptedInitialSeed` válido, perturbar e reparar

3. **Construção GRASP** (loop com limite adaptativo)
   - Para cada aula na fila:
     a. **RCL (Restricted Candidate List)**: Cria lista com `α` melhores slots
     b. **Randomização**: Seleciona aleatoriamente um slot de RCL
     c. **Construção gulosa**: Aloca a aula ao slot escolhido
   - Parâmetro `α` ∈ [0.15, 0.45] (controla criticidade)

4. **Quality Gate**: Rejeita se hard_penalty > limiar
5. **Repair**: Tenta reparar inviáitos via `GreedyRepairOperator`

**Constantes GRASP**:
```php
MAX_BUILD_ATTEMPTS = 12;
MIN_BUILD_ATTEMPTS = 4;
RCL_MIN_SIZE = 3;
RCL_ALPHA_MIN = 0.15;
RCL_ALPHA_MAX = 0.45;
```

---

### 4. **FitnessEvaluator.php** - Avaliação ⭐

**Calcula**: Score baseado em penalidades hard/soft

#### 4.1 Cálculo de Penalidade

```php
public function evaluate(Cromossomo $cromossomo): FitnessResult
```

**Processo**:
1. **Verifica cache** por assinatura estrutural
2. Para cada regra de fitness:
   - `$penalty = $rule->evaluate($context)`
   - Se `$rule->isHard()`: `$hardPenalty += penalty * weight`
   - Senão: `$softPenalty += penalty * weight`
3. Calcula score lexicográfico

#### 4.2 Score Lexicográfico

```php
// Se houver conflito hard
if ($hardPenalty > 0.0) {
    return min(49.999, 49.999 / (1.0 + $hardPenalty + $softPenalty * 0.10));
}

// Se for viável
return 50.0 + (50.0 / (1.0 + $softPenalty));
```

**Interpretação**:
- `[0 → 50)`: Inviáveis (com hard violations)
- `[50 → 100]`: Viáveis (sem hard violations)
- Qualquer viável domina qualquer inviável

#### 4.3 Regras de Fitness

**Hard Rules** (impedem inviabilidade):
- `TeacherConflictRule`: Professor em 2 lugares ao mesmo tempo
- `ClassConflictRule`: Turma em 2 lugares ao mesmo tempo
- `WorkloadExceededRule`: Professor com mais aulas que limite
- `MandatoryBlockViolationRule`: Blocos obrigatórios não respeitados

**Soft Rules** (diminuem qualidade):
- `WindowPenaltyRule`: Janelas (gaps) entre aulas
- `DistributionRule`: Distribuição irregular de aulas
- `MaxLessonsPerDayRule`: Mais aulas/dia que permitido
- `ConsecutiveLessonRule`: Falta de aulas consecutivas
- `PreferredTimeRule`: Aulas fora de horários preferidos

#### 4.4 Avaliação Incremental (Delta Fitness)

```php
public function evaluateDelta(
    Cromossomo $cromossomo,
    AffectedRegion $region,
    FitnessResult $previous
): FitnessResult
```

**Otimização**:
- Usa `RuleDependencyGraph` para determinar regras afetadas
- Reavalia **apenas as regras impactadas**
- Reduz custo computacional em ~70-80%

---

### 5. **GeneticAlgorithmEngine.php** - Engine de Evolução ⭐

**Responsabilidade**: Loop principal de evolução com operadores

```php
public function run(int $populationSize): Cromossomo
```

**Fluxo de Geração**:
```
while (!termination.shouldTerminate()) {
    1. SELEÇÃO: Tournament selection com fitness sharing
    2. CRUZAMENTO: Crossover (type: ConflictGraphCrossover)
    3. MUTAÇÃO: Adaptive diversity mutation
    4. REPARAÇÃO: Repair de inviáveis
    5. AVALIAÇÃO: Delta fitness evaluation
    6. LNS (ALNS): A cada lnsFrequency gerações
    7. LANDSCAPE: Análise de landscape a cada 10 ger.
    8. SUBSTITUIÇÃO: Elitismo + Niching replacement
    9. HIPERHEURÍSTICA: Atualiza reward de operadores
}
```

#### 5.1 Operadores Genéticos

**Seleção**:
- Tournament selection (tamanho 3)
- Com fitness sharing para manter diversidade

**Crossover**:
- `ConflictGraphCrossoverOperator`: Preserva estrutura de conflitos
- Combina genes de pais respeitando restrições

**Mutação**:
- `AdaptiveDiversityMutation`: Escolhe dinamicamente:
  - `StructuredSwapMutation`: Swap preservando estrutura
  - `GeneSwapMutation`: Swap aleatório
  - `ConflictGuidedMutation`: Baseada em conflitos

**Elitismo**:
- `TopEliteStrategy`: Preserva top `elite_count` indivíduos

**Replacement**:
- `AdaptiveNichingReplacement`: Mantém diversidade via niching

#### 5.2 Intensificação com ALNS

Executado a cada `lnsFrequency` gerações (padrão: ~5-10 ger.)

```php
if ($generation % $lnsFrequency === 0) {
    $improved = $this->lns->improve($individual, [
        'landscape_observation' => [...],
        'trigger' => [...],
    ]);
}
```

---

### 6. **AdaptiveLargeNeighborhoodSearch.php** - ALNS ⭐

**Responsabilidade**: Intensificação local com operadores adaptativos

```php
public function improve(Cromossomo $solution): Cromossomo
```

**Fluxo**:
1. **Seleção de Operadores**:
   - `destroy_op = selector->select(destroyOperators, scores)`
   - `repair_op = selector->select(repairOperators, scores)`

2. **Aplicação de Intensidade**:
   - Calcula `intensity` ∈ [0.35, 1.0] baseado em:
     - `landscape_pressure`: Há pressão de paisagem?
     - `basin_lock`: Está preso em bacia de atração?
     - `phenomenon`: deep_valley, local_minimum, etc.
   - Configura operadores com intensidade

3. **Destroy** (remoção):
   - Operador destroy remove ~(10% a 55%) genes
   - Cria `PartialSolution(assigned, unassigned)`

4. **Repair** (reinserção):
   - Operador repair reinsere genes unassigned
   - Tenta melhorar alocação iterativamente

5. **Recompensa** de Operadores:
   - `improvement = candidate_fitness - base_fitness`
   - Se `improvement > 0`: Recompensa destroy/repair
   - `score += reward(improvement)`

#### 6.1 Operadores Destroy

| Operador | Descrição | Intensidade |
|----------|-----------|------------|
| **RandomDestroyOperator** | Remove genes aleatoriamente | `destroyRatio ∈ [0.10, 0.55]` |
| **ConflictDestroyOperator** | Remove genes conflitantes | Maior número = pior fitness |
| **ClusterDestroyOperator** | Remove clusters de aulas | Baseado em grupos espaciais |

#### 6.2 Operadores Repair

| Operador | Descrição |
|----------|-----------|
| **GreedyRebuildOperator** | Reinserção gulosa simples |
| **RegretInsertionOperator** | Insertion com critério de "regret" (arrependimento) |
| **LNSRepairAdapter** | Adapta GreedyRepairOperator do AG |

**RegretInsertion** (avançado):
- Para cada gene não alocado:
  - Calcula custo de inserção em todos os slots
  - Computa "regret" = (2º melhor custo - melhor custo)
  - Insere o gene com maior regret
- Mantém qualidade alta durante reinserção

#### 6.3 Seleção Adaptativa (Roulette Wheel)

```php
score[operator] = (uses, improvements, performance)
probability[operator] ∝ score[operator]
selector.select() → operador escolhido via probabilidade
```

---

## 🔧 Configurações (GeneticAlgorithmConfigDTO)

### Parâmetros do GA

```php
public int $tamanhoPopulacao;           // Tamanho da população / ilha
public int $numeroGeracoes;             // Máximo de gerações
public float $taxaMutacao;              // Taxa de mutação base
public float $taxaCrossover;            // Taxa de crossover
public float $taxaElitismo;             // % de elite mantida
public float $taxaMutacaoMin;           // Taxa mínima (adaptativa)
public float $taxaMutacaoMax;           // Taxa máxima (adaptativa)
public int $limiteEstagnacao;           // Ger. sem melhora → parada
public float $targetFitness;            // Fitness alvo (parada)
public int $maxGenerationsWithoutImprovement;  // Limite sem melhora
```

### Configurações do Horário

```php
public int $aulasPorDia;                // Max aulas/dia
public int $diasSemana;                 // Dias da semana
public int $duracaoAulaMinutos;         // Minutos/aula
public bool $permitirJanelas;           // Permite gaps?
public bool $agruparDisciplinas;        // Agrupar disciplinas?
```

### Arquivo de Configuração (config/ag.php)

```php
'parallel_evaluation' => true,
'max_workers' => 8,
'termination_variance_threshold' => 0.0005,
'termination_variance_window' => 8,
'termination_min_diversity' => 0.08,
'termination_min_entropy' => 0.10,
'search_response_activation' => [
    'enable_temporary_intensive_alns' => false,
    'enable_temporary_mutation_shock' => false,
    'enable_temporary_selection_pressure_reduction' => false,
]
```

---

## 📊 Fluxo Geral de Execução

```
┌──────────────────────────────────────────────────────────────┐
│ RunGeneticAlgorithm::execute(Horario $horario)              │
└─────────────────────────────┬────────────────────────────────┘
                              │
                    ┌─────────▼──────────┐
                    │ Carrega Config     │
                    │ BuildScheduleData  │
                    │ FitnessEvaluator   │
                    └────────┬───────────┘
                             │
              ┌──────────────▼──────────────┐
              │ IslandModelEngine::run()    │ (2 ilhas)
              └──────────┬───────────────────┘
                         │
        ┌────────────────┴────────────────┐
        │                                 │
      Island 1                          Island 2
        │                                 │
        ├─► GeneticAlgorithmEngine::run() ├─► GeneticAlgorithmEngine::run()
        │   (populationSize = 50)         │   (populationSize = 50)
        │                                 │
        │   ┌─────────────────────┐       │   ┌─────────────────────┐
        ├──►│ initializePopulation│       ├──►│ initializePopulation│
        │   └────────────┬────────┘       │   └────────────┬────────┘
        │                │                │                │
        │   ┌───────────▼──────────┐      │   ┌───────────▼──────────┐
        │   │ Para cada individuo: │      │   │ Para cada individuo: │
        │   │ ScheduleProblem::    │      │   │ ScheduleProblem::    │
        │   │ createIndividual()   │      │   │ createIndividual()   │
        │   └───────────┬──────────┘      │   └───────────┬──────────┘
        │               │                 │               │
        │   ┌──────────▼────────────────────────────────────────────┐
        │   │ GRASP Construction:                                  │
        │   │ - Constrói fila de aulas                             │
        │   │ - Para cada aula:                                    │
        │   │   * Cria RCL (α = rand[0.15, 0.45])                 │
        │   │   * Seleciona slot aleatório de RCL                 │
        │   │   * Aloca aula                                       │
        │   │ - Se inviável: Tenta repair GreedyRepair            │
        │   │ - Quality gate: rejeita se muito pior               │
        │   └──────────┬──────────────────────────────────────────┘
        │              │
        │   ┌─────────▼──────────────────────┐
        │   │ for generation in range(gens): │
        │   └──────────┬────────────────────┘
        │              │
        │   ┌─────────▼────────────────┐
        │   │ 1. SELEÇÃO              │
        │   │    Tournament (size=3)   │
        │   │    + Fitness Sharing    │
        │   └─────────┬────────────────┘
        │             │
        │   ┌─────────▼────────────────────┐
        │   │ 2. CROSSOVER                │
        │   │    ConflictGraphCrossover   │
        │   └─────────┬────────────────────┘
        │             │
        │   ┌─────────▼────────────────────┐
        │   │ 3. MUTAÇÃO                  │
        │   │    AdaptiveDiversityMut.    │
        │   │    (Chuta qual tipo usar)   │
        │   └─────────┬────────────────────┘
        │             │
        │   ┌─────────▼────────────────────┐
        │   │ 4. REPAIR                   │
        │   │    GreedyRepairOperator     │
        │   │    (Se inviável)            │
        │   └─────────┬────────────────────┘
        │             │
        │   ┌─────────▼────────────────────┐
        │   │ 5. AVALIAÇÃO                │
        │   │    Delta Fitness (cache)    │
        │   │    RuleDependencyGraph      │
        │   └─────────┬────────────────────┘
        │             │
        │   ┌─────────▼─────────────────────────┐
        │   │ 6. LNS (ALNS)                    │
        │   │    A cada lnsFrequency gen.      │
        │   │    - Seleciona destroy_op        │
        │   │    - Seleciona repair_op         │
        │   │    - Aplica intensidade           │
        │   │    - Destrói ~20-30% genes      │
        │   │    - Repara com RegretInsertion  │
        │   │    - Recompensa operadores      │
        │   └─────────┬──────────────────────────┘
        │             │
        │   ┌─────────▼────────────────────────────┐
        │   │ 7. LANDSCAPE (a cada 10 gen.)      │
        │   │    - Detecta tipo de paisagem       │
        │   │    - Vales, plateaus, etc.         │
        │   │    - Modula intensidade do ALNS    │
        │   └─────────┬───────────────────────────┘
        │             │
        │   ┌─────────▼──────────────────────┐
        │   │ 8. SUBSTITUIÇÃO                │
        │   │    - Elitismo (preserva top)   │
        │   │    - Niching replacement       │
        │   │    - Mantém diversidade        │
        │   └─────────┬──────────────────────┘
        │             │
        │   ┌─────────▼────────────────────────────────┐
        │   │ 9. HIPERHEURÍSTICA                      │
        │   │    - Atualiza score de operadores       │
        │   │    - Calcula reward de mutações         │
        │   │    - Seleção ε-greedy                   │
        │   └─────────┬───────────────────────────────┘
        │             │
        │   ┌─────────▼────────────────────────┐
        │   │ 10. MIGRAÇÃO (a cada 25 gen.)   │
        │   │     - Best individuals arqu.    │
        │   │     - Compartilha entre ilhas   │
        │   └─────────┬──────────────────────┘
        │             │
        │   ┌─────────▼──────────────────┐
        │   │ while !termination.should() │
        │   │ - Min variance              │
        │   │ - Max diversity collapse    │
        │   │ - Target fitness            │
        │   │ - Max gerações              │
        │   └─────────┬──────────────────┘
        │             │
        └─────────────┴──────────────────└─────────────────────────┘
                      │
              ┌───────▼────────┐
              │ Retorna melhor  │
              │ finalizado com  │
              │ repair final    │
              └────────────────┘
```

---

## 🎲 Como a População Inicial É Criada (GRASP)

### Algoritmo GRASP em Detalhes

```php
/**
 * GRASP - Greedy Randomized Adaptive Search Procedure
 *
 * Objetivo: Gerar solução inicial viável rapidamente
 * com aleatoriedade controlada
 */

public function createIndividual(): Cromossomo
{
    // 1. Construir fila de alocação (ordenada por dificuldade)
    $queue = $this->buildPlacementQueue();

    // 2. Limite adaptativo de tentativas
    $attemptLimit = $this->resolveAdaptiveBuildAttemptLimit();

    // 3. Tentar reutilizar seed anterior se disponível
    if ($this->lastAcceptedInitialSeed !== null) {
        $candidate = $this->tryCreateIndividualFromAcceptedSeed($queue);
        if ($candidate !== null) {
            return $candidate;  // Sucesso!
        }
    }

    // 4. Loop GRASP
    for ($attempt = 1; $attempt <= $attemptLimit; $attempt++) {
        $assignedGenes = [];
        $unassignedQueue = $queue;

        // 5. Para cada aula na fila
        while (!empty($unassignedQueue)) {
            $lesson = array_shift($unassignedQueue);

            // 6. Construã RCL (Restricted Candidate List)
            $alpha = rand(RCL_ALPHA_MIN, RCL_ALPHA_MAX);  // [0.15, 0.45]
            $candidateSlots = $this->findViableSlots($lesson);

            if (empty($candidateSlots)) {
                // Fail-fast: aula não tem slot viável
                $this->initialPopulationCounters['fail_fast']++;
                break;  // Falha dessa tentativa
            }

            // 7. Criar RCL com os α-melhores slots
            $rcl = $this->createRestrictedCandidateList(
                $candidateSlots,
                $alpha,
                RCL_MIN_SIZE
            );

            // 8. Selecionar aleatoriamente de RCL
            $chosenSlot = $rcl[array_rand($rcl)];

            // 9. Alocar aula ao slot
            $gene = new Gene($lesson, $chosenSlot->day, $chosenSlot->period);
            $assignedGenes[] = $gene;

            // 10. Atualizar índices de ocupação
            $this->updateOccupancy($assignedGenes);
        }

        if (empty($unassignedQueue)) {
            // Sucessoem construir todos!
            $cromossomo = new Cromossomo($assignedGenes);

            // 11. Quality Gate: filtrar soluções ruins
            if ($this->passesQualityGate($cromossomo)) {
                $this->lastAcceptedInitialSeed = $cromossomo;
                return $cromossomo;
            }

            $this->initialPopulationCounters['quality_gate_rejected']++;
        }
    }

    // 12. Se esgotou tentativas, tenta repair
    $bestRejected = /* melhor até agora */;
    if ($bestRejected !== null) {
        $repaired = $this->repairOperator->repair($bestRejected);
        return $repaired;
    }

    // Falha total
    throw new Exception('Não conseguiu gerar indivíduo viável');
}
```

**Parâmetros Adaptativos**:
- `α` ∈ [0.15, 0.45]: Controla aleatoriedade
  - α = 0.15 → Mais guloso (heurístico)
  - α = 0.45 → Mais aleatório (diverso)
- `RCL_MIN_SIZE` = 3: Garante pelo menos 3 opções
- `MAX_BUILD_ATTEMPTS` = 12: Máximo de tentativas

---

## 🔄 Algoritmo ALNS e Seus Operadores

### Fluxo ALNS Dentro da Evolução

```
Geração N
├─ Se N % lnsFrequency == 0:
│   └─► ALNS::improve(individual)
│        ├─ 1. Seleção de Operadores (Roulette Wheel)
│        │ ├─ Destroy op = selector.select(destroyOps, scores)
│        │ └─ Repair op = selector.select(repairOps, scores)
│        │
│        ├─ 2. Intensidade
│        │ └─ intensity = resolveIntensityProfile(solution)
│        │    ├─ Se landscape_pressure: intensity += 0.15
│        │    ├─ Se basin_of_attraction_lock: intensity += 0.20
│        │    └─ Se phenomenon == 'deep_valley': intensity += 0.18
│        │
│        ├─ 3. Destroy
│        │ └─ partial = destroy.destroy(solution)
│        │    ├─ RandomDestroy: Remove ~20-30% genes
│        │    ├─ ConflictDestroy: Remove conflitantes
│        │    └─ ClusterDestroy: Remove clusters
│        │
│        ├─ 4. Repair
│        │ └─ candidate = repair.repair(partial)
│        │    ├─ GreedyRepair: Reinserção gulosa
│        │    └─ RegretInsertion: Critério de arrependimento
│        │
│        ├─ 5. Avaliação
│        │ └─ improvement = fitness(candidate) - fitness(solution)
│        │
│        ├─ 6. Recompensa
│        │ └─ scores.reward(destroy, repair, improvement)
│        │    ├─ Se improvement > 0: σ+ (sucesso considerável)
│        │    ├─ Se 0 > improvement > -threshold: σ+ (sucesso)
│        │    ├─ Se improvement baixa diversidade: σ++ (muito bom)
│        │    └─ Senão: σ- (falha)
│        │
│        └─ Retorna: candidate (melhor se improvement > 0)
└─ Continua evolução...
```

### Operadores Destroy

#### 1. RandomDestroyOperator

```php
/*
 * Remove genes aleatoriamente
 * - Simples e rápido
 * - Explora espacialmente o vizinhança
 */

public function destroy(Cromossomo $solution): PartialSolution
{
    $genes = $solution->genes();

    // Calcular quantos removerwaverer
    $intensity = $this->destroyRatio;  // 0.10 a 0.55
    $removeCount = max(1, (int)floor(count($genes) * $intensity));

    // Selecionar aleatoriamente
    $indexes = array_rand($genes, $removeCount);

    // Separar assigned/unassigned
    $partial = new PartialSolution($assigned, $unassigned);

    return $partial;
}

/*
 * configureDestroyIntensity(intensity)
 * - intensity = 0.0 → destroyRatio = 0.10 (mínimo)
 * - intensity = 1.0 → destroyRatio = 0.45 (máximo)
 */
```

#### 2. ConflictDestroyOperator

```php
/*
 * Remove genes que causam conflitos
 * - Foca em regiões problemáticas
 * - Mira tentar resolver hot spots
 */

public function destroy(Cromossomo $solution): PartialSolution
{
    $conflictGraph = $solution->conflictGraph();

    // Encontrar genes mais conflitantes
    $conflicted = $conflictGraph->getTopConflictedGenes(
        count: (int)(count($solution->genes()) * $intensity * 0.3)
    );

    foreach ($conflicted as $gene) {
        $unassigned[] = $gene;
    }

    return new PartialSolution($assigned, $unassigned);
}
```

#### 3. ClusterDestroyOperator

```php
/*
 * Remove clusters/blocos de aulas
 * - Mantém estrutura de grupos
 * - Permite reestruturação de blocos
 */

public function destroy(Cromossomo $solution): PartialSolution
{
    $clusters = $solution->identifyClusters();  // Grupos de aulas

    $clustersToRemove = (int)ceil(
        count($clusters) * $intensity * 0.25
    );

    $selectedClusters = array_rand($clusters, $clustersToRemove);

    foreach ($selectedClusters as $clusterId) {
        foreach ($clusters[$clusterId] as $gene) {
            $unassigned[] = $gene;
        }
    }

    return new PartialSolution($assigned, $unassigned);
}
```

### Operadores Repair

#### 1. GreedyRebuildOperator

```php
/*
 * Reinserção gulosa simples
 * - Para cada gene unassigned
 * - Encontra melhor slot disponível
 * - Insere lá
 */

public function repair(PartialSolution $partial): Cromossomo
{
    $genes = $partial->assigned();  // Já alocados

    foreach ($partial->unassigned() as $gene) {
        // Encontrar o melhor slot para este gene
        $bestSlot = null;
        $bestCost = INF;

        foreach ($this->availableSlots($gene) as $slot) {
            $cost = $this->calculateCost($gene, $slot, $genes);

            if ($cost < $bestCost) {
                $bestCost = $cost;
                $bestSlot = $slot;
            }
        }

        if ($bestSlot !== null) {
            $genes[] = $gene->withSlot($bestSlot);
        }
    }

    return new Cromossomo($genes);
}
```

#### 2. RegretInsertionOperator (Avançado)

```php
/*
 * Insertion com critério de "regret"
 * - Maior "regret" = maior perda se não alocar aqui agora
 * - Prioriza genes mais críticos
 *
 * Regret = 2º_melhor_custo - melhor_custo
 */

public function repair(PartialSolution $partial): Cromossomo
{
    $genes = $partial->assigned();
    $unassigned = $partial->unassigned();

    while (!empty($unassigned)) {
        $bestRegret = -INF;
        $bestGeneIndex = null;
        $bestGene = null;

        // Para cada gene não alocado, calcular regret
        foreach ($unassigned as $index => $gene) {

            // Encontrar 2 melhores slots
            [$bestCost, $secondCost, $bestCandidate] =
                $this->evaluateInsertionOptions(
                    $gene,
                    $genes  // Genes já alocados
                );

            // Regret = ganho de deixar para depois
            $regret = $secondCost - $bestCost;

            if ($regret > $bestRegret) {
                $bestRegret = $regret;
                $bestGeneIndex = $index;
                $bestGene = $bestCandidate;
            }
        }

        // Alocar o gene com maior regret
        if ($bestGene !== null) {
            $genes[] = $bestGene;
            unset($unassigned[$bestGeneIndex]);
        } else {
            break;
        }
    }

    return new Cromossomo(array_values($genes));
}
```

**Regret em Detalhe**:
```
Gene: Aula de Cálculo (turma 1A, professor João)

Slots disponíveis:
1. Segunda, 08:00 → custo = 2 (poucos conflitos)
2. Segunda, 10:00 → custo = 5
3. Quarta, 08:00  → custo = 3
4. Sexta, 14:00   → custo = 6

Ordenado:
1. Slot 1 → custo = 2 (MELHOR)
2. Slot 3 → custo = 3 (2º melhor)
3. Slot 2 → custo = 5
4. Slot 4 → custo = 6

Regret = 3 - 2 = 1

Interpretação:
- Se alocar agora no Slot 1: economia de 1 ponto
- Se deixar para depois: pode perder essa oportunidade
- Então priorizar este gene tem valor de 1
```

---

## 🎯 Cálculo de Penalidade (Hard/Soft Penalty)

### Estrutura Geral

```php
$hardPenalty = 0.0;
$softPenalty = 0.0;

foreach ($rules as $rule) {
    $penalty = $rule->evaluate($context);
    $weight = $weights->get($rule::class);
    $weighted = $penalty * $weight;

    if ($rule->isHard()) {
        $hardPenalty += $weighted;
    } else {
        $softPenalty += $weighted;
    }
}

$score = computeLexicographicScore($hardPenalty, $softPenalty);
```

### Hard Penalties (Inviabilidade)

| Regra | Descrição | Cálculo |
|-------|-----------|---------|
| **TeacherConflict** | Professor em >= 2 slots simultaneamente | Σ conflitos |
| **ClassConflict** | Turma em >= 2 slots simultaneamente | Σ conflitos |
| **WorkloadExceeded** | Carga de trabalho > limite | Σ (actual - limite) |
| **MandatoryBlockViolation** | Blocos obrigatórios não alocados | Σ de violações |

**Peso típico**: 10.0 (moltiplicador)

### Soft Penalties (Qualidade)

| Regra | Descrição | Cálculo |
|-------|-----------|---------|
| **WindowPenalty** | Janelas (gaps) entre aulas | Σ duração/gaps |
| **Distribution** | Distribuição irregular | Σ (desvio padrão) |
| **MaxLessonsPerDay** | Mais aulas/dia que permitido | Σ (excess) |
| **ConsecutiveLesson** | Falta de aulas consecutivas | Σ inversão |
| **PreferredTime** | Aula fora de horário preferido | Σ de penalidades |

**Peso típico**: 0.5 a 2.0 (multiplicador)

### Score Lexicográfico

```php
private function computeLexicographicScore(
    float $hardPenalty,
    float $softPenalty
): float
{
    /*
     * Objetivo: Garantir hierarquia viabilidade > qualidade
     *
     * - Todas inviáveis ficam em [0, 50)
     * - Todas viáveis ficam em [50, 100]
     * - Qualquer viável domina qualquer inviável
     */

    if ($hardPenalty > 0.0) {
        // INVIÁVEL: faixa [0, 50)
        $hardComponent = $hardPenalty;
        $softComponent = $softPenalty * 0.10;  // 90% ignorado

        $score = 49.999 / (1.0 + $hardComponent + $softComponent);

        return min(49.999, $score);

        /*
         * Exemplos:
         * hardPenalty = 1.0, softPenalty = 10.0
         *   → score = 49.999 / (1.0 + 1.0 + 1.0) = 16.67
         *
         * hardPenalty = 0.1, softPenalty = 50.0
         *   → score = 49.999 / (1.0 + 0.1 + 5.0) = 8.33
         *
         * Nota: Hard domina a fórmula pois softComponent é 90% ignorado
         */
    }

    // VIÁVEL: faixa [50, 100]
    $score = 50.0 + (50.0 / (1.0 + max(0.0, $softPenalty)));

    return $score;

    /*
     * Exemplos:
     * softPenalty = 0.0
     *   → score = 50.0 + 50.0 = 100.0 (ótimo)
     *
     * softPenalty = 1.0
     *   → score = 50.0 + 25.0 = 75.0
     *
     * softPenalty = 10.0
     *   → score = 50.0 + 4.55 = 54.55
     */
}
```

### Delta Fitness Evaluation (Otimização)

```php
/*
 * Problema: Recalcular todas as 9 regras para cada fitness
 * é custoso (complexidade O(n))
 *
 * Solução: Usar Delta Fitness com Rule Dependency Graph
 * - Identifica quais regras sofrem com a mutação
 * - Reavalia apenas essas regras
 * - Reduz custo em ~70-80%
 */

public function evaluateDelta(
    Cromossomo $cromossomo,
    AffectedRegion $region,  // Genes alterados
    FitnessResult $previous
): FitnessResult
{
    // 1. Determinar regras afetadas pelo change em $region
    $affectedRuleClasses = $this->dependencyGraph
        ->affectedRules($region);

    // 2. Executar apenas essas regras
    $affectedRules = [];
    foreach ($this->rules as $rule) {
        if (in_array($rule::class, $affectedRuleClasses)) {
            $affectedRules[] = $rule;
        }
    }

    // 3. Calcular novo hard/soft penalty apenas para affected
    $deltaHard = 0.0;
    $deltaSoft = 0.0;

    foreach ($affectedRules as $rule) {
        $newPenalty = $rule->evaluate($context);
        $oldPenalty = $previous->penaltyByRule[$rule::class] ?? 0.0;
        $delta = $newPenalty - $oldPenalty;

        if ($rule->isHard()) {
            $deltaHard += $delta * $weight;
        } else {
            $deltaSoft += $delta * $weight;
        }
    }

    // 4. Construir novo resultado baseado em anterior + delta
    $newHardPenalty = $previous->hardPenalty + $deltaHard;
    $newSoftPenalty = $previous->softPenalty + $deltaSoft;
    $newScore = $this->computeLexicographicScore(
        $newHardPenalty,
        $newSoftPenalty
    );

    return new FitnessResult(
        score: $newScore,
        hardPenalty: $newHardPenalty,
        softPenalty: $newSoftPenalty
    );
}
```

---

## ⚙️ Configurações do AG (Parâmetros)

### Arquivo de Configuração Padrão

```php
// config/ag.php

return [
    // ═══════════════════════════════════════════════════
    // AVALIAÇÃO PARALELA
    // ═══════════════════════════════════════════════════
    'parallel_evaluation' => true,
    'max_workers' => env('AG_MAX_WORKERS', 8),

    // ═══════════════════════════════════════════════════
    // CRITÉRIOS DE PARADA
    // ═══════════════════════════════════════════════════
    'termination_variance_threshold' => 0.0005,
        // Parada se variância fitness < 0.0005 por 8 ger.

    'termination_variance_window' => 8,
        // Janela de gerações para calcular variância

    'termination_min_generations_before_variance' => 20,
        // Esperar mínimo 20 ger. antes de verificar variância

    'termination_min_diversity' => 0.08,
        // Diversidade mínima antes de parada

    'termination_min_entropy' => 0.10,
        // Entropia mínima da população

    // ═══════════════════════════════════════════════════
    // ATIVAÇÃO TEMPORÁRIA (Search Response)
    // ═══════════════════════════════════════════════════
    'search_response_activation' => [
        'enable_temporary_intensive_alns' => false,
            // ALNS super intenso em momentos de stagnação?

        'temporary_intensive_alns_cooldown' => 2,
            // Dias de "cooldown" antes de ativar novamente

        'enable_temporary_mutation_shock' => false,
            // Mutação shock (exploração) em stagnação?

        'temporary_mutation_shock_duration' => 2,
            // Quantas gerações de shock

        'enable_temporary_selection_pressure_reduction' => false,
            // Reduzir pressão de seleção (diversidade)?
    ],
];
```

### Parâmetros do DTO

```php
// GeneticAlgorithmConfigDTO

// POPULAÇÃO E GERAÇÕES
public int $tamanhoPopulacao = 50;              // Indivíduos/isla
public int $numeroGeracoes = 300;               // Max gerações

// OPERADORES
public float $taxaMutacao = 0.02;               // Taxa base
public float $taxaCrossover = 0.80;             // 80% cross by default
public float $taxaMutacaoMin = 0.005;           // Min adaptive
public float $taxaMutacaoMax = 0.35;            // Max adaptive

// ELITISMO
public float $taxaElitismo = 0.10;              // 10% elite mantida

// PARADA
public float $targetFitness = 100.0;            // Fitness alvo
public int $maxGenerationsWithoutImprovement = 50;
public int $limiteEstagnacao = 50;

// HORÁRIO
public int $aulasPorDia = 7;                    // Max/dia
public int $diasSemana = 6;                     // Seg-Sab
public int $duracaoAulaMinutos = 50;
```

### Frequência de ALNS

```php
// RunGeneticAlgorithm.php

private function resolveBaseLnsFrequency(int $generations): int
{
    // ALNS a cada ~5-10 gerações dependendo do tamanho
    if ($generations >= 500) {
        return 8;           // A cada 8 ger.
    } elseif ($generations >= 200) {
        return 5;           // A cada 5 ger.
    }

    return 10;              // A cada 10 ger. (raro)
}

// Configuração nas ilhas
$engine = new GeneticAlgorithmEngine(
    lnsFrequency: $baseLnsFrequency,  // ~5-8
    // ...
);
```

---

## 📈 Modelo de Ilhas (Island Model)

```
┌─────────────────────────────────────────┐
│ IslandModelEngine                       │
│ ├─ runGenerations($generations)         │
│ │  ├─ Island 1                          │
│ │  │  ├─ populationSize = 50            │
│ │  │  ├─ engine = GeneticAlgorithmEngine│
│ │  │  └─ Loop evolution aqui            │
│ │  │                                    │
│ │  └─ Island 2                          │
│ │     ├─ populationSize = 50            │
│ │     ├─ engine = GeneticAlgorithmEngine│
│ │     └─ Loop evolution aqui            │
│ │                                       │
│ └─ A cada 25 gerações:                  │
│    ├─ BestIndividualsMigration          │
│    │  └─ Melhor de ilha 1 → ilha 2      │
│    │  └─ Melhor de ilha 2 → ilha 1      │
│    └─ Mantém pressão seletiva sem perder│
│       diversidade global                │
└─────────────────────────────────────────┘
```

**Vantagens**:
- ✅ Exploração paralela de sub-espaços
- ✅ Comunicação via migração (best individuals)
- ✅ Diversidade mantida entre ilhas
- ✅ Possibilidade de paralelização real

---

## 📊 Métricas Coletadas

```php
MetricsRecorder
├─ generationData()
│  ├─ best_fitness_generation
│  ├─ avg_fitness_generation
│  ├─ worst_fitness_generation
│  ├─ diversity_generation
│  ├─ entropy_generation
│  ├─ hard_penalties
│  ├─ soft_penalties
│  └─ operator_statistics
│
└─ executionMetrics
   ├─ execution_id
   ├─ horario_id
   ├─ population_size
   ├─ generations_executed
   ├─ start_time / end_time
   ├─ total_duration_seconds
   ├─ average_fitness_history
   └─ convergence_metrics
```

---

## 🔍 Landscape Analysis

Executado a cada ~10 gerações:

```php
LandscapeEngine
├─ detect($population, $generation)
│  ├─ Tipo de paisagem: 'plateau', 'deep_valley', 'local_minimum'
│  ├─ Basin of attraction locked?
│  ├─ Fitness landscape ruggedness
│  └─ Directionality (ascendendo/descendendo?)
│
└─ response($detection)
   ├─ Se plateau → aumentar ALNS intensidade
   ├─ Se deep_valley → shocks de mutação
   └─ Se local_minimum → reduzir pressão seleção
```

---

## 🚀 Exemplo de Execução

```
RunGeneticAlgorithm::execute(horario_id=1)
│
├─► GeneticAlgorithmConfigDTO::fromModels(horario_1)
│   └─ tamanhoPopulacao = 50
│   └─ numeroGeracoes = 300
│   └─ taxaMutacao = 0.02
│
├─► ScheduleProblem creation
│   └─ fitnessEvaluator com 9 regras
│   └─ repairOperator (GreedyRepair)
│   └─ contextBuilder
│
├─► IslandModelEngine::run(300)
│   ├─ Island 1: GeneticAlgorithmEngine::run(populationSize=50)
│   │  ├─ Geração 1:
│   │  │  ├─ initializePopulation(50)
│   │  │  │  ├─ Para cada indivíduo:
│   │  │  │  │  └─ ScheduleProblem::createIndividual()
│   │  │  │  │     └─ GRASP com 12 tentativas max
│   │  │  │  └─ Population: 50 indivíduos viáveis gerados
│   │  │  │
│   │  │  ├─ Selection: Tournament(3) + FitnessSharing
│   │  │  ├─ Crossover: ConflictGraphCrossover
│   │  │  ├─ Mutation: AdaptiveDiversityMutation (escolhe aleatório)
│   │  │  ├─ Repair: GreedyRepair se inviável
│   │  │  ├─ Evaluation: Delta Fitness com cache
│   │  │  ├─ Replacement: Niching
│   │  │  └─ Best so far = 76.5
│   │  │
│   │  ├─ Geração 2-4: Evolução normal
│   │  │
│   │  ├─ Geração 5: ⚡ ALNS triggered (a cada 5 ger.)
│   │  │  └─ improve(best_individual)
│   │  │     ├─ Seleciona: destroy=ConflictDestroy, repair=RegretInsertion
│   │  │     ├─ Destroi ~25% genes
│   │  │     ├─ Reinsertion via regret
│   │  │     └─ improvement = 78.3 - 76.5 = +1.8 ✅
│   │  │
│   │  ├─ Geração 10: Landscape Analysis
│   │  │  └─ Detectado: "plateau" (fitness variância < threshold)
│   │  │  └─ → Aumentar ALNS intensidade
│   │  │
│   │  ├─ Gerações 11-25: Evolução com ALNS intenso
│   │  │
│   │  ├─ Geração 25: Migração para Isla 2
│   │  │  └─ best_island_1 → isla_2
│   │  │
│   │  ├─ Gerações 26-300: Continua...
│   │
│   ├─ Island 2: Similar (paralelizado)
│   │
│   └─ Final: best_global = 87.4 (fitness final)
│
├─► RunGeneticAlgorithm::finalizeBestSolution(best, problem)
│   ├─ Final repair attempt (3 passes)
│   │  └─ Tenta melhorar ainda mais antes de retornar
│   │
│   └─ best_fitness = 87.4
│
└─► Retorna: {
        'best': Cromossomo,
        'best_fitness': 87.4,
        'generation_metrics': [...],
    }
```

---

## 🎓 Resumo por Pontos Solicitados

### 1. **Estrutura das Classes Principais** ✅

- `RunGeneticAlgorithm`: Orquestrador, inicializa 2 ilhas
- `Cromossomo`: Solução com indexação otimizada
- `ScheduleProblem`: Cria população via GRASP, repara inviáveis
- `FitnessEvaluator`: Avalia via hard/soft penalties com cache
- `GeneticAlgorithmEngine`: Executa loop de evolução

### 2. **População Inicial (GRASP)** ✅

- RCL com α ∈ [0.15, 0.45]
- Seleção aleatória de slots de RCL
- Quality gate para rejeitar inviáveis
- Repair com GreedyRepairOperator
- Max 12 tentativas adaptativamente

### 3. **Algoritmo ALNS** ✅

- Destroy: Random, Conflict, Cluster
- Repair: GreedyRebuild, RegretInsertion
- Seleção via Roulette Wheel adaptativa
- Recompensa por melhoria
- Intensidade baseada em landscape

### 4. **Cálculo de Penalidade** ✅

- Hard: TeacherConflict, ClassConflict, WorkloadExceeded, MandatoryBlockViolation
- Soft: Window, Distribution, MaxLessonsPerDay, ConsecutiveLesson, PreferredTime
- Score Lexicográfico: [0, 50) inviável, [50, 100] viável
- Delta Fitness com Rule Dependency Graph

### 5. **Configurações do AG** ✅

- População: 50/ilha (2 ilhas)
- Gerações: 300
- Taxa mutação: 0.02 (adaptativa)
- Taxa crossover: 0.80
- Elite: 10%
- ALNS frequência: 5-8 gerações
- Migração: a cada 25 gerações

---

**Documentação criada em**: `docs/AG_ARCHITECTURE.md`
