# Exemplos Práticos - Algoritmo Genético

## 📝 Exemplo 1: Criar um Cromossomo com GRASP

```php
// app/Modules/Horarios/Domain/Problem/ScheduleProblem.php

// Obter o ScheduleProblem
$problem = new ScheduleProblem(
    data: $scheduleData,
    contextBuilder: new EvaluationContextBuilder(),
    fitnessEvaluator: $fitnessEvaluator,
    repairOperator: $repairOperator,
    progress: $progress
);

// Criar um indivíduo via GRASP
$cromossomo = $problem->createIndividual();

// $cromossomo é um Cromossomo com:
// - 150 genes (aulas alocadas em slots)
// - fitness = 76.5 (score lexicográfico)
// - conflictGraph preenchido e analisado
// - indexes otimizados para operações rápidas
```

---

## 📝 Exemplo 2: Calcular Fitness (Hard + Soft)

```php
// app/Modules/AG/Domain/Fitness/FitnessEvaluator.php

$cromossomo = /* ... */;

// Criar contexto de avaliação
$context = (new EvaluationContextBuilder())
    ->build($cromossomo, $scheduleData);

// Avaliar
$result = $fitnessEvaluator->evaluate($cromossomo, $context);

// Resultado contém:
$result->score()           // 76.5 (score lexicográfico)
$result->hardPenalty()     // 5.0 (conflitos hard)
$result->softPenalty()     // 12.3 (qualidade ruim)
$result->totalPenalty()    // 17.3

// Interpretation:
// - hardPenalty > 0 → Inviável (score ∈ [0, 50))
// - hardPenalty = 0 → Viável (score ∈ [50, 100])
```

**Exemplos de Breakdown:**

```
Cromossomo 1 (Inviável):
┌─────────────────────────────────────────┐
│ Hard Penalties:                         │
│ ├─ TeacherConflict: 2.0 × 2.0 = 4.0    │
│ ├─ ClassConflict: 1.0 × 2.0 = 2.0      │
│ └─ Total hard: 6.0                      │
│                                         │
│ Soft Penalties:                         │
│ ├─ Window: 0.3 × 1.0 = 0.3             │
│ ├─ Distribution: 2.1 × 1.5 = 3.15      │
│ └─ Total soft: 8.5                      │
│                                         │
│ Score Lexicográfico:                   │
│ hardPenalty = 6.0 > 0                  │
│ score = 49.999 / (1 + 6.0 + 8.5*0.10)  │
│ score = 49.999 / 7.85 = 6.37 ❌        │
│ (Inviável, score baixo)                │
└─────────────────────────────────────────┘

Cromossomo 2 (Viável):
┌─────────────────────────────────────────┐
│ Hard Penalties: 0.0 ✅                  │
│ Soft Penalties: 4.2                     │
│                                         │
│ Score Lexicográfico:                   │
│ hardPenalty = 0 (viável!)              │
│ score = 50.0 + (50.0 / (1 + 4.2))      │
│ score = 50.0 + 9.26 = 59.26 ✅        │
│ (Viável, qualidade aceitável)          │
└─────────────────────────────────────────┘
```

---

## 📝 Exemplo 3: Executar ALNS em Uma Solução

```php
// app/Modules/AG/Domain/Intensification/LNS/ALNS/

$alns = new AdaptiveLargeNeighborhoodSearch(
    destroyOperators: [
        new RandomDestroyOperator(),
        new ConflictDestroyOperator(new ConflictDetector()),
        new ClusterDestroyOperator(),
    ],
    repairOperators: [
        new LNSRepairAdapter($repairOperator, $scheduleData),
        new RegretInsertionOperator($scheduleData),
    ]
);

// Solução atual
$currentSolution = /* cromossomo com fitness 76.5 */;

// Contexto com observações de landscape
$context = [
    'landscape_observation' => [
        'phenomenon' => 'deep_valley',
        'basin_of_attraction_lock_detected' => true,
    ],
    'trigger' => [
        'alns_landscape_pressure' => true,
        'alns_real_activation_applied' => false,
    ],
];

// Aplicar ALNS
$improved = $alns->improve($currentSolution, $context);

// Resultado:
$improved->fitness()        // 78.3 (melhorado!)
$lastTelemetry = $alns->lastTelemetry();

// Telemetria:
$lastTelemetry['alns_destroy_operator']      // "ConflictDestroyOperator"
$lastTelemetry['alns_repair_operator']       // "RegretInsertionOperator"
$lastTelemetry['alns_improvement']           // 1.8
$lastTelemetry['alns_removed_genes']         // 32 (de 150)
$lastTelemetry['alns_intensity_profile']     // 0.75 (alta intensidade)
```

**Fluxo Detalhado do ALNS:**

```
Passo 1: Seleção de Operadores
┌────────────────────────────────────────────┐
│ Scores históricos de destroy operators:   │
│ ├─ RandomDestroy:    {uses: 12, reward: 8.5}
│ ├─ ConflictDestroy:  {uses: 15, reward: 14.2}  ← Melhor!
│ └─ ClusterDestroy:   {uses: 8,  reward: 3.1}
│                                            │
│ Seletor Roulette: ConflictDestroy         │
│ (Probabilidade ∝ score)                  │
│                                            │
│ Repair operators:                         │
│ ├─ GreedyRebuild:    {uses: 10, reward: 5.2}
│ └─ RegretInsertion:  {uses: 13, reward: 11.8}  ← Melhor!
│                                            │
│ Seletor Roulette: RegretInsertion         │
└────────────────────────────────────────────┘

Passo 2: Calcular Intensidade
┌────────────────────────────────────────────┐
│ Base intensity = 0.35                     │
│ + landscape_pressure = true → +0.15       │
│ + basin_lock_detected = true → +0.20      │
│ + deep_valley phenomenon → +0.18          │
│ + recent_success < 0.35 → +0.12          │
│ ═════════════════════════════════════════ │
│ Final: intensity = 1.0 (capped)           │
│                                            │
│ Configura operadores:                     │
│ ConflictDestroy.configureIntensity(1.0)  │
│ → destroyRatio = 0.45 (remove 45%)        │
│                                            │
│ RegretInsertion.configureIntensity(1.0)  │
│ → candidateSampleRatio = 1.0              │
│ → regretDepth = 4 (considera 4 melhores)  │
└────────────────────────────────────────────┘

Passo 3: Destroy
┌────────────────────────────────────────────┐
│ Cromossomo original: 150 genes            │
│                                            │
│ ConflictDestroy com intensity=1.0:        │
│ ├─ Identifica genes conflitantes          │
│ ├─ Calcula conflictScore para cada gene   │
│ ├─ Remove: (45% de 150) = 68 genes       │
│ │  (Escolhe os 68 mais conflitantes)      │
│ │                                         │
│ └─ PartialSolution:                       │
│    ├─ assigned: 82 genes (alocados)      │
│    └─ unassigned: 68 genes (flutuando)   │
└────────────────────────────────────────────┘

Passo 4: Repair
┌────────────────────────────────────────────┐
│ Input: PartialSolution com 82+68 genes   │
│                                            │
│ RegretInsertion::repair():                 │
│ genes = [82 assigned]                     │
│ unassigned = [68 floating]                │
│                                            │
│ Loop (até esvaziar unassigned):           │
│ ┌─ Iteração 1:                            │
│ │ ├─ Para cada gene unassigned:          │
│ │ │  Avaliar inserção em todos os slots  │
│ │ │  ├─ Slot A: cost = 2.1               │
│ │ │  ├─ Slot B: cost = 3.5 (2º melhor)   │
│ │ │  ├─ Slot C: cost = 4.2               │
│ │ │  └─ regret = 3.5 - 2.1 = 1.4        │
│ │ │                                       │
│ │ ├─ Gene com maior regret = Gene123     │
│ │ ├─ Aloca em Slot A                     │
│ │ └─ genes = [82 + Gene123]              │
│ │ └─ unassigned = [67 restantes]         │
│ │                                         │
│ │ (Próxima iteração...)                  │
│ │                                         │
│ └─ Final: genes = [150 alocados] ✅      │
│                                            │
│ Return: novo Cromossomo(150 genes)       │
└────────────────────────────────────────────┘

Passo 5: Avaliação & Recompensa
┌────────────────────────────────────────────┐
│ Fitness antes: 76.5                        │
│ Fitness depois: 78.3                       │
│ improvement = 78.3 - 76.5 = +1.8 ✅      │
│                                            │
│ Recompensa dos operadores:                │
│ σ = selectRewardScenario(improvement)     │
│   = σ++ (melhora substancial!)            │
│                                            │
│ scores['ConflictDestroy'].addReward(1.8)  │
│ scores['RegretInsertion'].addReward(1.8)  │
│                                            │
│ Próxima chamada ao ALNS:                  │
│ Estes operadores têm maior probabilidade  │
│ de serem selecionados novamente           │
└────────────────────────────────────────────┘
```

---

## 📝 Exemplo 4: Acumular Fitness (Score Lexicográfico)

```php
// Exemplo com múltiplos cromossomos

$cromossomos = [
    /* hard=6.0, soft=8.5 */ // score = 6.37  (inviável)
    /* hard=0.0, soft=4.2 */ // score = 59.26 (viável)
    /* hard=0.0, soft=2.1 */ // score = 76.92 (viável, melhor)
    /* hard=1.5, soft=7.0 */ // score = 14.71 (inviável)
];

// Ordenar por fitness (lexicográfico)
usort($cromossomos, fn($a, $b) => $b->fitness() <=> $a->fitness());

// Resultado:
// 1. [hard=0.0, soft=2.1]  → score = 76.92  ✅ MELHOR
// 2. [hard=0.0, soft=4.2]  → score = 59.26  ✅ Viável
// 3. [hard=1.5, soft=7.0]  → score = 14.71  ❌ Inviável
// 4. [hard=6.0, soft=8.5]  → score = 6.37   ❌ Inviável

/*
 * Propriedade importante:
 * Qualquer viável (score >= 50) DOMINA qualquer inviável (score < 50)
 *
 * Exemplo:
 * Viável com score=50.0 > Inviável com score=49.999
 * Mesmo que em termos de "quantidade de conflitos" pareçam próximos!
 *
 * Isso garante que o AG prioriza viabilidade antes de qualidade.
 */
```

---

## 📝 Exemplo 5: Loop de Uma Geração

```php
// app/Modules/AG/Application/GeneticAlgorithmEngine.php

public function run(int $populationSize): Cromossomo
{
    // ═══════════════════════════════════════════════════════
    // INICIALIZAÇÃO
    // ═══════════════════════════════════════════════════════
    $population = $this->initializePopulation($populationSize);
    // population = [50 cromossomos gerados via GRASP]
    // best_fitness = 76.5 (cromossomo 1)

    // ═══════════════════════════════════════════════════════
    // LOOP DE EVOLUÇÃO
    // ═══════════════════════════════════════════════════════
    $generation = 0;

    while (!$this->termination->shouldTerminate($generation, $population)) {
        $generation++;

        // ───────────────────────────────────────────────────
        // 1. SELEÇÃO
        // ───────────────────────────────────────────────────
        $parent1 = $this->selection->select($population);
        // TournamentSelection com 3 indivíduos
        // Cria compartilhamento de fitness via niching
        // parent1 = cromossomo_id_7 (fitness = 74.2)

        $parent2 = $this->selection->select($population);
        // parent2 = cromossomo_id_23 (fitness = 73.8)

        // ───────────────────────────────────────────────────
        // 2. CROSSOVER
        // ───────────────────────────────────────────────────
        if (rand(0, 1) < $this->crossoverRate) {  // 80% chance
            $offspring = $this->crossover->cross($parent1, $parent2);
            // ConflictGraphCrossover:
            // ├─ Analisa grafo de conflitos de ambos os pais
            // ├─ Tenta combinar estrutura de genes viáveis
            // └─ offspring = [75 genes de parent1, 75 de parent2]
        } else {
            $offspring = $parent1->copy();  // Clona sem crossover
        }

        // ───────────────────────────────────────────────────
        // 3. MUTAÇÃO
        // ───────────────────────────────────────────────────
        if (rand(0, 1) < $this->mutationRate) {  // 2% chance

            // Escolher tipo de mutação adaptativa
            $mutationType = $this->adaptiveMutation->selectType(
                diversityMetrics: $this->calculateDiversity($population)
            );
            // Opções:
            // - 'structured': StructuredSwapMutation (mantém estrutura)
            // - 'swap': GeneSwapMutation (aleatório)
            // - 'conflict': ConflictGuidedMutation (baseada em conflitos)

            $mutator = match ($mutationType) {
                'structured' => new StructuredSwapMutation(),
                'swap' => new GeneSwapMutation(),
                'conflict' => new ConflictGuidedMutation(),
            };

            $offspring = $mutator->mutate($offspring);
            // GeneSwapMutation:
            // ├─ Seleciona 2 genes aleatoriamente
            // ├─ Troca dias/períodos
            // └─ offspring genes: [..., gene_5@seg→ter, ..., gene_12@ter→seg, ...]
        }

        // ───────────────────────────────────────────────────
        // 4. REPAIR (Se inviável)
        // ───────────────────────────────────────────────────
        if ($this->problem->isInfeasible($offspring)) {
            $offspring = $this->repairOperator->repair($offspring);
            // GreedyRepairOperator:
            // ├─ Identifica genes conflitantes
            // ├─ Realoca em slots alternativos
            // └─ offspring: genes alocados viáveis
        }

        // ───────────────────────────────────────────────────
        // 5. AVALIAÇÃO
        // ───────────────────────────────────────────────────
        $context = $this->contextBuilder->build($offspring, $data);

        // Delta Fitness (otimizado)
        $affectedRegion = $this->calculateAffectedRegion($offspring);
        $fitnessResult = $this->fitnessEvaluator->evaluateDelta(
            $offspring,
            $context,
            $affectedRegion,
            $previousFitness
        );
        // Reavalia apenas regras afetadas (-70% custo)

        $offspring->setFitness($fitnessResult->score());
        // offspring.fitness = 75.3 (novo score)

        // ───────────────────────────────────────────────────
        // 6. ALNS (A cada lnsFrequency gerações)
        // ───────────────────────────────────────────────────
        if ($generation % $this->lnsFrequency === 0) {  // 5% das gerações

            $context = [
                'landscape_observation' => $this->landscape->detect(),
                'trigger' => $this->searchResponse->trigger(),
                'finalize_candidate' => fn($c) => $this->repair($c),
            ];

            $offspring = $this->lns->improve($offspring, $context);
            // ALNS aplicado
            // offspring.fitness = 77.1 (melhorado!)
        }

        // ───────────────────────────────────────────────────
        // 7. LANDSCAPE ANALYSIS (A cada 10 gerações)
        // ───────────────────────────────────────────────────
        if ($generation % 10 === 0) {
            $phenomenonType = $this->landscapeEngine->analyze(
                $population,
                $generation
            );
            // Retorna: 'plateau', 'deep_valley', 'local_minimum', etc.

            // Adaptar estratégia:
            if ($phenomenonType === 'plateau') {
                $this->lnsFrequency = 3;  // ALNS mais frequente
            }
        }

        // ───────────────────────────────────────────────────
        // 8. SUBSTITUIÇÃO
        // ───────────────────────────────────────────────────
        $population = $this->replacement->replace(
            $population,
            $offspring,
            $this->elitism
        );
        // AdaptiveNichingReplacement:
        // ├─ Preserva elite (10% melhores)
        // ├─ Substitui piores similares
        // └─ Mantém diversidade

        // population = [49 anteriores + offspring se melhor]
        // population.size() = 50

        // ───────────────────────────────────────────────────
        // 9. HIPERHEURÍSTICA
        // ───────────────────────────────────────────────────
        if ($this->hyperHeuristic !== null) {
            $this->hyperHeuristic->updateRewards(
                operator: $mutationType,
                improvement: $offspring->fitness() - $parent1->fitness(),
                success: $offspring->fitness() > $parent1->fitness()
            );
            // ├─ Se sucesso: score[mutationType] += reward
            // ├─ Algoritmo: ε-greedy (80% melhor, 20% aleatorio)
            // └─ Próximas gerações probabilidade ∝ score
        }

        // ───────────────────────────────────────────────────
        // 10. MIGRAÇÃO (A cada 25 gerações)
        // ───────────────────────────────────────────────────
        if ($generation % 25 === 0 && $this->islandId !== null) {
            $bestIndividual = $this->population->best();
            $this->islandModel->migrate(
                from: $this->islandId,
                individual: $bestIndividual
            );
            // ├─ Melhor da ilha 1 → ilha 2
            // └─ Melhor da ilha 2 → ilha 1
        }

        // ───────────────────────────────────────────────────
        // 11. LOGGING & TELEMETRIA
        // ───────────────────────────────────────────────────
        $generationMetrics = [
            'generation' => $generation,
            'best_fitness' => $this->population->best()->fitness(),
            'avg_fitness' => array_sum($fitnessScores) / count($fitnessScores),
            'worst_fitness' => $this->population->worst()->fitness(),
            'diversity' => $this->calculateDiversity($population),
            'entropy' => $this->calculateEntropy($population),
        ];

        $this->metrics->record($generationMetrics);
        $this->progress?->progress($generation, $generationMetrics);

        // ═══════════════════════════════════════════════════════
        // FIM DA GERAÇÃO
        // ═══════════════════════════════════════════════════════
    }

    // ═══════════════════════════════════════════════════════
    // PÓS-EVOLUÇÃO
    // ═══════════════════════════════════════════════════════
    return $this->population->best();  // Melhor encontrado
}
```

---

## 📝 Exemplo 6: Critério de Parada

```php
// app/Modules/AG/Domain/Termination/VarianceBasedTerminationCriterion.php

public function shouldTerminate(int $generation, array $population): bool
{
    // Critério 1: Máximo de gerações
    if ($generation >= $this->maxGenerations) {
        Log::info("Parada: Máximo de gerações ({$generation}) atingido");
        return true;
    }

    // Critério 2: Target fitness atingido
    $bestFitness = $population->best()->fitness();
    if ($bestFitness >= $this->targetFitness) {
        Log::info("Parada: Target fitness {$this->targetFitness} atingido");
        return true;
    }

    // Critério 3: Sem melhora por X gerações
    $generationsWithoutImprovement = $generation - $this->lastImprovementGen;
    if ($generationsWithoutImprovement >= $this->maxGenerationsWithoutImprovement) {
        Log::info("Parada: {$generationsWithoutImprovement} gen sem melhora");
        return true;
    }

    // Critério 4: Variância muito baixa (convergência)
    if ($generation >= $this->minGenerationsBeforeVarianceCheck) {

        $recentFitness = array_slice(
            $this->fitnessHistory,
            $generation - $this->varianceWindowSize,
            $this->varianceWindowSize
        );

        $variance = $this->calculateVariance($recentFitness);

        if ($variance < $this->varianceThreshold) {
            Log::info("Parada: Variância {$variance} < {$this->varianceThreshold}");
            return true;
        }
    }

    // Critério 5: Diversidade colapsada
    $diversity = $this->populationStatistics->calculateDiversity($population);
    $entropy = $this->populationStatistics->calculateEntropy($population);

    if ($diversity < $this->minDiversity || $entropy < $this->minEntropy) {
        Log::info("Parada: Diversidade/Entropia colapsada");
        return true;
    }

    return false;  // Continuar
}
```

---

## 📊 Configuração do DTO

```php
// app/Modules/AG/Support/DTO/GeneticAlgorithmConfigDTO.php

$config = GeneticAlgorithmConfigDTO::fromModels($horario);

// Proprietá acesso via:
$config->tamanhoPopulacao;           // 50
$config->numeroGeracoes;             // 300
$config->taxaMutacao;                // 0.02
$config->taxaCrossover;              // 0.80
$config->taxaElitismo;               // 0.10
$config->targetFitness;              // 100.0
$config->maxGenerationsWithoutImprovement; // 50

$config->aulasPorDia;                // 7
$config->diasSemana;                 // 6
$config->duracaoAulaMinutos;         // 50

$config->eliteCount();  // 5 (10% de 50)
```

---

## 🎯 Resumo do Fluxo Completo

```
┌─────────────────────────────────────────────────────────┐
│ RunGeneticAlgorithm::execute(horario)                  │
└────────────────┬────────────────────────────────────────┘
                 │
          ┌──────▼──────┐
          │ Configuração │
          │ Dados        │
          │ Fitness      │
          └──────┬──────┘
                 │
┌────────────────▼─────────────────────────────────────────┐
│ IslandModelEngine::run(2 ilhas, 300 gerações)           │
│                                                          │
│  ┌──────────────────────┬──────────────────────┐        │
│  │ ILHA 1               │ ILHA 2               │        │
│  │ GeneticAlgorithmEngine│ GeneticAlgorithmEngine│       │
│  │                      │                      │        │
│  │ Gerações:            │ Gerações:            │        │
│  │ 1. Init Pop GRASP   │ 1. Init Pop GRASP    │        │
│  │ 2-300. Evolução     │ 2-300. Evolução      │        │
│  │   - Seleção         │   - Seleção          │        │
│  │   - Crossover       │   - Crossover        │        │
│  │   - Mutação         │   - Mutação          │        │
│  │   - Repair          │   - Repair           │        │
│  │   - Delta Fitness   │   - Delta Fitness    │        │
│  │   - ALNS (a cada 5) │   - ALNS (a cada 5)  │        │
│  │   - Landscape (10)  │   - Landscape (10)   │        │
│  │   - Replace         │   - Replace          │        │
│  │   - Hiperheurística │   - Hiperheurística  │        │
│  │   - Migração (25)   │   - Migração (25)    │        │
│  │                      │                      │        │
│  │ Melhor: fitness=87.4 │ Melhor: fitness=86.8 │        │
│  └──────────────────────┴──────────────────────┘        │
└────────────────┬─────────────────────────────────────────┘
                 │
         ┌───────▼────────┐
         │ Melhor Global  │
         │ fitness = 87.4 │
         └───────┬────────┘
                 │
    ┌────────────▼─────────────┐
    │ Final Repair Attempts (3)│
    │ Tenta melhorar mais      │
    │ fitness = 87.5 (ligeiro) │
    └────────────┬─────────────┘
                 │
         ┌───────▼────────┐
         │ Retorna        │
         │ Cromossomo     │
         │ fitness=87.5   │
         └────────────────┘
```

---

## 📚 Referências Rápidas

### Arquivo Que Contém...

| Conceito | Arquivo |
|----------|---------|
| Loop de evolução | `GeneticAlgorithmEngine.php` |
| Geração via GRASP | `ScheduleProblem.php` |
| Cálculo de fitness | `FitnessEvaluator.php` |
| Mutação adaptativa | `AdaptiveDiversityMutation.php` |
| ALNS | `AdaptiveLargeNeighborhoodSearch.php` |
| Destroy operators | `Destroy/*.php` |
| Repair operators | `Repair/*.php` |
| Landscape analysis | `LandscapeEngine.php` |
| Modelo de ilhas | `IslandModelEngine.php` |
| Configurações | `GeneticAlgorithmConfigDTO.php` |
| Métricas | `MetricsRecorder.php` |
| Parada | `VarianceBasedTerminationCriterion.php` |

