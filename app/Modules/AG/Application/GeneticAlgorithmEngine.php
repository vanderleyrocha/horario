<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Models\ScheduleExecution;
use App\Modules\AG\Application\Progress\EvolutionProgress;
use App\Modules\AG\Domain\Contracts\FitnessEvaluatorInterface;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Evolution\IslandModel\IslandProfile;
use App\Modules\AG\Domain\HyperHeuristic\LearningHyperHeuristicController;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\Acceptance\AlnsAcceptanceCriterion;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\Acceptance\StrictScoreImprovementAcceptance;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\DTO\AlnsStepResult;
use App\Modules\AG\Domain\Landscape\LandscapeEngine;
use App\Modules\AG\Domain\Landscape\LandscapeMetrics;
use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Operators\Adaptive\AdaptiveMutationController;
use App\Modules\AG\Domain\Operators\Crossover\CrossoverOperatorInterface;
use App\Modules\AG\Domain\Operators\Elitism\ElitismStrategyInterface;
use App\Modules\AG\Domain\Operators\Mutation\Interfaces\DiversityAwareMutationInterface;
use App\Modules\AG\Domain\Operators\Mutation\MutationOperatorInterface;
use App\Modules\AG\Domain\Operators\Replacement\ReplacementStrategyInterface;
use App\Modules\AG\Domain\Operators\Selection\AdaptiveSelectionPressureInterface;
use App\Modules\AG\Domain\Operators\Selection\SelectionOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Termination\TerminationCriterionInterface;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Support\Exceptions\ExecutionCancelledException;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;
use Illuminate\Support\Facades\Log;

final class GeneticAlgorithmEngine
{
    private const INITIAL_POPULATION_LOG_SAMPLE_SIZE = 3;

    private const INITIAL_POPULATION_MAX_DUPLICATE_RETRIES = 4;

    private const OPERATIONAL_HEARTBEAT_INTERVAL_SECONDS = 30;

    private const LONG_RUNNING_OPERATION_LOG_INTERVAL_SECONDS = 300;

    private const CANCELLATION_CHECK_INTERVAL_SECONDS = 2;

    private const ALNS_STEP_TIMEOUT_MS_DEFAULT = 15000;

    private array $mutationPool;

    private array $lastEvolutionTelemetry = [];

    private int $evolutionGeneration = 0;

    private ?int $lastAlnsGeneration = null;

    private ?int $lastMutationShockGeneration = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $activeMutationShock = null;

    private float $currentSelectionPressureMultiplier = 1.0;

    private ?int $lastSelectionPressureReductionGeneration = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $activeSelectionPressureReduction = null;

    private ?float $lastOperationalHeartbeatAt = null;

    private ?float $lastLongRunningOperationLogAt = null;

    private ?float $lastCancellationCheckAt = null;

    private ?string $lastKnownExecutionStatus = null;

    private ?int $islandId = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $activeStagnationBurst = null;

    public function __construct(
        private ?AlnsAcceptanceCriterion $alnsAcceptance,
        private readonly GeneticProblem $problem,
        private readonly SelectionOperatorInterface $selection,
        private readonly CrossoverOperatorInterface $crossover,
        private readonly MutationOperatorInterface $mutation,
        private readonly TerminationCriterionInterface $termination,
        private readonly MetricsRecorder $metrics,
        private readonly ElitismStrategyInterface $elitism,
        private readonly AdaptiveMutationController $adaptiveMutation,
        private readonly FitnessEvaluatorInterface $populationEvaluator,
        private readonly ReplacementStrategyInterface $replacement,
        private readonly ?LearningHyperHeuristicController $hyperHeuristic,
        private readonly ?AdaptiveLargeNeighborhoodSearch $lns = null,
        private readonly ?ProgressReporterInterface $progress = null,
        private readonly int $lnsFrequency = 50,
        private readonly ?ExecutionMetricsRecorder $executionMetrics = null,
        private readonly ?LandscapeEngine $landscapeEngine = null,
    ) {
        $this->alnsAcceptance ??= new StrictScoreImprovementAcceptance();
        $this->mutationPool = [$this->mutation];
        $this->applySelectionPressureMultiplier(1.0);
    }

    public function run(int $populationSize): Cromossomo
    {
        $this->assertNotCancelled();
        $ini_time = microtime(true);
        $population = $this->initializePopulation($populationSize);
        Log::info('ga.evolution.started', [
            'execution_id' => $this->executionMetrics?->getExecutionId(),
            'population_size' => count($population),
            'initialization_time_seconds' => round(microtime(true) - $ini_time, 2),
        ]);
        $generation = 0;

        while (true) {
            if ($this->termination->shouldTerminate($generation, $population)) {
                if ($generation === 0) {
                    $initialMetrics = $this->metrics->recordExtended(
                        $generation,
                        $population,
                        0.0,
                        $this->termination->getGenerationsWithoutImprovement(),
                    );

                    $this->publishGenerationState(
                        generation: $generation,
                        metrics: $initialMetrics,
                        mutationRate: 0.0,
                        stagnation: $this->termination->getGenerationsWithoutImprovement(),
                        landscapeState: 'initial_population',
                        heatmap: [],
                        alnsTelemetry: [
                            'phase' => 'initial_population',
                            'initial_population_terminated' => true,
                        ],
                    );

                    Log::info('ga.population.initial_snapshot_published', [
                        'execution_id' => $this->executionMetrics?->getExecutionId(),
                        'generation' => $generation,
                        'best_fitness' => $initialMetrics->bestFitness,
                        'avg_fitness' => $initialMetrics->avgFitness,
                        'diversity' => $initialMetrics->diversity,
                    ]);
                }

                break;
            }

            $this->assertNotCancelled();

            $generationStep = $this->executeGenerationStep(
                population: $population,
                populationSize: $populationSize,
            );
            $population = $generationStep['population'];

            $stagnation = $this->termination->getGenerationsWithoutImprovement();

            $metrics = $this->metrics->recordExtended(
                $generation,
                $population,
                $generationStep['mutation_rate'],
                $stagnation,
            );

            Log::info("Generation {$generation} | best={$metrics->bestFitness} | avg={$metrics->avgFitness} | div={$metrics->diversity}");

            $postProcess = $this->postProcessGeneration(
                generation: $generation,
                population: $population,
                metrics: $metrics,
                mutationRate: $generationStep['mutation_rate'],
                stagnation: $stagnation,
                operatorUsed: $generationStep['operator_used'],
                operatorReward: $generationStep['operator_reward'],
                populationTrajectory: $generationStep,
                evaluationStartedAt: $generationStep['evaluation_started_at'] ?? null,
                evaluationContext: [
                    'population_target' => $populationSize,
                    'offspring_built' => count($population),
                ],
            );

            $metrics = $postProcess['metrics'];

            $this->publishGenerationState(
                generation: $generation,
                metrics: $metrics,
                mutationRate: $postProcess['mutation_rate'],
                stagnation: $stagnation,
                landscapeState: $postProcess['landscape_state'],
                heatmap: $postProcess['heatmap'],
                alnsTelemetry: $postProcess['alns_telemetry'],
            );

            $generation++;
        }

        return $this->getBest($population);
    }

    private function shouldMutate(float $rate): bool
    {
        return mt_rand() / mt_getrandmax() < $rate;
    }

    private function initializePopulation(int $size): array
    {
        $population = [];
        $sampleGenes = [];
        $seenSignatures = [];
        $duplicateRetries = 0;
        $acceptedDuplicates = 0;
        $sourceCounts = [];
        $duplicateSourceCounts = [];
        $acceptedDuplicateSourceCounts = [];
        $sourceExamples = [];
        $totalGraspAttempts = 0;
        $qualityGateEvaluations = 0;
        $qualityGatePassed = 0;
        $qualityGateRejected = 0;

        for ($i = 0; $i < $size; $i++) {
            $this->assertNotCancelled();

            $individual = null;

            for ($attempt = 1; $attempt <= self::INITIAL_POPULATION_MAX_DUPLICATE_RETRIES + 1; $attempt++) {
                $init_start_time = microtime(true);
                $candidate = $this->problem->createIndividual();
                Log::debug('ga.initial_population.candidate_created', [
                    'candidate_index' => $i,
                    'attempt' => $attempt,
                    'execution_id' => $this->executionMetrics?->getExecutionId(),
                    'candidate_signature_prefix' => substr($candidate->signature(), 0, 12),
                    'candidate_gene_count' => $candidate->count(),
                    'initial_population_time_seconds' => round(microtime(true) - $init_start_time, 4),
                ]);
                $candidate = $this->problem->repair($candidate);
                $source = $this->resolveInitialPopulationSource();
                $buildStats = $this->resolveInitialPopulationBuildStats();
                $signature = $candidate->signature();

                if (! isset($seenSignatures[$signature])) {
                    $individual = $candidate;
                    $seenSignatures[$signature] = true;
                    $this->accumulateInitialPopulationBuildStats(
                        totalGraspAttempts: $totalGraspAttempts,
                        qualityGateEvaluations: $qualityGateEvaluations,
                        qualityGatePassed: $qualityGatePassed,
                        qualityGateRejected: $qualityGateRejected,
                        buildStats: $buildStats,
                    );
                    $this->incrementInitialPopulationCounter($sourceCounts, $source);
                    $this->rememberInitialPopulationSourceExample($sourceExamples, $source, $candidate, false);
                    break;
                }

                $this->incrementInitialPopulationCounter($duplicateSourceCounts, $source);
                $this->rememberInitialPopulationSourceExample($sourceExamples, $source, $candidate, true);

                if ($attempt <= self::INITIAL_POPULATION_MAX_DUPLICATE_RETRIES) {
                    $duplicateRetries++;

                    continue;
                }

                $individual = $candidate;
                $acceptedDuplicates++;
                $this->accumulateInitialPopulationBuildStats(
                    totalGraspAttempts: $totalGraspAttempts,
                    qualityGateEvaluations: $qualityGateEvaluations,
                    qualityGatePassed: $qualityGatePassed,
                    qualityGateRejected: $qualityGateRejected,
                    buildStats: $buildStats,
                );
                $this->incrementInitialPopulationCounter($sourceCounts, $source);
                $this->incrementInitialPopulationCounter($acceptedDuplicateSourceCounts, $source);
                break;
            }

            if ($individual === null) {
                throw new \RuntimeException('Falha ao criar individuo para a populacao inicial.');
            }

            $population[] = $individual;

            if (count($sampleGenes) < self::INITIAL_POPULATION_LOG_SAMPLE_SIZE) {
                $sampleGenes[] = $individual->count();
            }
        }

        $this->populationEvaluator->evaluate($population);

        $fitnessValues = array_map(
            static fn (Cromossomo $individual): float => $individual->fitness(),
            $population,
        );

        Log::info('ga.population.initialized', [
            'execution_id' => $this->executionMetrics?->getExecutionId(),
            'population_size' => count($population),
            'sample_gene_counts' => $sampleGenes,
            'unique_signatures' => count($seenSignatures),
            'uniqueness_ratio' => count($population) === 0 ? 0.0 : round(count($seenSignatures) / count($population), 4),
            'duplicate_retries' => $duplicateRetries,
            'accepted_duplicates' => $acceptedDuplicates,
            'source_counts' => $sourceCounts,
            'duplicate_source_counts' => $duplicateSourceCounts,
            'accepted_duplicate_source_counts' => $acceptedDuplicateSourceCounts,
            'source_examples' => $sourceExamples,
            'total_grasp_attempts' => $totalGraspAttempts,
            'avg_grasp_attempts_per_individual' => count($population) === 0
                ? null
                : round($totalGraspAttempts / count($population), 4),
            'quality_gate_evaluations' => $qualityGateEvaluations,
            'quality_gate_passed' => $qualityGatePassed,
            'quality_gate_rejected' => $qualityGateRejected,
            'quality_gate_success_rate' => $qualityGateEvaluations === 0
                ? null
                : round($qualityGatePassed / $qualityGateEvaluations, 4),
            'best_fitness' => $fitnessValues === [] ? null : max($fitnessValues),
            'avg_fitness' => $fitnessValues === []
                ? null
                : array_sum($fitnessValues) / count($fitnessValues),
            'initial_population_avg_score' => $fitnessValues === []
                ? null
                : array_sum($fitnessValues) / count($fitnessValues),
        ]);

        // Sprint 3: publicar métricas de qualidade do batch
        $batchQuality = $this->computeInitialPopulationBatchQuality(
            fitnessValues: $fitnessValues,
            uniqueCount: count($seenSignatures),
            populationSize: count($population),
        );

        Log::info('schedule.initial_population.batch_quality', array_merge(
            [
                'execution_id' => $this->executionMetrics?->getExecutionId(),
                'island_id' => $this->islandId,
            ],
            $batchQuality,
        ));

        $this->progress?->report(array_merge(
            [
                'phase' => 'initial_population',
                'stage' => 'batch_quality',
                'execution_id' => $this->executionMetrics?->getExecutionId(),
                'island_id' => $this->islandId,
            ],
            $batchQuality,
        ));

        return $population;
    }

    /**
     * Sprint 3: Calcula métricas de qualidade do batch da população inicial.
     *
     * Avalia diversidade estrutural (signatures únicas) e dispersão de fitness,
     * emitindo um veredito que identifica problemas (convergência prematura, etc.).
     *
     * @param float[] $fitnessValues
     * @return array<string, mixed>
     */
    private function computeInitialPopulationBatchQuality(array $fitnessValues, int $uniqueCount, int $populationSize): array
    {
        if ($populationSize === 0 || $fitnessValues === []) {
            return [
                'uniqueness_ratio' => 0.0,
                'fitness_min' => null,
                'fitness_max' => null,
                'fitness_avg' => null,
                'fitness_std_dev' => null,
                'fitness_coefficient_of_variation' => null,
                'verdict' => 'empty',
                'issues' => [],
            ];
        }

        $uniquenessRatio = round($uniqueCount / $populationSize, 4);
        $avg = array_sum($fitnessValues) / $populationSize;
        $min = min($fitnessValues);
        $max = max($fitnessValues);

        $variance = array_sum(
            array_map(static fn (float $v): float => ($v - $avg) ** 2, $fitnessValues),
        ) / $populationSize;

        $stdDev = sqrt($variance);
        $coefficientOfVariation = $avg > 0.0 ? round($stdDev / $avg, 4) : 0.0;

        $issues = [];

        if ($uniquenessRatio < 0.75) {
            $issues[] = 'low_signature_diversity';
        }

        if ($populationSize >= 4 && $stdDev < 0.5) {
            $issues[] = 'fitness_collapsed';
        }

        if ($populationSize >= 4 && $coefficientOfVariation < 0.01) {
            $issues[] = 'low_fitness_dispersion';
        }

        return [
            'uniqueness_ratio' => $uniquenessRatio,
            'fitness_min' => round($min, 4),
            'fitness_max' => round($max, 4),
            'fitness_avg' => round($avg, 4),
            'fitness_std_dev' => round($stdDev, 4),
            'fitness_coefficient_of_variation' => $coefficientOfVariation,
            'verdict' => $issues === [] ? 'ok' : implode('|', $issues),
            'issues' => $issues,
        ];
    }

    private function resolveInitialPopulationSource(): string
    {
        if (! $this->problem instanceof ScheduleProblem) {
            return 'unknown';
        }

        $source = $this->problem->lastInitialPopulationSource();

        if (! is_string($source) || trim($source) === '') {
            return 'unknown';
        }

        return $source;
    }

    /**
     * @return array{
     *     grasp_attempts_used: int,
     *     quality_gate_evaluations: int,
     *     quality_gate_passed: int,
     *     quality_gate_rejected: int,
     *     source: string
     * }
     */
    private function resolveInitialPopulationBuildStats(): array
    {
        if (! $this->problem instanceof ScheduleProblem) {
            return [
                'grasp_attempts_used' => 0,
                'quality_gate_evaluations' => 0,
                'quality_gate_passed' => 0,
                'quality_gate_rejected' => 0,
                'source' => 'unknown',
            ];
        }

        return $this->problem->lastInitialPopulationBuildStats();
    }

    /**
     * @param array{
     *     grasp_attempts_used: int,
     *     quality_gate_evaluations: int,
     *     quality_gate_passed: int,
     *     quality_gate_rejected: int,
     *     source: string
     * } $buildStats
     */
    private function accumulateInitialPopulationBuildStats(int &$totalGraspAttempts, int &$qualityGateEvaluations, int &$qualityGatePassed, int &$qualityGateRejected, array $buildStats): void
    {
        $totalGraspAttempts += (int) ($buildStats['grasp_attempts_used'] ?? 0);
        $qualityGateEvaluations += (int) ($buildStats['quality_gate_evaluations'] ?? 0);
        $qualityGatePassed += (int) ($buildStats['quality_gate_passed'] ?? 0);
        $qualityGateRejected += (int) ($buildStats['quality_gate_rejected'] ?? 0);
    }

    /**
     * @param array<string, int> $counters
     */
    private function incrementInitialPopulationCounter(array &$counters, string $source): void
    {
        $counters[$source] = ($counters[$source] ?? 0) + 1;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $sourceExamples
     */
    private function rememberInitialPopulationSourceExample(array &$sourceExamples, string $source, Cromossomo $candidate, bool $duplicate): void
    {
        $examples = $sourceExamples[$source] ?? [];

        if (count($examples) >= 3) {
            return;
        }

        $examples[] = [
            'signature_prefix' => substr($candidate->signature(), 0, 12),
            'gene_count' => $candidate->count(),
            'duplicate' => $duplicate,
        ];

        $sourceExamples[$source] = $examples;
    }

    private function getBest(array $population): Cromossomo
    {
        usort($population, fn ($a, $b) => $b->fitness() <=> $a->fitness());

        return $population[0];
    }

    public function createIndividual(): Cromossomo
    {
        $individual = $this->problem->createIndividual();
        $individual = $this->problem->repair($individual);

        // ✅ AÇÃO 03: Remover avaliação duplicada
        // Problema: población é avaliada 2x - aqui individualmente e depois em batch
        // Solução: deixar apenas avaliação em batch em initializePopulation()
        // A avaliação batch é mais eficiente e reutiliza cache melhor
        // $this->problem->evaluate($individual);  ← REMOVIDO

        return $individual;
    }

    public function setIslandContext(int $islandId): void
    {
        $this->islandId = $islandId;
    }

    public function activateStagnationBurst(
        int $generation,
        int $durationGenerations,
        float $mutationMultiplier,
        float $selectionPressureMultiplier,
        bool $forceAlns,
        string $reason,
    ): void {
        $duration = max(1, $durationGenerations);

        $this->activeStagnationBurst = [
            'activated_generation' => $generation,
            'duration_generations' => $duration,
            'remaining_generations' => $duration,
            'mutation_multiplier' => max(1.0, $mutationMultiplier),
            'selection_pressure_multiplier' => max(0.34, min(1.0, $selectionPressureMultiplier)),
            'force_alns' => $forceAlns,
            'reason' => $reason,
        ];

        Log::info('ga.stagnation_burst.armed', [
            'execution_id' => $this->executionMetrics?->getExecutionId(),
            'island_id' => $this->islandId,
            'generation' => $generation,
            'duration_generations' => $duration,
            'mutation_multiplier' => $this->activeStagnationBurst['mutation_multiplier'],
            'selection_pressure_multiplier' => $this->activeStagnationBurst['selection_pressure_multiplier'],
            'force_alns' => $forceAlns,
            'reason' => $reason,
        ]);
    }

    /**
     * Sprint 4: Define o perfil de ilha para orientar a construção GRASP e mutação.
     *
     * Conservative → alpha baixo, mutação moderada (convergência rápida).
     * Exploratory  → alpha alto, mutação elevada (diversidade).
     * Balanced     → configuração padrão.
     */
    public function setIslandProfile(IslandProfile $profile): void
    {
        if ($this->problem instanceof ScheduleProblem) {
            $this->problem->setIslandProfile($profile);
        }

        Log::info('ga.island.profile_set', [
            'island_id' => $this->islandId,
            'profile' => $profile->value,
            'label' => $profile->label(),
        ]);
    }

    public function currentEvolutionGeneration(): int
    {
        return $this->evolutionGeneration;
    }

    public function lastEvolutionTelemetry(): array
    {
        return $this->lastEvolutionTelemetry;
    }

    public function evolveGeneration(array $population, int $populationSize): array
    {
        $this->assertNotCancelled();

        $stagnationBurst = $this->stagnationBurstState();

        $currentGeneration = $this->evolutionGeneration;
        $generationStep = $this->executeGenerationStep(
            population: $population,
            populationSize: $populationSize,
        );

        $newPopulation = $generationStep['population'];
        $evaluationStartedAt = (float) ($generationStep['evaluation_started_at'] ?? microtime(true));
        $evaluationContext = [
            'population_target' => $populationSize,
            'offspring_built' => count($newPopulation),
        ];
        $telemetry = [];
        $stagnation = $this->termination->getGenerationsWithoutImprovement();
        $observationPayload = null;
        $landscapeState = null;
        $activateDynamicLns = false;
        $selectionPressureTelemetry = $this->selectionPressureTelemetry();

        $this->reportEvaluationWatchdog(
            generation: $currentGeneration,
            operationStartedAt: $evaluationStartedAt,
            substage: 'generation_step_completed',
            context: $evaluationContext,
            force: true,
        );

        if ($this->landscapeEngine !== null) {
            $this->reportEvaluationWatchdog(
                generation: $currentGeneration,
                operationStartedAt: $evaluationStartedAt,
                substage: 'local_metrics_recording_started',
                context: $evaluationContext,
                force: true,
            );

            $localMetrics = $this->metrics->recordExtended(
                generation: $currentGeneration,
                population: $newPopulation,
                mutationRate: $generationStep['mutation_rate'],
                stagnation: $stagnation,
            );

            $this->reportEvaluationWatchdog(
                generation: $currentGeneration,
                operationStartedAt: $evaluationStartedAt,
                substage: 'local_metrics_recorded',
                context: $evaluationContext + [
                    'best_fitness_partial' => $localMetrics->bestFitness,
                    'avg_fitness_partial' => $localMetrics->avgFitness,
                ],
                force: true,
            );

            $this->reportEvaluationWatchdog(
                generation: $currentGeneration,
                operationStartedAt: $evaluationStartedAt,
                substage: 'landscape_evaluation_started',
                context: $evaluationContext,
                force: true,
            );

            $response = $this->landscapeEngine->evaluate(
                new LandscapeMetrics(
                    generation: $currentGeneration,
                    bestFitness: $localMetrics->bestFitness,
                    avgFitness: $localMetrics->avgFitness,
                    variance: $localMetrics->variance,
                    diversity: $localMetrics->diversity,
                    entropy: $localMetrics->entropy,
                    stagnation: $stagnation,
                    improvementAcceptanceRate: $generationStep['improvement_acceptance_rate'],
                    worseningAcceptanceRate: $generationStep['worsening_acceptance_rate'],
                    populationTurnover: $generationStep['population_turnover'],
                    bestSignatureChanged: $generationStep['best_signature_changed'],
                    eliteSimilarity: $generationStep['elite_similarity'],
                    bestSignature: $generationStep['best_signature'],
                ),
            );

            $landscapeState = $response->state->value;
            $activateDynamicLns = $response->activateALNS;
            $telemetry['landscape_state'] = $landscapeState;

            $this->reportEvaluationWatchdog(
                generation: $currentGeneration,
                operationStartedAt: $evaluationStartedAt,
                substage: 'landscape_evaluation_completed',
                context: $evaluationContext + [
                    'landscape_state' => $landscapeState,
                    'alns_landscape_eligible' => $activateDynamicLns,
                ],
                force: true,
            );

            $selectionPressureTelemetry = $this->applySelectionPressureMultiplier(
                $response->selectionPressureMultiplier,
                'landscape_response',
                $landscapeState,
            );

            $telemetry += $selectionPressureTelemetry;

            if ($observation = $this->landscapeEngine->observation()) {
                $telemetry['landscape_phenomenon'] = $observation->phenomenon->value;
                $observationPayload = $observation->toArray();
                $telemetry['landscape_observation'] = $observationPayload;
            }
        }

        $alnsTrigger = $this->buildAlnsTriggerTelemetry(
            generation: $currentGeneration,
            landscapeState: $landscapeState,
            landscapeObservation: $observationPayload,
            activateDynamicLns: $activateDynamicLns,
        );

        if (($stagnationBurst['active'] ?? false) === true && ($stagnationBurst['force_alns'] ?? false) === true) {
            $alnsTrigger['alns_trigger_eligible'] = true;
            $alnsTrigger['alns_triggered'] = true;
            $alnsTrigger['alns_trigger_reason'] = 'stagnation_burst_forced_alns';
            $alnsTrigger['alns_effective_frequency'] = 1;
            $alnsTrigger['alns_adaptive_profile'] = [
                'strategy' => 'stagnation_burst',
                'reason' => $stagnationBurst['reason'] ?? 'stagnation_detected',
            ];
        }

        $telemetry += $alnsTrigger;

        $mutationShockActivation = $this->resolveRealMutationShockActivation(
            generation: $currentGeneration,
            landscapeObservation: $observationPayload,
            eligible: true,
        );
        $telemetry += $mutationShockActivation;

        $selectionPressureActivation = $this->resolveRealSelectionPressureReductionActivation(
            generation: $currentGeneration,
            landscapeObservation: $observationPayload,
            eligible: true,
        );
        $telemetry += $selectionPressureActivation;

        $this->reportEvaluationWatchdog(
            generation: $currentGeneration,
            operationStartedAt: $evaluationStartedAt,
            substage: 'trigger_resolution_completed',
            context: $evaluationContext + [
                'landscape_state' => $landscapeState,
                'alns_triggered' => $alnsTrigger['alns_triggered'] ?? false,
                'mutation_shock_triggered' => $mutationShockActivation['mutation_shock_triggered'] ?? false,
                'selection_pressure_triggered' => $selectionPressureActivation['selection_pressure_triggered'] ?? false,
            ],
            force: true,
        );

        if ($observationPayload !== null) {
            $telemetry['landscape_observation'] = $observationPayload + [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
                'mutation_shock' => $this->mutationShockObservationPayload($mutationShockActivation, $generationStep),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $generationStep,
                ),
            ];
        } elseif (($alnsTrigger['alns_trigger_eligible'] ?? false) === true) {
            $telemetry['landscape_observation'] = [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
                'mutation_shock' => $this->mutationShockObservationPayload($mutationShockActivation, $generationStep),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $generationStep,
                ),
            ];
        } elseif (
            ($mutationShockActivation['mutation_shock_trigger_eligible'] ?? false) === true
            || ($selectionPressureActivation['selection_pressure_trigger_eligible'] ?? false) === true
        ) {
            $telemetry['landscape_observation'] = [
                'mutation_shock' => $this->mutationShockObservationPayload($mutationShockActivation, $generationStep),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $generationStep,
                ),
            ];
        }

        if (($alnsTrigger['alns_triggered'] ?? false) === true) {
            $this->lastAlnsGeneration = $currentGeneration;

            $this->reportEvaluationWatchdog(
                generation: $currentGeneration,
                operationStartedAt: $evaluationStartedAt,
                substage: 'alns_started',
                context: $evaluationContext + [
                    'landscape_state' => $landscapeState,
                ],
                force: true,
            );

            $alnsTelemetry = $this->applyLns(
                population: $newPopulation,
                triggerTelemetry: $alnsTrigger,
                landscapeObservation: $telemetry['landscape_observation'] ?? null,
            );

            $telemetry += $alnsTelemetry;

            $this->reportEvaluationWatchdog(
                generation: $currentGeneration,
                operationStartedAt: $evaluationStartedAt,
                substage: 'alns_completed',
                context: $evaluationContext + [
                    'alns_destroy_operator' => $alnsTelemetry['alns_destroy_operator'] ?? null,
                    'alns_repair_operator' => $alnsTelemetry['alns_repair_operator'] ?? null,
                    'alns_accepted' => $alnsTelemetry['alns_accepted'] ?? false,
                ],
                force: true,
            );

            if (isset($telemetry['landscape_observation']) && is_array($telemetry['landscape_observation'])) {
                $telemetry['landscape_observation'] = $this->mergeAlnsObservationTelemetry(
                    $telemetry['landscape_observation'],
                    $alnsTrigger,
                    $alnsTelemetry,
                );
            }
        }

        $this->lastEvolutionTelemetry = [
            'mutation_rate' => $generationStep['mutation_rate'],
            'operator_used' => $generationStep['operator_used'],
            'operator_reward' => $generationStep['operator_reward'],
            'diversity' => $generationStep['diversity'],
            'entropy' => $generationStep['entropy'],
            'best_delta_window' => $telemetry['landscape_observation']['best_delta_window'] ?? null,
            'avg_delta_window' => $telemetry['landscape_observation']['avg_delta_window'] ?? null,
            'improvement_acceptance_rate' => $generationStep['improvement_acceptance_rate'],
            'worsening_acceptance_rate' => $generationStep['worsening_acceptance_rate'],
            'population_turnover' => $generationStep['population_turnover'],
            'best_signature_changed' => $generationStep['best_signature_changed'],
            'elite_similarity' => $generationStep['elite_similarity'],
            'selection_pressure_effective_multiplier' => $generationStep['selection_pressure_effective_multiplier'] ?? null,
            'selection_pressure_effective_tournament_size' => $generationStep['selection_pressure_effective_tournament_size'] ?? null,
            'stagnation_burst_active' => $generationStep['stagnation_burst_active'] ?? false,
            'stagnation_burst_remaining_generations_before' => $generationStep['stagnation_burst_remaining_generations_before'] ?? null,
            'stagnation_burst_remaining_generations_after' => $generationStep['stagnation_burst_remaining_generations_after'] ?? null,
        ] + $telemetry;

        $this->reportEvaluationWatchdog(
            generation: $currentGeneration,
            operationStartedAt: $evaluationStartedAt,
            substage: 'evolution_generation_completed',
            context: $evaluationContext + [
                'landscape_state' => $landscapeState,
                'alns_triggered' => $alnsTrigger['alns_triggered'] ?? false,
            ],
            force: true,
        );

        $this->consumeStagnationBurstGeneration();

        $this->evolutionGeneration++;

        return $newPopulation;
    }

    private function applyLns(array &$population, array $triggerTelemetry = [], ?array $landscapeObservation = null): array
    {
        if ($this->lns === null || $population === []) {
            return [];
        }

        $alnsStepStartedAt = microtime(true);
        $alnsTimeoutMs = max(1, (int) config('ag.alns_step.max_millis', self::ALNS_STEP_TIMEOUT_MS_DEFAULT));

        $watchdog = function (string $substage, array $context = [], bool $force = false) use ($alnsStepStartedAt): void {
            $this->reportEvaluationWatchdog(
                generation: $this->evolutionGeneration,
                operationStartedAt: $alnsStepStartedAt,
                substage: $substage,
                context: $context,
                force: $force,
            );
        };

        $best = $this->getBest($population);
        $stagnation = $this->termination->getGenerationsWithoutImprovement();

        try {
            $step = $this->executeAlnsStep(
                best: $best,
                generation: $this->evolutionGeneration,
                stagnation: $stagnation,
                triggerTelemetry: $triggerTelemetry,
                landscapeObservation: $landscapeObservation,
                alnsStepStartedAt: $alnsStepStartedAt,
                alnsTimeoutMs: $alnsTimeoutMs,
                watchdog: $watchdog,
            );
        } catch (\RuntimeException $exception) {
            if (! str_starts_with($exception->getMessage(), 'alns_step_timeout:')) {
                throw $exception;
            }

            $watchdog('alns_timeout', [
                'alns_timeout_ms' => $alnsTimeoutMs,
                'alns_timeout_message' => $exception->getMessage(),
            ], true);

            Log::warning('ga.alns.step_timeout', [
                'execution_id' => $this->executionMetrics?->getExecutionId(),
                'island_id' => $this->islandId,
                'local_generation' => $this->evolutionGeneration + 1,
                'timeout_ms' => $alnsTimeoutMs,
                'message' => $exception->getMessage(),
            ]);

            return $triggerTelemetry + [
                'alns_timed_out' => true,
                'alns_timeout_ms' => $alnsTimeoutMs,
                'alns_acceptance_policy' => class_basename($this->alnsAcceptance),
                'alns_accepted' => false,
                'alns_acceptance_reason' => 'timeout',
            ];
        }

        if ($step->accepted) {
            $this->replacement->replace($population, $step->selected);
            $this->problem->clearFitnessCache();
        }

        $telemetry = $this->lns->lastTelemetry();
        $telemetry['alns_base_fitness'] = $step->currentEvaluation->score();
        $telemetry['alns_candidate_fitness'] = $step->candidateEvaluation->score();
        $telemetry['alns_selected_fitness'] = $step->selectedEvaluation->score();

        $telemetry['alns_base_hard_penalty'] = $step->currentEvaluation->hardPenalty();
        $telemetry['alns_candidate_hard_penalty'] = $step->candidateEvaluation->hardPenalty();
        $telemetry['alns_selected_hard_penalty'] = $step->selectedEvaluation->hardPenalty();

        $telemetry['alns_base_soft_penalty'] = $step->currentEvaluation->softPenalty();
        $telemetry['alns_candidate_soft_penalty'] = $step->candidateEvaluation->softPenalty();
        $telemetry['alns_selected_soft_penalty'] = $step->selectedEvaluation->softPenalty();

        $telemetry['alns_improvement'] = $step->rawImprovement;
        $telemetry['alns_accepted_improvement'] = $step->acceptedImprovement;
        $telemetry['alns_reward'] = $step->reward;
        $telemetry['operator_reward'] = $step->reward;

        $telemetry['alns_acceptance_policy'] = class_basename($this->alnsAcceptance);
        $telemetry['alns_accepted'] = $step->accepted;
        $telemetry['alns_acceptance_reason'] = $this->resolveAlnsAcceptanceReason($step);

        $telemetry['alns_destroy_operator'] = $step->destroyOperator
            ?? ($telemetry['alns_destroy_operator'] ?? null);
        $telemetry['alns_repair_operator'] = $step->repairOperator
            ?? ($telemetry['alns_repair_operator'] ?? null);

        if ($telemetry !== []) {
            Log::info('ga.alns.applied', [
                'execution_id' => $this->executionMetrics?->getExecutionId(),
                'island_id' => $this->islandId,
                'local_generation' => $this->evolutionGeneration + 1,
                'trigger_reason' => $triggerTelemetry['alns_trigger_reason'] ?? null,
                'effective_frequency' => $triggerTelemetry['alns_effective_frequency'] ?? null,
                'destroy_operator' => $telemetry['alns_destroy_operator'] ?? null,
                'repair_operator' => $telemetry['alns_repair_operator'] ?? null,
                'improvement' => $step->rawImprovement,
                'accepted_improvement' => $step->acceptedImprovement,
                'base_fitness' => $step->currentEvaluation->score(),
                'candidate_fitness' => $step->candidateEvaluation->score(),
                'accepted' => $step->accepted,
                'acceptance_reason' => $telemetry['alns_acceptance_reason'],
                'reward' => $step->reward,
                'destroy_uses' => $telemetry['alns_destroy_stats']['uses'] ?? null,
                'destroy_mean_reward' => $telemetry['alns_destroy_stats']['mean_reward'] ?? null,
                'repair_uses' => $telemetry['alns_repair_stats']['uses'] ?? null,
                'repair_mean_reward' => $telemetry['alns_repair_stats']['mean_reward'] ?? null,
            ]);
        }

        return $triggerTelemetry + $telemetry;
    }

    private function executeAlnsStep(
        Cromossomo $best,
        int $generation,
        int $stagnation,
        array $triggerTelemetry = [],
        ?array $landscapeObservation = null,
        ?float $alnsStepStartedAt = null,
        int $alnsTimeoutMs = self::ALNS_STEP_TIMEOUT_MS_DEFAULT,
        ?callable $watchdog = null,
    ): AlnsStepResult {
        $stepStartedAt = $alnsStepStartedAt ?? microtime(true);

        $emitWatchdog = function (string $substage, array $context = [], bool $force = false) use ($watchdog): void {
            if ($watchdog === null) {
                return;
            }

            $watchdog($substage, $context, $force);
        };

        $emitWatchdog('alns_step_started', [
            'alns_timeout_ms' => $alnsTimeoutMs,
        ], true);
        $this->assertAlnsStepNotTimedOut($stepStartedAt, $alnsTimeoutMs, 'before_current_evaluation');

        $current = $best->copy();
        $currentEvaluation = $this->problem->evaluate($current);
        $current->setFitness($currentEvaluation->score());

        $emitWatchdog('alns_current_evaluation_completed', [
            'alns_timeout_ms' => $alnsTimeoutMs,
            'alns_elapsed_ms' => (int) round((microtime(true) - $stepStartedAt) * 1000),
        ], true);
        $this->assertAlnsStepNotTimedOut($stepStartedAt, $alnsTimeoutMs, 'before_improve_call');

        $emitWatchdog('alns_improve_started', [
            'alns_timeout_ms' => $alnsTimeoutMs,
        ], true);

        $remainingRepairBudgetMs = max(
            1,
            $alnsTimeoutMs - (int) round((microtime(true) - $stepStartedAt) * 1000),
        );

        $repairHeartbeat = function (array $heartbeatPayload) use ($emitWatchdog, $stepStartedAt, $alnsTimeoutMs): void {
            $repairEvent = is_string($heartbeatPayload['event'] ?? null) && $heartbeatPayload['event'] !== ''
                ? (string) $heartbeatPayload['event']
                : 'progress';

            $checkpoint = sprintf('inside_repair_%s', $repairEvent);
            $this->assertAlnsStepNotTimedOut($stepStartedAt, $alnsTimeoutMs, $checkpoint);

            $emitWatchdog('alns_repair_heartbeat', [
                'alns_timeout_ms' => $alnsTimeoutMs,
                'alns_elapsed_ms' => (int) round((microtime(true) - $stepStartedAt) * 1000),
                'alns_repair_event' => $repairEvent,
                'alns_repair_abort_reason' => $heartbeatPayload['abort_reason'] ?? null,
                'alns_repair_pass' => $heartbeatPayload['pass'] ?? null,
                'alns_repair_processed_invalid_genes' => $heartbeatPayload['processed_invalid_genes'] ?? null,
                'alns_repair_total_invalid_genes' => $heartbeatPayload['total_invalid_genes'] ?? null,
                'alns_repair_hard_penalty_before' => $heartbeatPayload['hard_penalty_before'] ?? null,
                'alns_repair_hard_penalty_after' => $heartbeatPayload['hard_penalty_after'] ?? null,
            ], in_array($repairEvent, ['repair_started', 'pass_started', 'pass_finished', 'repair_aborted'], true));
        };

        $candidate = $this->lns->improve($current->copy(), [
            'trigger' => $triggerTelemetry,
            'landscape_observation' => $landscapeObservation,
            'repair_context' => [
                'abort_if_timed_out' => function (string $checkpoint = 'repair_progress') use ($stepStartedAt, $alnsTimeoutMs): void {
                    $this->assertAlnsStepNotTimedOut(
                        $stepStartedAt,
                        $alnsTimeoutMs,
                        sprintf('inside_repair_%s', $checkpoint),
                    );
                },
                'progress_heartbeat' => $repairHeartbeat,
                'limits' => [
                    'max_millis' => $remainingRepairBudgetMs,
                ],
            ],
            // ✅ AÇÃO 04: Remover avaliação duplicada do callback
            // Problema: candidate era avaliado aqui e depois novamente abaixo
            // Solução: deixar apenas aqui a repair, sem evaluate
            'finalize_candidate' => function (Cromossomo $individual): Cromossomo {
                $individual = $this->problem->repair($individual);
                // REMOVIDO: $this->problem->evaluate($individual);
                // A avaliação acontece UMA ÚNICA VEZ abaixo (linha 473)

                return $individual;
            },
        ]);

        $emitWatchdog('alns_improve_completed', [
            'alns_timeout_ms' => $alnsTimeoutMs,
            'alns_elapsed_ms' => (int) round((microtime(true) - $stepStartedAt) * 1000),
        ], true);
        $this->assertAlnsStepNotTimedOut($stepStartedAt, $alnsTimeoutMs, 'after_improve_before_candidate_evaluation');

        // ✅ AÇÃO 04: Avaliação única e real do candidate
        // Agora candidate é avaliado apenas UMA VEZ com fitness real
        // Isso garante que o improvement calculado em ALNS.improve() usa fitness correto
        $candidateEvaluation = $this->problem->evaluate($candidate);
        $candidate->setFitness($candidateEvaluation->score());
        // Registrar para próxima iteração ALNS poder usar delta
        $this->problem->recordFitness($candidate, $candidateEvaluation);

        $emitWatchdog('alns_candidate_evaluation_completed', [
            'alns_timeout_ms' => $alnsTimeoutMs,
            'alns_elapsed_ms' => (int) round((microtime(true) - $stepStartedAt) * 1000),
        ], true);
        $this->assertAlnsStepNotTimedOut($stepStartedAt, $alnsTimeoutMs, 'after_candidate_evaluation');

        $rawImprovement = $candidateEvaluation->score() - $currentEvaluation->score();

        $accepted = $this->alnsAcceptance->shouldAccept(
            current: $current,
            currentResult: $currentEvaluation,
            candidate: $candidate,
            candidateResult: $candidateEvaluation,
            generation: $generation,
            stagnation: $stagnation,
        );

        $selected = $accepted ? $candidate : $current;
        $selectedEvaluation = $accepted ? $candidateEvaluation : $currentEvaluation;
        $acceptedImprovement = $accepted ? $rawImprovement : 0.0;

        $destroyOperator = $this->extractOperatorName(
            $this->lns->lastTelemetry()['alns_destroy_operator'] ?? null,
        );
        $repairOperator = $this->extractOperatorName(
            $this->lns->lastTelemetry()['alns_repair_operator'] ?? null,
        );

        $reward = $this->calculateAlnsReward(
            currentEvaluation: $currentEvaluation,
            candidateEvaluation: $candidateEvaluation,
            accepted: $accepted,
            rawImprovement: $rawImprovement,
        );

        if ($this->hyperHeuristic !== null) {
            if ($destroyOperator !== null) {
                $this->hyperHeuristic->recordRewardByName($destroyOperator, $reward);
            }

            if ($repairOperator !== null) {
                $this->hyperHeuristic->recordRewardByName($repairOperator, $reward);
            }
        }

        $emitWatchdog('alns_step_completed', [
            'alns_timeout_ms' => $alnsTimeoutMs,
            'alns_elapsed_ms' => (int) round((microtime(true) - $stepStartedAt) * 1000),
            'alns_destroy_operator' => $destroyOperator,
            'alns_repair_operator' => $repairOperator,
            'alns_accepted' => $accepted,
        ], true);

        return new AlnsStepResult(
            current: $current,
            currentEvaluation: $currentEvaluation,
            candidate: $candidate,
            candidateEvaluation: $candidateEvaluation,
            selected: $selected,
            selectedEvaluation: $selectedEvaluation,
            accepted: $accepted,
            rawImprovement: $rawImprovement,
            acceptedImprovement: $acceptedImprovement,
            reward: $reward,
            destroyOperator: $destroyOperator,
            repairOperator: $repairOperator,
        );
    }

    private function assertAlnsStepNotTimedOut(float $startedAt, int $timeoutMs, string $checkpoint): void
    {
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($elapsedMs <= $timeoutMs) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'alns_step_timeout: checkpoint=%s elapsed_ms=%d timeout_ms=%d',
            $checkpoint,
            $elapsedMs,
            $timeoutMs,
        ));
    }

    private function calculateAlnsReward(
        object $currentEvaluation,
        object $candidateEvaluation,
        bool $accepted,
        float $rawImprovement,
    ): float {
        if ($candidateEvaluation->hardPenalty() < $currentEvaluation->hardPenalty()) {
            return 2.0 + max(0.0, $rawImprovement);
        }

        if ($accepted && $rawImprovement > 0.0) {
            return 1.0 + $rawImprovement;
        }

        if (! $accepted && $rawImprovement > 0.0) {
            return 0.25 * $rawImprovement;
        }

        return 0.0;
    }

    private function resolveAlnsAcceptanceReason(AlnsStepResult $step): string
    {
        if ($step->accepted) {
            if ($step->candidateEvaluation->hardPenalty() < $step->currentEvaluation->hardPenalty()) {
                return 'accepted_hard_penalty_improved';
            }

            if ($step->candidateEvaluation->hardPenalty() === $step->currentEvaluation->hardPenalty()
                && $step->candidateEvaluation->score() > $step->currentEvaluation->score()) {
                return 'accepted_score_improved_same_hard_penalty';
            }

            return 'accepted_by_policy';
        }

        if ($step->candidateEvaluation->hardPenalty() > $step->currentEvaluation->hardPenalty()) {
            return 'rejected_hard_penalty_worsened';
        }

        if ($step->candidateEvaluation->hardPenalty() === $step->currentEvaluation->hardPenalty()
            && $step->candidateEvaluation->score() <= $step->currentEvaluation->score()) {
            return 'rejected_no_score_improvement';
        }

        return 'rejected_by_policy';
    }

    private function extractOperatorName(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param Cromossomo[] $population
     * @return array{
     *     metrics: GenerationMetrics,
     *     mutation_rate: float,
     *     landscape_state: string|null,
     *     heatmap: array,
     *     alns_telemetry: array<string, mixed>
     * }
     */
    private function postProcessGeneration(
        int $generation,
        array &$population,
        GenerationMetrics $metrics,
        float $mutationRate,
        int $stagnation,
        string $operatorUsed,
        float $operatorReward,
        array $populationTrajectory,
        ?float $evaluationStartedAt = null,
        array $evaluationContext = [],
    ): array {
        $landscapeState = null;
        $heatmap = [];
        $activateDynamicLNS = false;
        $telemetry = [];
        $observationPayload = null;
        $selectionPressureTelemetry = $this->selectionPressureTelemetry();
        $startedAt = $evaluationStartedAt ?? microtime(true);

        $this->reportEvaluationWatchdog(
            generation: $generation,
            operationStartedAt: $startedAt,
            substage: 'post_process_started',
            context: $evaluationContext,
            force: true,
        );

        if ($this->landscapeEngine !== null) {
            $this->reportEvaluationWatchdog(
                generation: $generation,
                operationStartedAt: $startedAt,
                substage: 'landscape_analysis_started',
                context: $evaluationContext,
                force: true,
            );

            $landscapeMetrics = new LandscapeMetrics(
                generation: $generation,
                bestFitness: $metrics->bestFitness,
                avgFitness: $metrics->avgFitness,
                variance: $metrics->variance,
                diversity: $metrics->diversity,
                entropy: $metrics->entropy,
                stagnation: $stagnation,
                improvementAcceptanceRate: (float) ($populationTrajectory['improvement_acceptance_rate'] ?? 0.0),
                worseningAcceptanceRate: (float) ($populationTrajectory['worsening_acceptance_rate'] ?? 0.0),
                populationTurnover: (float) ($populationTrajectory['population_turnover'] ?? 0.0),
                bestSignatureChanged: (bool) ($populationTrajectory['best_signature_changed'] ?? false),
                eliteSimilarity: (float) ($populationTrajectory['elite_similarity'] ?? 0.0),
                bestSignature: (string) ($populationTrajectory['best_signature'] ?? ''),
            );

            $response = $this->landscapeEngine->evaluate($landscapeMetrics);

            if ($this->hyperHeuristic) {
                $this->hyperHeuristic->updateLandscapeState($response->state);
            }

            $heatmap = $this->landscapeEngine->heatmap();
            $landscapeState = $response->state->value;
            $mutationRate *= $response->mutationMultiplier;
            $mutationRate = max(0.001, min($mutationRate, 0.9));
            $activateDynamicLNS = $response->activateALNS;

            $selectionPressureTelemetry = $this->applySelectionPressureMultiplier(
                $response->selectionPressureMultiplier,
                'landscape_response',
                $landscapeState,
            );
            $telemetry += $selectionPressureTelemetry;

            if ($observation = $this->landscapeEngine->observation()) {
                $telemetry['landscape_phenomenon'] = $observation->phenomenon->value;
                $observationPayload = $observation->toArray();
                $telemetry['landscape_observation'] = $observationPayload;
            }

            $this->reportEvaluationWatchdog(
                generation: $generation,
                operationStartedAt: $startedAt,
                substage: 'landscape_analysis_completed',
                context: $evaluationContext + [
                    'landscape_state' => $landscapeState,
                ],
                force: true,
            );
        }

        $alnsTrigger = $this->buildAlnsTriggerTelemetry(
            generation: $generation,
            landscapeState: $landscapeState,
            landscapeObservation: $observationPayload,
            activateDynamicLns: $activateDynamicLNS,
        );
        $telemetry += $alnsTrigger;

        $mutationShockActivation = $this->resolveRealMutationShockActivation(
            generation: $generation,
            landscapeObservation: $observationPayload,
            eligible: true,
        );
        $telemetry += $mutationShockActivation;

        $selectionPressureActivation = $this->resolveRealSelectionPressureReductionActivation(
            generation: $generation,
            landscapeObservation: $observationPayload,
            eligible: true,
        );
        $telemetry += $selectionPressureActivation;

        if ($observationPayload !== null) {
            $telemetry['landscape_observation'] = $observationPayload + [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
                'mutation_shock' => $this->mutationShockObservationPayload($mutationShockActivation, $populationTrajectory),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $populationTrajectory,
                ),
            ];
        } elseif (($alnsTrigger['alns_trigger_eligible'] ?? false) === true) {
            $telemetry['landscape_observation'] = [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
                'mutation_shock' => $this->mutationShockObservationPayload($mutationShockActivation, $populationTrajectory),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $populationTrajectory,
                ),
            ];
        } elseif (
            ($mutationShockActivation['mutation_shock_trigger_eligible'] ?? false) === true
            || ($selectionPressureActivation['selection_pressure_trigger_eligible'] ?? false) === true
        ) {
            $telemetry['landscape_observation'] = [
                'mutation_shock' => $this->mutationShockObservationPayload($mutationShockActivation, $populationTrajectory),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $populationTrajectory,
                ),
            ];
        }

        if (($alnsTrigger['alns_triggered'] ?? false) === true) {
            $this->reportEvaluationWatchdog(
                generation: $generation,
                operationStartedAt: $startedAt,
                substage: 'alns_started',
                context: $evaluationContext,
                force: true,
            );

            $this->lastAlnsGeneration = $generation;

            $alnsTelemetry = $this->applyLns(
                population: $population,
                triggerTelemetry: $alnsTrigger,
                landscapeObservation: $telemetry['landscape_observation'] ?? null,
            );

            $telemetry += $alnsTelemetry;

            if (isset($telemetry['landscape_observation']) && is_array($telemetry['landscape_observation'])) {
                $telemetry['landscape_observation'] = $this->mergeAlnsObservationTelemetry(
                    $telemetry['landscape_observation'],
                    $alnsTrigger,
                    $alnsTelemetry,
                );
            }

            $metrics = $this->metrics->recordExtended(
                generation: $generation,
                population: $population,
                mutationRate: $mutationRate,
                stagnation: $stagnation,
                landscapeState: $landscapeState,
                forceRefreshStatistics: true,
            );

            $this->reportEvaluationWatchdog(
                generation: $generation,
                operationStartedAt: $startedAt,
                substage: 'alns_completed',
                context: $evaluationContext,
                force: true,
            );
        }

        $metrics->landscapeState = $landscapeState;
        $metrics->operatorUsed = $operatorUsed;
        $metrics->operatorReward = $operatorReward;
        $metrics->alnsDestroyOperator = $telemetry['alns_destroy_operator'] ?? null;
        $metrics->alnsRepairOperator = $telemetry['alns_repair_operator'] ?? null;
        $metrics->alnsImprovement = $telemetry['alns_improvement'] ?? null;
        $metrics->landscapePhenomenon = $telemetry['landscape_phenomenon'] ?? null;
        $metrics->landscapeObservation = $telemetry['landscape_observation'] ?? null;

        $this->reportEvaluationWatchdog(
            generation: $generation,
            operationStartedAt: $startedAt,
            substage: 'post_process_completed',
            context: $evaluationContext + [
                'landscape_state' => $landscapeState,
                'alns_triggered' => $alnsTrigger['alns_triggered'] ?? false,
            ],
            force: true,
        );

        return [
            'metrics' => $metrics,
            'mutation_rate' => $mutationRate,
            'landscape_state' => $landscapeState,
            'heatmap' => $heatmap,
            'alns_telemetry' => $telemetry,
        ];
    }

    private function publishGenerationState(
        int $generation,
        GenerationMetrics $metrics,
        float $mutationRate,
        int $stagnation,
        ?string $landscapeState,
        array $heatmap,
        array $alnsTelemetry,
    ): void {
        if ($this->executionMetrics !== null) {
            $this->executionMetrics->recordGeneration($metrics);
        }

        $this->metrics->publishGenerationMetrics($metrics->toArray() + [
            'execution_id' => $this->executionMetrics?->getExecutionId(),
            'landscape_state' => $landscapeState ?? 'unknown',
            'landscape_heatmap' => $heatmap,
            'timestamp' => microtime(true),
        ]);

        if ($this->progress === null) {
            return;
        }

        $progress = new EvolutionProgress();
        $progress->phase = 'evolution';
        $progress->generation = $generation;
        $progress->maxGenerations = $this->termination->getMaxGenerations() ?? 0;
        $progress->bestFitness = $metrics->bestFitness;
        $progress->avgFitness = $metrics->avgFitness;
        $progress->diversity = $metrics->diversity;
        $progress->entropy = $metrics->entropy;
        $progress->mutationRate = $mutationRate;
        $progress->stagnation = $stagnation;
        $progress->landscapeState = $landscapeState ?? 'unknown';

        $this->progress->report($progress->toArray() + $alnsTelemetry);
    }

    /**
     * @param array<string, mixed>|null $landscapeObservation
     * @return array<string, mixed>
     */
    private function buildAlnsTriggerTelemetry(
        int $generation,
        ?string $landscapeState,
        ?array $landscapeObservation,
        bool $activateDynamicLns,
    ): array {
        $baseFrequency = max(2, $this->lnsFrequency);
        $maxGenerations = max(1, $this->termination->getMaxGenerations() ?? ($generation + 1));
        $budgetFrequency = $this->budgetAwareLnsFrequency($maxGenerations);
        $landscapeFrequency = $this->landscapeAwareLnsFrequency(
            budgetFrequency: $budgetFrequency,
            landscapeState: $landscapeState,
            landscapeObservation: $landscapeObservation,
            activateDynamicLns: $activateDynamicLns,
        );

        $effectiveFrequency = min($baseFrequency, $budgetFrequency, $landscapeFrequency ?? PHP_INT_MAX);

        if ($effectiveFrequency === PHP_INT_MAX) {
            $effectiveFrequency = min($baseFrequency, $budgetFrequency);
        }

        $recentEffectiveness = $this->lns?->recentEffectiveness() ?? [
            'sample_size' => 0,
            'mean_improvement' => 0.0,
            'success_rate' => 0.0,
        ];

        $baseCooldown = max(1, (int) floor($effectiveFrequency / 2));
        $cooldownBrake = $this->adaptiveAlnsCooldownBrake($recentEffectiveness);

        $cooldown = $baseCooldown + (int) ($cooldownBrake['extra_generations'] ?? 0);

        $generationsSinceLastTrigger = $this->lastAlnsGeneration === null
            ? null
            : $generation - $this->lastAlnsGeneration;

        $cooldownSatisfied = $generationsSinceLastTrigger === null || $generationsSinceLastTrigger >= $cooldown;

        $realActivation = $this->resolveRealAlnsActivation(
            landscapeObservation: $landscapeObservation,
            generationsSinceLastTrigger: $generationsSinceLastTrigger,
            eligible: $this->lns !== null && $generation > 0,
        );

        $landscapePressure = $this->hasLandscapePressure(
            landscapeState: $landscapeState,
            landscapeObservation: $landscapeObservation,
            activateDynamicLns: $activateDynamicLns,
        );

        $intervalDue = $generation > 0 && $generation % $effectiveFrequency === 0;
        $landscapeDue = $generation > 0 && $landscapePressure && $cooldownSatisfied;
        $triggerSources = [];

        if ($intervalDue && $cooldownSatisfied) {
            $triggerSources[] = 'budget_interval';
        }

        if ($landscapeDue) {
            $triggerSources[] = 'landscape_pressure';
        }

        if (($realActivation['applied'] ?? false) === true) {
            $triggerSources[] = 'activation_gate';
        }

        $eligible = $this->lns !== null && $generation > 0;
        $triggered = $eligible && $triggerSources !== [];

        return [
            'alns_base_frequency' => $baseFrequency,
            'alns_budget_frequency' => $budgetFrequency,
            'alns_landscape_frequency' => $landscapeFrequency,
            'alns_effective_frequency' => $effectiveFrequency,
            'alns_base_cooldown_generations' => $baseCooldown,
            'alns_cooldown_generations' => $cooldown,
            'alns_cooldown_brake_applied' => (bool) ($cooldownBrake['applied'] ?? false),
            'alns_cooldown_brake_extra_generations' => (int) ($cooldownBrake['extra_generations'] ?? 0),
            'alns_cooldown_brake_reason' => $cooldownBrake['reason'] ?? null,
            'alns_generations_since_last_trigger' => $generationsSinceLastTrigger,
            'alns_landscape_pressure' => $landscapePressure,
            'alns_trigger_eligible' => $eligible,
            'alns_triggered' => $triggered,
            'alns_trigger_reason' => $triggered
                ? implode('+', $triggerSources)
                : ($cooldownSatisfied
                    ? ($landscapePressure ? 'waiting_interval' : 'not_due')
                    : (($cooldownBrake['applied'] ?? false) ? 'cooldown_recent_low_return' : 'cooldown')),
            'alns_trigger_sources' => $triggerSources,
            'alns_recent_effectiveness_sample_size' => (int) ($recentEffectiveness['sample_size'] ?? 0),
            'alns_recent_effectiveness_mean_improvement' => (float) ($recentEffectiveness['mean_improvement'] ?? 0.0),
            'alns_recent_effectiveness_success_rate' => (float) ($recentEffectiveness['success_rate'] ?? 0.0),
            'alns_real_activation_enabled' => $realActivation['enabled'] ?? false,
            'alns_real_activation_requested' => $realActivation['requested'] ?? false,
            'alns_real_activation_applied' => $realActivation['applied'] ?? false,
            'alns_real_activation_policy' => $realActivation['policy'] ?? null,
            'alns_real_activation_mode' => $realActivation['mode'] ?? 'diagnostic_only',
            'alns_real_activation_cooldown' => $realActivation['cooldown_generations'] ?? null,
            'alns_real_activation_reason' => $realActivation['reason'] ?? null,
        ];
    }

    private function budgetAwareLnsFrequency(int $maxGenerations): int
    {
        $targetTriggers = match (true) {
            $maxGenerations <= 10 => 2,
            $maxGenerations <= 20 => 3,
            $maxGenerations <= 40 => 4,
            $maxGenerations <= 80 => 5,
            default => 6,
        };

        return max(2, (int) ceil($maxGenerations / ($targetTriggers + 1)));
    }

    /**
     * @param array<string, float|int> $recentEffectiveness
     * @return array{applied: bool, extra_generations: int, reason: ?string}
     */
    private function adaptiveAlnsCooldownBrake(array $recentEffectiveness): array
    {
        if (! (bool) config('ag.alns_adaptive.enabled', true)) {
            return [
                'applied' => false,
                'extra_generations' => 0,
                'reason' => null,
            ];
        }

        $sampleSize = (int) ($recentEffectiveness['sample_size'] ?? 0);
        $meanImprovement = (float) ($recentEffectiveness['mean_improvement'] ?? 0.0);
        $successRate = (float) ($recentEffectiveness['success_rate'] ?? 0.0);
        $minSampleSize = max(1, (int) config('ag.alns_adaptive.cooldown_brake_min_sample_size', 3));
        $negativeImprovement = (float) config('ag.alns_adaptive.cooldown_brake_negative_improvement', -5.0);
        $nonPositiveImprovement = (float) config('ag.alns_adaptive.cooldown_brake_non_positive_improvement', 0.0);
        $veryLowSuccessRate = (float) config('ag.alns_adaptive.cooldown_brake_very_low_success_rate', 0.15);
        $lowSuccessRate = (float) config('ag.alns_adaptive.cooldown_brake_low_success_rate', 0.34);
        $extraGenerationsNegative = max(0, (int) config('ag.alns_adaptive.cooldown_brake_extra_generations_negative_return', 3));
        $extraGenerationsLowReturn = max(0, (int) config('ag.alns_adaptive.cooldown_brake_extra_generations_low_return', 2));

        if ($sampleSize < $minSampleSize) {
            return [
                'applied' => false,
                'extra_generations' => 0,
                'reason' => null,
            ];
        }

        if ($meanImprovement <= $negativeImprovement || ($meanImprovement <= $nonPositiveImprovement && $successRate <= $veryLowSuccessRate)) {
            return [
                'applied' => true,
                'extra_generations' => $extraGenerationsNegative,
                'reason' => 'Recent ALNS outcomes are consistently negative or null.',
            ];
        }

        if ($meanImprovement <= $nonPositiveImprovement || $successRate <= $lowSuccessRate) {
            return [
                'applied' => true,
                'extra_generations' => $extraGenerationsLowReturn,
                'reason' => 'Recent ALNS outcomes show low return for the current search pattern.',
            ];
        }

        return [
            'applied' => false,
            'extra_generations' => 0,
            'reason' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $landscapeObservation
     */
    private function landscapeAwareLnsFrequency(
        int $budgetFrequency,
        ?string $landscapeState,
        ?array $landscapeObservation,
        bool $activateDynamicLns,
    ): ?int {
        if (! (bool) config('ag.alns_adaptive.enabled', true)) {
            return null;
        }

        if (! $this->hasLandscapePressure($landscapeState, $landscapeObservation, $activateDynamicLns)) {
            return null;
        }

        $divisor = max(1, (int) config('ag.alns_adaptive.landscape_frequency_divisor', 2));

        return max(2, (int) ceil($budgetFrequency / $divisor));
    }

    /**
     * @param array<string, mixed>|null $landscapeObservation
     */
    private function hasLandscapePressure(
        ?string $landscapeState,
        ?array $landscapeObservation,
        bool $activateDynamicLns,
    ): bool {
        if ($activateDynamicLns) {
            return true;
        }

        if (in_array($landscapeState, ['plateau', 'premature_convergence'], true)) {
            return true;
        }

        if (! is_array($landscapeObservation)) {
            return false;
        }

        return (bool) ($landscapeObservation['basin_of_attraction_lock_detected'] ?? false)
            || in_array($landscapeObservation['phenomenon'] ?? null, ['deep_valley', 'local_minimum'], true);
    }

    /**
     * @param array<string, mixed> $alnsTrigger
     * @return array<string, mixed>
     */
    private function alnsTriggerObservationPayload(array $alnsTrigger): array
    {
        return [
            'base_frequency' => $alnsTrigger['alns_base_frequency'] ?? null,
            'budget_frequency' => $alnsTrigger['alns_budget_frequency'] ?? null,
            'landscape_frequency' => $alnsTrigger['alns_landscape_frequency'] ?? null,
            'effective_frequency' => $alnsTrigger['alns_effective_frequency'] ?? null,
            'base_cooldown_generations' => $alnsTrigger['alns_base_cooldown_generations'] ?? null,
            'cooldown_generations' => $alnsTrigger['alns_cooldown_generations'] ?? null,
            'cooldown_brake' => [
                'applied' => $alnsTrigger['alns_cooldown_brake_applied'] ?? false,
                'extra_generations' => $alnsTrigger['alns_cooldown_brake_extra_generations'] ?? 0,
                'reason' => $alnsTrigger['alns_cooldown_brake_reason'] ?? null,
            ],
            'generations_since_last_trigger' => $alnsTrigger['alns_generations_since_last_trigger'] ?? null,
            'landscape_pressure' => $alnsTrigger['alns_landscape_pressure'] ?? false,
            'eligible' => $alnsTrigger['alns_trigger_eligible'] ?? false,
            'triggered' => $alnsTrigger['alns_triggered'] ?? false,
            'reason' => $alnsTrigger['alns_trigger_reason'] ?? null,
            'sources' => $alnsTrigger['alns_trigger_sources'] ?? [],
            'recent_effectiveness' => [
                'sample_size' => $alnsTrigger['alns_recent_effectiveness_sample_size'] ?? 0,
                'mean_improvement' => $alnsTrigger['alns_recent_effectiveness_mean_improvement'] ?? 0.0,
                'success_rate' => $alnsTrigger['alns_recent_effectiveness_success_rate'] ?? 0.0,
            ],
            'real_activation' => [
                'enabled' => $alnsTrigger['alns_real_activation_enabled'] ?? false,
                'requested' => $alnsTrigger['alns_real_activation_requested'] ?? false,
                'applied' => $alnsTrigger['alns_real_activation_applied'] ?? false,
                'policy' => $alnsTrigger['alns_real_activation_policy'] ?? null,
                'mode' => $alnsTrigger['alns_real_activation_mode'] ?? 'diagnostic_only',
                'cooldown_generations' => $alnsTrigger['alns_real_activation_cooldown'] ?? null,
                'reason' => $alnsTrigger['alns_real_activation_reason'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $landscapeObservation
     * @return array<string, mixed>
     */
    private function resolveRealAlnsActivation(
        ?array $landscapeObservation,
        ?int $generationsSinceLastTrigger,
        bool $eligible,
    ): array {
        $enabled = (bool) config('ag.search_response_activation.enable_temporary_intensive_alns', false);
        $cooldown = max(1, (int) config('ag.search_response_activation.temporary_intensive_alns_cooldown', 2));
        $requested = false;
        $applied = false;
        $policy = null;
        $mode = 'diagnostic_only';
        $reason = $enabled
            ? 'Activation gate did not request temporary intensive ALNS for the current observation.'
            : 'Temporary intensive ALNS is disabled by configuration.';

        if (! is_array($landscapeObservation)) {
            return [
                'enabled' => $enabled,
                'requested' => false,
                'applied' => false,
                'policy' => null,
                'mode' => $mode,
                'cooldown_generations' => $cooldown,
                'reason' => $reason,
            ];
        }

        $activationGate = is_array($landscapeObservation['search_response_activation_gate'] ?? null)
            ? $landscapeObservation['search_response_activation_gate']
            : [];
        $simulation = is_array($landscapeObservation['search_response_simulation'] ?? null)
            ? $landscapeObservation['search_response_simulation']
            : [];

        $policy = $activationGate['candidate_policy'] ?? null;
        $policyAligned = $policy !== null && $policy === ($simulation['policy'] ?? null);
        $simulationRequestsAlns = (bool) ($simulation['activate_alns'] ?? false)
            && (bool) ($simulation['would_escalate'] ?? false);

        $requested = $enabled
            && (bool) ($activationGate['eligible_as_candidate'] ?? false)
            && $policyAligned
            && $simulationRequestsAlns;

        if (! $requested) {
            if (! $enabled) {
                $reason = 'Temporary intensive ALNS is disabled by configuration.';
            } elseif (! (bool) ($activationGate['eligible_as_candidate'] ?? false)) {
                $reason = 'Activation gate has not approved a real candidate policy yet.';
            } elseif (! $policyAligned) {
                $reason = 'Current simulated policy does not match the activation gate candidate.';
            } else {
                $reason = 'Current simulated response does not request ALNS activation.';
            }

            return [
                'enabled' => $enabled,
                'requested' => false,
                'applied' => false,
                'policy' => $policy,
                'mode' => $activationGate['mode'] ?? $mode,
                'cooldown_generations' => $cooldown,
                'reason' => $reason,
            ];
        }

        $cooldownSatisfied = $generationsSinceLastTrigger === null || $generationsSinceLastTrigger >= $cooldown;
        $applied = $eligible && $cooldownSatisfied;
        $mode = 'opt_in';
        $reason = $applied
            ? 'Temporary intensive ALNS activated via SearchResponseActivationGate.'
            : 'Temporary intensive ALNS is waiting for its short cooldown window.';

        return [
            'enabled' => $enabled,
            'requested' => $requested,
            'applied' => $applied,
            'policy' => $policy,
            'mode' => $mode,
            'cooldown_generations' => $cooldown,
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string, mixed>|null $landscapeObservation
     * @return array<string, mixed>
     */
    private function resolveRealMutationShockActivation(
        int $generation,
        ?array $landscapeObservation,
        bool $eligible,
    ): array {
        $enabled = (bool) config('ag.search_response_activation.enable_temporary_mutation_shock', false);
        $cooldown = max(1, (int) config('ag.search_response_activation.temporary_mutation_shock_cooldown', 2));
        $duration = max(1, (int) config('ag.search_response_activation.temporary_mutation_shock_duration', 2));
        $requested = false;
        $applied = false;
        $policy = null;
        $mode = 'diagnostic_only';
        $multiplier = 1.0;
        $reason = $enabled
            ? 'Activation gate did not request a temporary mutation shock for the current observation.'
            : 'Temporary mutation shock is disabled by configuration.';

        if (! is_array($landscapeObservation)) {
            return [
                'mutation_shock_trigger_eligible' => $enabled,
                'mutation_shock_enabled' => $enabled,
                'mutation_shock_requested' => false,
                'mutation_shock_applied' => false,
                'mutation_shock_policy' => null,
                'mutation_shock_mode' => $mode,
                'mutation_shock_cooldown_generations' => $cooldown,
                'mutation_shock_duration_generations' => $duration,
                'mutation_shock_multiplier' => $multiplier,
                'mutation_shock_reason' => $reason,
            ];
        }

        $activationGate = is_array($landscapeObservation['search_response_activation_gate'] ?? null)
            ? $landscapeObservation['search_response_activation_gate']
            : [];
        $simulation = is_array($landscapeObservation['search_response_simulation'] ?? null)
            ? $landscapeObservation['search_response_simulation']
            : [];

        $policy = $activationGate['candidate_policy'] ?? null;
        $policyAligned = $policy !== null && $policy === ($simulation['policy'] ?? null);
        $multiplier = max(1.0, (float) ($simulation['mutation_multiplier'] ?? 1.0));
        $simulationRequestsShock = (bool) ($simulation['would_escalate'] ?? false) && $multiplier > 1.0;

        $requested = $enabled
            && (bool) ($activationGate['eligible_as_candidate'] ?? false)
            && $policyAligned
            && $simulationRequestsShock;

        if (! $requested) {
            if (! $enabled) {
                $reason = 'Temporary mutation shock is disabled by configuration.';
            } elseif (! (bool) ($activationGate['eligible_as_candidate'] ?? false)) {
                $reason = 'Activation gate has not approved a real candidate policy yet.';
            } elseif (! $policyAligned) {
                $reason = 'Current simulated policy does not match the activation gate candidate.';
            } else {
                $reason = 'Current simulated response does not request a mutation shock.';
            }

            return [
                'mutation_shock_trigger_eligible' => $enabled,
                'mutation_shock_enabled' => $enabled,
                'mutation_shock_requested' => false,
                'mutation_shock_applied' => false,
                'mutation_shock_policy' => $policy,
                'mutation_shock_mode' => $activationGate['mode'] ?? $mode,
                'mutation_shock_cooldown_generations' => $cooldown,
                'mutation_shock_duration_generations' => $duration,
                'mutation_shock_multiplier' => $multiplier,
                'mutation_shock_reason' => $reason,
            ];
        }

        $generationsSinceLastShock = $this->lastMutationShockGeneration === null
            ? null
            : $generation - $this->lastMutationShockGeneration;

        $cooldownSatisfied = $generationsSinceLastShock === null || $generationsSinceLastShock >= $cooldown;
        $applied = $eligible && $cooldownSatisfied;
        $mode = 'opt_in';
        $reason = $applied
            ? 'Temporary mutation shock armed via SearchResponseActivationGate.'
            : 'Temporary mutation shock is waiting for its short cooldown window.';

        if ($applied) {
            $this->activeMutationShock = [
                'policy' => $policy,
                'multiplier' => $multiplier,
                'remaining_generations' => $duration,
                'duration_generations' => $duration,
                'activated_generation' => $generation,
                'reason' => $reason,
            ];
            $this->lastMutationShockGeneration = $generation;

            Log::info('ga.mutation_shock.armed', [
                'execution_id' => $this->executionMetrics?->getExecutionId(),
                'generation' => $generation,
                'policy' => $policy,
                'multiplier' => round($multiplier, 6),
                'duration_generations' => $duration,
                'cooldown_generations' => $cooldown,
            ]);
        }

        return [
            'mutation_shock_trigger_eligible' => $enabled,
            'mutation_shock_enabled' => $enabled,
            'mutation_shock_requested' => $requested,
            'mutation_shock_applied' => $applied,
            'mutation_shock_policy' => $policy,
            'mutation_shock_mode' => $mode,
            'mutation_shock_cooldown_generations' => $cooldown,
            'mutation_shock_duration_generations' => $duration,
            'mutation_shock_multiplier' => $multiplier,
            'mutation_shock_effective_from_generation' => $applied ? $generation + 1 : null,
            'mutation_shock_reason' => $reason,
        ];
    }

    /**
     * @param array<string, mixed> $mutationShockActivation
     * @param array<string, mixed> $populationTrajectory
     * @return array<string, mixed>
     */
    private function mutationShockObservationPayload(array $mutationShockActivation, array $populationTrajectory = []): array
    {
        return [
            'enabled' => $mutationShockActivation['mutation_shock_enabled'] ?? false,
            'requested' => $mutationShockActivation['mutation_shock_requested'] ?? false,
            'applied' => $mutationShockActivation['mutation_shock_applied'] ?? false,
            'policy' => $mutationShockActivation['mutation_shock_policy'] ?? null,
            'mode' => $mutationShockActivation['mutation_shock_mode'] ?? 'diagnostic_only',
            'cooldown_generations' => $mutationShockActivation['mutation_shock_cooldown_generations'] ?? null,
            'duration_generations' => $mutationShockActivation['mutation_shock_duration_generations'] ?? null,
            'multiplier' => isset($mutationShockActivation['mutation_shock_multiplier'])
                ? round((float) $mutationShockActivation['mutation_shock_multiplier'], 6)
                : null,
            'effective_from_generation' => $mutationShockActivation['mutation_shock_effective_from_generation'] ?? null,
            'reason' => $mutationShockActivation['mutation_shock_reason'] ?? null,
            'active' => $populationTrajectory['mutation_shock_active'] ?? false,
            'active_multiplier' => isset($populationTrajectory['mutation_shock_multiplier'])
                ? round((float) $populationTrajectory['mutation_shock_multiplier'], 6)
                : null,
            'remaining_generations_before' => $populationTrajectory['mutation_shock_remaining_generations_before'] ?? null,
            'remaining_generations_after' => $populationTrajectory['mutation_shock_remaining_generations_after'] ?? null,
            'activated_generation' => $populationTrajectory['mutation_shock_activated_generation'] ?? null,
            'base_rate' => isset($populationTrajectory['mutation_rate_base'])
                ? round((float) $populationTrajectory['mutation_rate_base'], 6)
                : null,
            'effective_rate' => isset($populationTrajectory['mutation_rate_effective'])
                ? round((float) $populationTrajectory['mutation_rate_effective'], 6)
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $selectionPressureTelemetry
     * @param array<string, mixed> $selectionPressureActivation
     * @param array<string, mixed> $populationTrajectory
     * @return array<string, mixed>
     */
    private function selectionPressureObservationPayload(
        array $selectionPressureTelemetry,
        array $selectionPressureActivation,
        array $populationTrajectory = [],
    ): array {
        return [
            'supported' => $selectionPressureTelemetry['selection_pressure_supported'] ?? false,
            'source' => $populationTrajectory['selection_pressure_source']
                ?? $selectionPressureTelemetry['selection_pressure_source']
                ?? 'landscape_response',
            'landscape_state' => $selectionPressureTelemetry['selection_pressure_landscape_state'] ?? null,
            'base_multiplier' => isset($populationTrajectory['selection_pressure_base_multiplier'])
                ? round((float) $populationTrajectory['selection_pressure_base_multiplier'], 6)
                : (isset($selectionPressureTelemetry['selection_pressure_multiplier'])
                    ? round((float) $selectionPressureTelemetry['selection_pressure_multiplier'], 6)
                    : null),
            'effective_multiplier' => isset($populationTrajectory['selection_pressure_effective_multiplier'])
                ? round((float) $populationTrajectory['selection_pressure_effective_multiplier'], 6)
                : (isset($selectionPressureTelemetry['selection_pressure_multiplier'])
                    ? round((float) $selectionPressureTelemetry['selection_pressure_multiplier'], 6)
                    : null),
            'state' => $populationTrajectory['selection_pressure_state']
                ?? $selectionPressureTelemetry['selection_pressure_state']
                ?? 'nominal',
            'base_tournament_size' => $selectionPressureTelemetry['selection_pressure_base_tournament_size'] ?? null,
            'effective_tournament_size' => $populationTrajectory['selection_pressure_effective_tournament_size']
                ?? $selectionPressureTelemetry['selection_pressure_effective_tournament_size']
                ?? null,
            'real_reduction' => [
                'enabled' => $selectionPressureActivation['selection_pressure_real_enabled'] ?? false,
                'requested' => $selectionPressureActivation['selection_pressure_real_requested'] ?? false,
                'applied' => $selectionPressureActivation['selection_pressure_real_applied'] ?? false,
                'policy' => $selectionPressureActivation['selection_pressure_real_policy'] ?? null,
                'mode' => $selectionPressureActivation['selection_pressure_real_mode'] ?? 'diagnostic_only',
                'cooldown_generations' => $selectionPressureActivation['selection_pressure_real_cooldown_generations'] ?? null,
                'duration_generations' => $selectionPressureActivation['selection_pressure_real_duration_generations'] ?? null,
                'multiplier' => isset($selectionPressureActivation['selection_pressure_real_multiplier'])
                    ? round((float) $selectionPressureActivation['selection_pressure_real_multiplier'], 6)
                    : null,
                'effective_from_generation' => $selectionPressureActivation['selection_pressure_real_effective_from_generation'] ?? null,
                'reason' => $selectionPressureActivation['selection_pressure_real_reason'] ?? null,
            ],
            'reduction_active' => $populationTrajectory['selection_pressure_reduction_active'] ?? false,
            'reduction_multiplier' => isset($populationTrajectory['selection_pressure_reduction_multiplier'])
                ? round((float) $populationTrajectory['selection_pressure_reduction_multiplier'], 6)
                : null,
            'reduction_policy' => $populationTrajectory['selection_pressure_reduction_policy'] ?? null,
            'reduction_reason' => $populationTrajectory['selection_pressure_reduction_reason'] ?? null,
            'reduction_activated_generation' => $populationTrajectory['selection_pressure_reduction_activated_generation'] ?? null,
            'remaining_generations_before' => $populationTrajectory['selection_pressure_reduction_remaining_generations_before'] ?? null,
            'remaining_generations_after' => $populationTrajectory['selection_pressure_reduction_remaining_generations_after'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $landscapeObservation
     * @param array<string, mixed> $alnsTrigger
     * @param array<string, mixed> $alnsTelemetry
     * @return array<string, mixed>
     */
    private function mergeAlnsObservationTelemetry(array $landscapeObservation, array $alnsTrigger, array $alnsTelemetry): array
    {
        $existingTrigger = is_array($landscapeObservation['alns_trigger'] ?? null)
            ? $landscapeObservation['alns_trigger']
            : $this->alnsTriggerObservationPayload($alnsTrigger);

        $profile = is_array($alnsTelemetry['alns_intensity_profile'] ?? null)
            ? $alnsTelemetry['alns_intensity_profile']
            : [];

        $recentEffectiveness = is_array($alnsTelemetry['alns_recent_effectiveness'] ?? null)
            ? $alnsTelemetry['alns_recent_effectiveness']
            : [];

        $existingTrigger['response'] = [
            'destroy_operator' => $alnsTelemetry['alns_destroy_operator'] ?? null,
            'repair_operator' => $alnsTelemetry['alns_repair_operator'] ?? null,
            'improvement' => isset($alnsTelemetry['alns_improvement'])
                ? round((float) $alnsTelemetry['alns_improvement'], 6)
                : null,
            'accepted_improvement' => isset($alnsTelemetry['alns_accepted_improvement'])
                ? round((float) $alnsTelemetry['alns_accepted_improvement'], 6)
                : null,
            'accepted' => $alnsTelemetry['alns_accepted'] ?? null,
            'acceptance_policy' => $alnsTelemetry['alns_acceptance_policy'] ?? null,
            'acceptance_reason' => $alnsTelemetry['alns_acceptance_reason'] ?? null,
            'reward' => isset($alnsTelemetry['alns_reward'])
                ? round((float) $alnsTelemetry['alns_reward'], 6)
                : null,
            'base_hard_penalty' => isset($alnsTelemetry['alns_base_hard_penalty'])
                ? round((float) $alnsTelemetry['alns_base_hard_penalty'], 6)
                : null,
            'candidate_hard_penalty' => isset($alnsTelemetry['alns_candidate_hard_penalty'])
                ? round((float) $alnsTelemetry['alns_candidate_hard_penalty'], 6)
                : null,
            'base_soft_penalty' => isset($alnsTelemetry['alns_base_soft_penalty'])
                ? round((float) $alnsTelemetry['alns_base_soft_penalty'], 6)
                : null,
            'candidate_soft_penalty' => isset($alnsTelemetry['alns_candidate_soft_penalty'])
                ? round((float) $alnsTelemetry['alns_candidate_soft_penalty'], 6)
                : null,
            'aggression_label' => $profile['aggression_label'] ?? null,
            'intensity' => isset($profile['intensity']) ? round((float) $profile['intensity'], 4) : null,
            'destroy_ratio' => isset($profile['destroy_ratio']) ? round((float) $profile['destroy_ratio'], 4) : null,
            'repair_intensity' => isset($profile['repair_intensity']) ? round((float) $profile['repair_intensity'], 4) : null,
            'target_removed_genes' => isset($profile['target_removed_genes']) ? (int) $profile['target_removed_genes'] : null,
            'removed_genes' => isset($alnsTelemetry['alns_removed_genes']) ? (int) $alnsTelemetry['alns_removed_genes'] : null,
            'remaining_assigned_genes' => isset($alnsTelemetry['alns_remaining_assigned_genes'])
                ? (int) $alnsTelemetry['alns_remaining_assigned_genes']
                : null,
            'recent_mean_improvement' => isset($recentEffectiveness['mean_improvement'])
                ? round((float) $recentEffectiveness['mean_improvement'], 4)
                : null,
            'recent_success_rate' => isset($recentEffectiveness['success_rate'])
                ? round((float) $recentEffectiveness['success_rate'], 4)
                : null,
            'recent_sample_size' => isset($recentEffectiveness['sample_size'])
                ? (int) $recentEffectiveness['sample_size']
                : null,
            'reasons' => is_array($profile['reasons'] ?? null) ? $profile['reasons'] : [],
        ];

        $landscapeObservation['alns_trigger'] = $existingTrigger;

        return $landscapeObservation;
    }

    /**
     * @param Cromossomo[] $population
     * @return array{
     *     population: array<int, Cromossomo>,
     *     mutation_rate: float,
     *     operator_used: string,
     *     operator_reward: float,
     *     diversity: float,
     *     entropy: float,
     *     improvement_acceptance_rate: float,
     *     worsening_acceptance_rate: float,
     *     population_turnover: float,
     *     best_signature_changed: bool,
     *     elite_similarity: float,
     *     best_signature: string
     * }
     */
    private function executeGenerationStep(array $population, int $populationSize): array
    {
        $generation = $this->evolutionGeneration;
        $stagnationBurst = $this->stagnationBurstState();
        $entropy = $this->metrics->lastEntropy();
        $diversity = $this->metrics->lastDiversity();
        $baseMutationRate = $this->adaptiveMutation->computeRate($entropy, $diversity);

        if (($stagnationBurst['active'] ?? false) === true) {
            $baseMutationRate = max(
                0.001,
                min(0.9, $baseMutationRate * (float) ($stagnationBurst['mutation_multiplier'] ?? 1.0)),
            );
        }

        $mutationShock = $this->consumeActiveMutationShock($baseMutationRate, $generation);
        $mutationRate = $mutationShock['mutation_rate'];

        $selectionPressureMultiplier = $this->currentSelectionPressureMultiplier;

        if (($stagnationBurst['active'] ?? false) === true) {
            $selectionPressureMultiplier = min(
                max(0.34, $selectionPressureMultiplier),
                max(0.34, min(1.0, $selectionPressureMultiplier * (float) ($stagnationBurst['selection_pressure_multiplier'] ?? 1.0))),
            );
        }

        $selectionPressure = $this->consumeActiveSelectionPressureReduction(
            $selectionPressureMultiplier,
            $generation,
        );
        $generationStartedAt = microtime(true);

        if ($this->mutation instanceof DiversityAwareMutationInterface) {
            $this->mutation->setDiversity($diversity);
        }

        $newPopulation = [];
        $allRewards = [];
        $operatorUsed = null;

        foreach ($this->elitism->selectElites($population) as $elite) {
            $newPopulation[] = $elite;
        }

        $this->reportOperationalHeartbeat(
            generation: $generation,
            stage: 'generation_started',
            operation: 'Preparando nova geracao',
            operationStartedAt: $generationStartedAt,
            context: [
                'population_target' => $populationSize,
                'offspring_built' => count($newPopulation),
                'mutation_rate' => $mutationRate,
                'mutation_rate_base' => $baseMutationRate,
                'mutation_shock_active' => $mutationShock['telemetry']['mutation_shock_active'] ?? false,
                'selection_pressure_multiplier' => $selectionPressure['telemetry']['selection_pressure_effective_multiplier'] ?? 1.0,
                'selection_tournament_size' => $selectionPressure['telemetry']['selection_pressure_effective_tournament_size'] ?? null,
            ],
            force: true,
        );

        while (count($newPopulation) < $populationSize) {
            $this->assertNotCancelled();

            $parentA = $this->selection->select($population);
            $parentB = $this->selection->select($population);

            [$childA, $childB] = $this->crossover->crossover($parentA, $parentB);

            $childAResult = $this->evolveChild($childA, $mutationRate);
            $childBResult = $this->evolveChild($childB, $mutationRate);

            $childA = $childAResult['child'];
            $childB = $childBResult['child'];

            if ($childAResult['operator_name'] !== null) {
                $operatorUsed = $childAResult['operator_name'];
            }

            if ($childBResult['operator_name'] !== null) {
                $operatorUsed = $childBResult['operator_name'];
            }

            $allRewards[] = $childAResult['reward'];
            $allRewards[] = $childBResult['reward'];

            $newPopulation[] = $childA;

            if (count($newPopulation) < $populationSize) {
                $newPopulation[] = $childB;
            }

            $this->reportOperationalHeartbeat(
                generation: $generation,
                stage: 'building_offspring',
                operation: 'Montando descendentes da geracao',
                operationStartedAt: $generationStartedAt,
                context: [
                    'population_target' => $populationSize,
                    'offspring_built' => count($newPopulation),
                    'last_operator_used' => $operatorUsed ?? 'none',
                    'mutation_rate' => $mutationRate,
                    'mutation_rate_base' => $baseMutationRate,
                    'mutation_shock_active' => $mutationShock['telemetry']['mutation_shock_active'] ?? false,
                    'selection_pressure_multiplier' => $selectionPressure['telemetry']['selection_pressure_effective_multiplier'] ?? 1.0,
                    'selection_tournament_size' => $selectionPressure['telemetry']['selection_pressure_effective_tournament_size'] ?? null,
                ],
            );
        }

        $evaluationStartedAt = microtime(true);

        $this->reportOperationalHeartbeat(
            generation: $generation,
            stage: 'evaluating_population',
            operation: 'Avaliando a nova populacao da geracao',
            operationStartedAt: $evaluationStartedAt,
            context: [
                'population_target' => $populationSize,
                'offspring_built' => count($newPopulation),
            ],
            force: true,
        );

        $evaluationContext = [
            'population_target' => $populationSize,
            'offspring_built' => count($newPopulation),
        ];

        $this->reportEvaluationWatchdog(
            generation: $generation,
            operationStartedAt: $evaluationStartedAt,
            substage: 'trajectory_signals_started',
            context: $evaluationContext,
            force: true,
        );

        $trajectorySignals = $this->calculateTrajectorySignals(
            previousPopulation: $population,
            newPopulation: $newPopulation,
            rewards: $allRewards,
            generation: $generation,
            operationStartedAt: $evaluationStartedAt,
            evaluationContext: $evaluationContext,
        );

        $this->reportEvaluationWatchdog(
            generation: $generation,
            operationStartedAt: $evaluationStartedAt,
            substage: 'trajectory_signals_completed',
            context: $evaluationContext,
            force: true,
        );

        return [
            'population' => $newPopulation,
            'mutation_rate' => $mutationRate,
            'operator_used' => $operatorUsed ?? 'none',
            'operator_reward' => $this->summarizeOperatorReward($allRewards),
            'diversity' => $diversity,
            'entropy' => $entropy,
            'stagnation_burst_active' => $stagnationBurst['active'] ?? false,
            'stagnation_burst_force_alns' => $stagnationBurst['force_alns'] ?? false,
            'stagnation_burst_reason' => $stagnationBurst['reason'] ?? null,
            'stagnation_burst_remaining_generations_before' => $stagnationBurst['remaining_generations'] ?? null,
            'stagnation_burst_mutation_multiplier' => $stagnationBurst['mutation_multiplier'] ?? null,
            'stagnation_burst_selection_pressure_multiplier' => $stagnationBurst['selection_pressure_multiplier'] ?? null,
            'evaluation_started_at' => $evaluationStartedAt,
        ] + $trajectorySignals + $mutationShock['telemetry'] + $selectionPressure['telemetry'];
    }

    /**
     * @return array<string, mixed>
     */
    private function stagnationBurstState(): array
    {
        if (! is_array($this->activeStagnationBurst)) {
            return [
                'active' => false,
            ];
        }

        $remaining = (int) ($this->activeStagnationBurst['remaining_generations'] ?? 0);

        if ($remaining <= 0) {
            return [
                'active' => false,
            ];
        }

        return [
            'active' => true,
            'remaining_generations' => $remaining,
            'mutation_multiplier' => (float) ($this->activeStagnationBurst['mutation_multiplier'] ?? 1.0),
            'selection_pressure_multiplier' => (float) ($this->activeStagnationBurst['selection_pressure_multiplier'] ?? 1.0),
            'force_alns' => (bool) ($this->activeStagnationBurst['force_alns'] ?? false),
            'reason' => (string) ($this->activeStagnationBurst['reason'] ?? 'stagnation_detected'),
        ];
    }

    private function consumeStagnationBurstGeneration(): void
    {
        if (! is_array($this->activeStagnationBurst)) {
            return;
        }

        $remaining = max(0, (int) ($this->activeStagnationBurst['remaining_generations'] ?? 0) - 1);

        if ($remaining <= 0) {
            $this->activeStagnationBurst = null;

            return;
        }

        $this->activeStagnationBurst['remaining_generations'] = $remaining;
    }

    /**
     * @return array{mutation_rate: float, telemetry: array<string, mixed>}
     */
    private function consumeActiveMutationShock(float $baseMutationRate, int $generation): array
    {
        if (
            ! is_array($this->activeMutationShock)
            || ((int) ($this->activeMutationShock['remaining_generations'] ?? 0)) <= 0
        ) {
            return [
                'mutation_rate' => $baseMutationRate,
                'telemetry' => [
                    'mutation_rate_base' => $baseMutationRate,
                    'mutation_shock_active' => false,
                ],
            ];
        }

        $shock = $this->activeMutationShock;
        $multiplier = max(1.0, (float) ($shock['multiplier'] ?? 1.0));
        $remainingBefore = max(0, (int) ($shock['remaining_generations'] ?? 0));
        $effectiveMutationRate = max(0.001, min($baseMutationRate * $multiplier, 0.9));

        $shock['remaining_generations'] = max(0, $remainingBefore - 1);
        $remainingAfter = (int) $shock['remaining_generations'];
        $this->activeMutationShock = $remainingAfter > 0 ? $shock : null;

        return [
            'mutation_rate' => $effectiveMutationRate,
            'telemetry' => [
                'mutation_rate_base' => $baseMutationRate,
                'mutation_rate_effective' => $effectiveMutationRate,
                'mutation_shock_active' => true,
                'mutation_shock_multiplier' => $multiplier,
                'mutation_shock_policy' => $shock['policy'] ?? null,
                'mutation_shock_reason' => $shock['reason'] ?? null,
                'mutation_shock_activated_generation' => $shock['activated_generation'] ?? $generation,
                'mutation_shock_duration_generations' => $shock['duration_generations'] ?? null,
                'mutation_shock_remaining_generations_before' => $remainingBefore,
                'mutation_shock_remaining_generations_after' => $remainingAfter,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applySelectionPressureMultiplier(
        float $multiplier,
        string $source = 'landscape_response',
        ?string $landscapeState = null,
    ): array {
        $normalizedMultiplier = max(0.34, min($multiplier, 3.0));
        $this->currentSelectionPressureMultiplier = $normalizedMultiplier;

        if ($this->selection instanceof AdaptiveSelectionPressureInterface) {
            $this->selection->applySelectionPressureMultiplier($normalizedMultiplier);

            return $this->selection->selectionPressureTelemetry() + [
                'selection_pressure_source' => $source,
                'selection_pressure_landscape_state' => $landscapeState,
            ];
        }

        return [
            'selection_pressure_supported' => false,
            'selection_pressure_multiplier' => round($normalizedMultiplier, 6),
            'selection_pressure_source' => $source,
            'selection_pressure_landscape_state' => $landscapeState,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function selectionPressureTelemetry(): array
    {
        if ($this->selection instanceof AdaptiveSelectionPressureInterface) {
            return $this->selection->selectionPressureTelemetry() + [
                'selection_pressure_source' => 'default',
                'selection_pressure_landscape_state' => null,
            ];
        }

        return [
            'selection_pressure_supported' => false,
            'selection_pressure_multiplier' => round($this->currentSelectionPressureMultiplier, 6),
            'selection_pressure_source' => 'default',
            'selection_pressure_landscape_state' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $landscapeObservation
     * @return array<string, mixed>
     */
    private function resolveRealSelectionPressureReductionActivation(
        int $generation,
        ?array $landscapeObservation,
        bool $eligible,
    ): array {
        $enabled = (bool) config('ag.search_response_activation.enable_temporary_selection_pressure_reduction', false);
        $cooldown = max(1, (int) config('ag.search_response_activation.temporary_selection_pressure_reduction_cooldown', 2));
        $duration = max(1, (int) config('ag.search_response_activation.temporary_selection_pressure_reduction_duration', 2));
        $requested = false;
        $applied = false;
        $policy = null;
        $mode = 'diagnostic_only';
        $multiplier = 1.0;
        $reason = $enabled
            ? 'Activation gate did not request a temporary selection pressure reduction for the current observation.'
            : 'Temporary selection pressure reduction is disabled by configuration.';

        if (! is_array($landscapeObservation)) {
            return [
                'selection_pressure_trigger_eligible' => $enabled,
                'selection_pressure_real_enabled' => $enabled,
                'selection_pressure_real_requested' => false,
                'selection_pressure_real_applied' => false,
                'selection_pressure_real_policy' => null,
                'selection_pressure_real_mode' => $mode,
                'selection_pressure_real_cooldown_generations' => $cooldown,
                'selection_pressure_real_duration_generations' => $duration,
                'selection_pressure_real_multiplier' => $multiplier,
                'selection_pressure_real_reason' => $reason,
            ];
        }

        $activationGate = is_array($landscapeObservation['search_response_activation_gate'] ?? null)
            ? $landscapeObservation['search_response_activation_gate']
            : [];
        $simulation = is_array($landscapeObservation['search_response_simulation'] ?? null)
            ? $landscapeObservation['search_response_simulation']
            : [];

        $policy = $activationGate['candidate_policy'] ?? null;
        $policyAligned = $policy !== null && $policy === ($simulation['policy'] ?? null);
        $multiplier = max(0.34, min(1.0, (float) ($simulation['selection_pressure_multiplier'] ?? 1.0)));
        $simulationRequestsReduction = (bool) ($simulation['would_escalate'] ?? false) && $multiplier < 1.0;

        $requested = $enabled
            && (bool) ($activationGate['eligible_as_candidate'] ?? false)
            && $policyAligned
            && $simulationRequestsReduction;

        if (! $requested) {
            if (! $enabled) {
                $reason = 'Temporary selection pressure reduction is disabled by configuration.';
            } elseif (! (bool) ($activationGate['eligible_as_candidate'] ?? false)) {
                $reason = 'Activation gate has not approved a real candidate policy yet.';
            } elseif (! $policyAligned) {
                $reason = 'Current simulated policy does not match the activation gate candidate.';
            } else {
                $reason = 'Current simulated response does not request a selection pressure reduction.';
            }

            return [
                'selection_pressure_trigger_eligible' => $enabled,
                'selection_pressure_real_enabled' => $enabled,
                'selection_pressure_real_requested' => false,
                'selection_pressure_real_applied' => false,
                'selection_pressure_real_policy' => $policy,
                'selection_pressure_real_mode' => $activationGate['mode'] ?? $mode,
                'selection_pressure_real_cooldown_generations' => $cooldown,
                'selection_pressure_real_duration_generations' => $duration,
                'selection_pressure_real_multiplier' => $multiplier,
                'selection_pressure_real_reason' => $reason,
            ];
        }

        $generationsSinceLastReduction = $this->lastSelectionPressureReductionGeneration === null
            ? null
            : $generation - $this->lastSelectionPressureReductionGeneration;

        $cooldownSatisfied = $generationsSinceLastReduction === null || $generationsSinceLastReduction >= $cooldown;
        $applied = $eligible && $cooldownSatisfied;
        $mode = 'opt_in';
        $reason = $applied
            ? 'Temporary selection pressure reduction armed via SearchResponseActivationGate.'
            : 'Temporary selection pressure reduction is waiting for its short cooldown window.';

        if ($applied) {
            $this->activeSelectionPressureReduction = [
                'policy' => $policy,
                'multiplier' => $multiplier,
                'remaining_generations' => $duration,
                'duration_generations' => $duration,
                'activated_generation' => $generation,
                'reason' => $reason,
            ];
            $this->lastSelectionPressureReductionGeneration = $generation;

            Log::info('ga.selection_pressure_reduction.armed', [
                'execution_id' => $this->executionMetrics?->getExecutionId(),
                'generation' => $generation,
                'policy' => $policy,
                'multiplier' => round($multiplier, 6),
                'duration_generations' => $duration,
                'cooldown_generations' => $cooldown,
            ]);
        }

        return [
            'selection_pressure_trigger_eligible' => $enabled,
            'selection_pressure_real_enabled' => $enabled,
            'selection_pressure_real_requested' => $requested,
            'selection_pressure_real_applied' => $applied,
            'selection_pressure_real_policy' => $policy,
            'selection_pressure_real_mode' => $mode,
            'selection_pressure_real_cooldown_generations' => $cooldown,
            'selection_pressure_real_duration_generations' => $duration,
            'selection_pressure_real_multiplier' => $multiplier,
            'selection_pressure_real_effective_from_generation' => $applied ? $generation + 1 : null,
            'selection_pressure_real_reason' => $reason,
        ];
    }

    /**
     * @return array{telemetry: array<string, mixed>}
     */
    private function consumeActiveSelectionPressureReduction(float $baseMultiplier, int $generation): array
    {
        if (
            ! is_array($this->activeSelectionPressureReduction)
            || ((int) ($this->activeSelectionPressureReduction['remaining_generations'] ?? 0)) <= 0
        ) {
            $telemetry = $this->applySelectionPressureMultiplier($baseMultiplier, 'landscape_response');

            return [
                'telemetry' => $telemetry + [
                    'selection_pressure_base_multiplier' => round($baseMultiplier, 6),
                    'selection_pressure_effective_multiplier' => round((float) ($telemetry['selection_pressure_multiplier'] ?? $baseMultiplier), 6),
                    'selection_pressure_reduction_active' => false,
                ],
            ];
        }

        $reduction = $this->activeSelectionPressureReduction;
        $multiplier = max(0.34, min(1.0, (float) ($reduction['multiplier'] ?? 1.0)));
        $remainingBefore = max(0, (int) ($reduction['remaining_generations'] ?? 0));
        $effectiveMultiplier = min(max(0.34, $baseMultiplier), $multiplier);
        $telemetry = $this->applySelectionPressureMultiplier($effectiveMultiplier, 'activation_gate', null);

        $reduction['remaining_generations'] = max(0, $remainingBefore - 1);
        $remainingAfter = (int) $reduction['remaining_generations'];
        $this->activeSelectionPressureReduction = $remainingAfter > 0 ? $reduction : null;

        return [
            'telemetry' => $telemetry + [
                'selection_pressure_base_multiplier' => round($baseMultiplier, 6),
                'selection_pressure_effective_multiplier' => round($effectiveMultiplier, 6),
                'selection_pressure_reduction_active' => true,
                'selection_pressure_reduction_multiplier' => round($multiplier, 6),
                'selection_pressure_reduction_policy' => $reduction['policy'] ?? null,
                'selection_pressure_reduction_reason' => $reduction['reason'] ?? null,
                'selection_pressure_reduction_activated_generation' => $reduction['activated_generation'] ?? $generation,
                'selection_pressure_reduction_duration_generations' => $reduction['duration_generations'] ?? null,
                'selection_pressure_reduction_remaining_generations_before' => $remainingBefore,
                'selection_pressure_reduction_remaining_generations_after' => $remainingAfter,
            ],
        ];
    }

    /**
     * @return array{
     *     child: Cromossomo,
     *     reward: float,
     *     operator_name: string|null
     * }
     */
    private function evolveChild(Cromossomo $child, float $mutationRate): array
    {
        $before = $child->fitness();
        $operator = null;

        if ($this->shouldMutate($mutationRate)) {
            $operator = $this->resolveMutationOperator();
            $child = $operator->mutate($child);
        }

        $child = $this->problem->repair($child);
        $after = $this->problem->evaluate($child)->score();

        if ($operator !== null && $this->hyperHeuristic) {
            $this->hyperHeuristic->record($operator, $before, $after);
        }

        return [
            'child' => $child,
            'reward' => $after - $before,
            'operator_name' => $operator !== null ? $this->operatorName($operator) : null,
        ];
    }

    private function resolveMutationOperator(): MutationOperatorInterface
    {
        $operator = $this->hyperHeuristic
            ? $this->hyperHeuristic->selectOperator($this->mutationPool)
            : $this->mutation;

        if (! $operator instanceof MutationOperatorInterface) {
            throw new \RuntimeException('Selected operator is not a mutation operator');
        }

        return $operator;
    }

    private function operatorName(object $operator): string
    {
        return method_exists($operator, 'getName')
            ? $operator->getName()
            : class_basename($operator);
    }

    /**
     * @param float[] $operatorRewards
     */
    private function summarizeOperatorReward(array $operatorRewards): float
    {
        if ($operatorRewards === []) {
            return 0.0;
        }

        return array_sum($operatorRewards) / count($operatorRewards);
    }

    /**
     * @param Cromossomo[] $previousPopulation
     * @param Cromossomo[] $newPopulation
     * @param float[] $rewards
     * @return array{
     *     improvement_acceptance_rate: float,
     *     worsening_acceptance_rate: float,
     *     population_turnover: float,
     *     best_signature_changed: bool,
     *     elite_similarity: float,
     *     best_signature: string
     * }
     */
    private function calculateTrajectorySignals(
        array $previousPopulation,
        array $newPopulation,
        array $rewards,
        int $generation,
        float $operationStartedAt,
        array $evaluationContext = [],
    ): array {
        $rewardCount = count($rewards);
        $improvements = count(array_filter($rewards, static fn (float $reward): bool => $reward > 0.0));
        $worsenings = count(array_filter($rewards, static fn (float $reward): bool => $reward < 0.0));

        $previousSignatureSet = $this->signatureSet($previousPopulation);
        $this->reportEvaluationWatchdog(
            generation: $generation,
            operationStartedAt: $operationStartedAt,
            substage: 'previous_signature_set_ready',
            context: $evaluationContext + [
                'signatures_processed' => count($previousPopulation),
            ],
        );
        $newSignatureSet = $this->signatureSet($newPopulation);
        $this->reportEvaluationWatchdog(
            generation: $generation,
            operationStartedAt: $operationStartedAt,
            substage: 'new_signature_set_ready',
            context: $evaluationContext + [
                'signatures_processed' => count($newPopulation),
            ],
        );
        $newSignatureCount = max(1, count($newPopulation));
        $newBestSignature = $this->bestSignature($newPopulation);

        $previousBestSignature = $previousPopulation === []
            ? ''
            : $this->bestSignature($previousPopulation);

        $turnoverCount = 0;

        foreach ($newPopulation as $index => $individual) {
            if (! isset($previousSignatureSet[$individual->signature()])) {
                $turnoverCount++;
            }

            if (($index + 1) % 10 === 0 || ($index + 1) === count($newPopulation)) {
                $this->reportEvaluationWatchdog(
                    generation: $generation,
                    operationStartedAt: $operationStartedAt,
                    substage: 'turnover_scan',
                    context: $evaluationContext + [
                        'signatures_processed' => $index + 1,
                        'population_turnover_partial' => $newSignatureCount === 0
                            ? 0.0
                            : round($turnoverCount / $newSignatureCount, 4),
                    ],
                );
            }
        }

        $eliteSimilarity = $this->eliteSimilarity($previousPopulation, $newPopulation);

        $this->reportEvaluationWatchdog(
            generation: $generation,
            operationStartedAt: $operationStartedAt,
            substage: 'elite_similarity_ready',
            context: $evaluationContext + [
                'elite_similarity' => $eliteSimilarity,
            ],
        );

        return [
            'improvement_acceptance_rate' => $rewardCount === 0 ? 0.0 : $improvements / $rewardCount,
            'worsening_acceptance_rate' => $rewardCount === 0 ? 0.0 : $worsenings / $rewardCount,
            'population_turnover' => $turnoverCount / $newSignatureCount,
            'best_signature_changed' => $previousBestSignature !== '' && $previousBestSignature !== $newBestSignature,
            'elite_similarity' => $eliteSimilarity,
            'best_signature' => $newBestSignature,
        ];
    }

    /**
     * @param Cromossomo[] $population
     * @return array<string, true>
     */
    private function signatureSet(array $population): array
    {
        $signatures = [];

        foreach ($population as $individual) {
            $signatures[$individual->signature()] = true;
        }

        return $signatures;
    }

    /**
     * @param Cromossomo[] $previousPopulation
     * @param Cromossomo[] $newPopulation
     */
    private function eliteSimilarity(array $previousPopulation, array $newPopulation): float
    {
        $eliteSize = max(1, min(5, min(count($previousPopulation), count($newPopulation))));

        if ($eliteSize <= 0) {
            return 0.0;
        }

        $previousElite = $this->topSignatures($previousPopulation, $eliteSize);
        $newElite = $this->topSignatures($newPopulation, $eliteSize);
        $union = array_unique(array_merge($previousElite, $newElite));

        if ($union === []) {
            return 0.0;
        }

        $intersection = array_intersect($previousElite, $newElite);

        return count($intersection) / count($union);
    }

    /**
     * @param Cromossomo[] $population
     * @return string[]
     */
    private function topSignatures(array $population, int $limit): array
    {
        usort(
            $population,
            static fn (Cromossomo $left, Cromossomo $right): int => $right->fitness() <=> $left->fitness(),
        );

        return array_map(
            static fn (Cromossomo $individual): string => $individual->signature(),
            array_slice($population, 0, $limit),
        );
    }

    /**
     * @param Cromossomo[] $population
     */
    private function bestSignature(array $population): string
    {
        $bestSignature = '';
        $bestFitness = -INF;

        foreach ($population as $individual) {
            $fitness = $individual->fitness();

            if ($fitness > $bestFitness) {
                $bestFitness = $fitness;
                $bestSignature = $individual->signature();
            }
        }

        return $bestSignature;
    }

    private function assertNotCancelled(): void
    {
        if ($this->executionMetrics === null || ! $this->executionMetrics->hasExecutionId()) {
            return;
        }

        $now = microtime(true);

        if (
            $this->lastCancellationCheckAt !== null
            && ($now - $this->lastCancellationCheckAt) < self::CANCELLATION_CHECK_INTERVAL_SECONDS
        ) {
            $status = $this->lastKnownExecutionStatus;

            if (in_array($status, ['cancel_requested', 'cancelled'], true)) {
                throw ExecutionCancelledException::forExecution($this->executionMetrics->getExecutionId());
            }

            return;
        }

        $executionId = $this->executionMetrics->getExecutionId();

        $status = ScheduleExecution::query()
            ->whereKey($executionId)
            ->value('status');

        $this->lastCancellationCheckAt = $now;
        $this->lastKnownExecutionStatus = is_string($status) ? $status : null;

        if (in_array($status, ['cancel_requested', 'cancelled'], true)) {
            throw ExecutionCancelledException::forExecution($executionId);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function reportOperationalHeartbeat(
        int $generation,
        string $stage,
        string $operation,
        float $operationStartedAt,
        array $context = [],
        bool $force = false,
    ): void {
        if ($this->progress === null) {
            return;
        }

        $now = microtime(true);
        $elapsedSeconds = max(0.0, $now - $operationStartedAt);

        $shouldEmitHeartbeat = $force
            || $this->lastOperationalHeartbeatAt === null
            || ($now - $this->lastOperationalHeartbeatAt) >= self::OPERATIONAL_HEARTBEAT_INTERVAL_SECONDS;

        $shouldLogLongRunning = $elapsedSeconds >= self::LONG_RUNNING_OPERATION_LOG_INTERVAL_SECONDS
            && ($this->lastLongRunningOperationLogAt === null
                || ($now - $this->lastLongRunningOperationLogAt) >= self::LONG_RUNNING_OPERATION_LOG_INTERVAL_SECONDS);

        if (! $shouldEmitHeartbeat && ! $shouldLogLongRunning) {
            return;
        }

        $payload = [
            'phase' => 'evolution',
            'stage' => $stage,
            'generation' => $generation,
            'local_generation' => $generation + 1,
            'max_generations' => $this->termination->getMaxGenerations() ?? 0,
            'execution_id' => $this->executionMetrics?->getExecutionId(),
            'island_id' => $this->islandId,
            'current_operation' => $stage,
            'operation_label' => $operation,
            'operation_elapsed_seconds' => (int) round($elapsedSeconds),
        ] + $context;

        if ($shouldEmitHeartbeat) {
            $this->progress->report($payload);
            Log::info('ga.execution.heartbeat', $payload);
            $this->lastOperationalHeartbeatAt = $now;
        }

        if ($shouldLogLongRunning) {
            Log::warning('ga.execution.long_running_operation', $payload + [
                'horario_id' => $this->executionMetrics?->getHorarioId(),
                'heartbeat_policy_seconds' => self::OPERATIONAL_HEARTBEAT_INTERVAL_SECONDS,
                'log_threshold_seconds' => self::LONG_RUNNING_OPERATION_LOG_INTERVAL_SECONDS,
            ]);
            $this->lastLongRunningOperationLogAt = $now;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function reportEvaluationWatchdog(
        int $generation,
        float $operationStartedAt,
        string $substage,
        array $context = [],
        bool $force = false,
    ): void {
        $this->reportOperationalHeartbeat(
            generation: $generation,
            stage: 'evaluating_population',
            operation: 'Avaliando a nova populacao da geracao',
            operationStartedAt: $operationStartedAt,
            context: $context + [
                'evaluation_substage' => $substage,
            ],
            force: $force,
        );
    }
}
