<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Models\ScheduleExecution;
use App\Modules\AG\Application\Progress\EvolutionProgress;
use App\Modules\AG\Domain\Contracts\FitnessEvaluatorInterface;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\HyperHeuristic\LearningHyperHeuristicController;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
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
use Illuminate\Support\Facades\Log;

final class GeneticAlgorithmEngine
{
    private const INITIAL_POPULATION_LOG_SAMPLE_SIZE = 3;

    private const OPERATIONAL_HEARTBEAT_INTERVAL_SECONDS = 30;

    private const LONG_RUNNING_OPERATION_LOG_INTERVAL_SECONDS = 300;

    private const CANCELLATION_CHECK_INTERVAL_SECONDS = 2;

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

    public function __construct(private readonly GeneticProblem $problem, private readonly SelectionOperatorInterface $selection, private readonly CrossoverOperatorInterface $crossover, private readonly MutationOperatorInterface $mutation, private readonly TerminationCriterionInterface $termination, private readonly MetricsRecorder $metrics, private readonly ElitismStrategyInterface $elitism, private readonly AdaptiveMutationController $adaptiveMutation, private readonly FitnessEvaluatorInterface $populationEvaluator, private readonly ReplacementStrategyInterface $replacement, private readonly ?LearningHyperHeuristicController $hyperHeuristic, private readonly ?AdaptiveLargeNeighborhoodSearch $lns = null, private readonly ?ProgressReporterInterface $progress = null, private readonly int $lnsFrequency = 50, private readonly ?ExecutionMetricsRecorder $executionMetrics = null, private readonly ?LandscapeEngine $landscapeEngine = null)
    {
        $this->mutationPool = [$this->mutation];
        $this->applySelectionPressureMultiplier(1.0);
    }

    public function run(int $populationSize): Cromossomo
    {
        $this->assertNotCancelled();
        $population = $this->initializePopulation($populationSize);

        $generation = 0;

        while (! $this->termination->shouldTerminate($generation, $population)) {
            $this->assertNotCancelled();

            $generationStep = $this->executeGenerationStep(
                population: $population,
                populationSize: $populationSize
            );
            $population = $generationStep['population'];

            $stagnation = $this->termination->getGenerationsWithoutImprovement();

            $metrics = $this->metrics->recordExtended(
                $generation,
                $population,
                $generationStep['mutation_rate'],
                $stagnation
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
                populationTrajectory: $generationStep
            );
            $metrics = $postProcess['metrics'];

            $this->publishGenerationState(
                generation: $generation,
                metrics: $metrics,
                mutationRate: $postProcess['mutation_rate'],
                stagnation: $stagnation,
                landscapeState: $postProcess['landscape_state'],
                heatmap: $postProcess['heatmap'],
                alnsTelemetry: $postProcess['alns_telemetry']
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

        for ($i = 0; $i < $size; $i++) {
            $this->assertNotCancelled();

            $individual = $this->problem->createIndividual();

            $individual = $this->problem->repair($individual);

            $population[] = $individual;

            if (count($sampleGenes) < self::INITIAL_POPULATION_LOG_SAMPLE_SIZE) {
                $sampleGenes[] = $individual->count();
            }
        }

        $this->populationEvaluator->evaluate($population);

        $fitnessValues = array_map(
            static fn (Cromossomo $individual): float => $individual->fitness(),
            $population
        );

        Log::info('ga.population.initialized', [
            'execution_id' => $this->executionMetrics?->getExecutionId(),
            'population_size' => count($population),
            'sample_gene_counts' => $sampleGenes,
            'best_fitness' => $fitnessValues === [] ? null : max($fitnessValues),
            'avg_fitness' => $fitnessValues === []
                ? null
                : array_sum($fitnessValues) / count($fitnessValues),
        ]);

        return $population;
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

        $this->problem->evaluate($individual);

        return $individual;
    }

    public function lastEvolutionTelemetry(): array
    {
        return $this->lastEvolutionTelemetry;
    }

    public function evolveGeneration(array $population, int $populationSize): array
    {
        $this->assertNotCancelled();
        $currentGeneration = $this->evolutionGeneration;
        $generationStep = $this->executeGenerationStep(
            population: $population,
            populationSize: $populationSize
        );
        $newPopulation = $generationStep['population'];
        $telemetry = [];
        $stagnation = $this->termination->getGenerationsWithoutImprovement();
        $observationPayload = null;
        $landscapeState = null;
        $activateDynamicLns = false;
        $selectionPressureTelemetry = $this->selectionPressureTelemetry();

        if ($this->landscapeEngine !== null) {
            $localMetrics = $this->metrics->recordExtended(
                generation: $currentGeneration,
                population: $newPopulation,
                mutationRate: $generationStep['mutation_rate'],
                stagnation: $stagnation
            );

            $response = $this->landscapeEngine->evaluate(new LandscapeMetrics(
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
                bestSignature: $generationStep['best_signature']
            ));

            $landscapeState = $response->state->value;
            $activateDynamicLns = $response->activateALNS;
            $telemetry['landscape_state'] = $landscapeState;
            $selectionPressureTelemetry = $this->applySelectionPressureMultiplier(
                $response->selectionPressureMultiplier,
                'landscape_response',
                $landscapeState
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
            activateDynamicLns: $activateDynamicLns
        );
        $telemetry += $alnsTrigger;
        $mutationShockActivation = $this->resolveRealMutationShockActivation(
            generation: $currentGeneration,
            landscapeObservation: $observationPayload,
            eligible: true
        );
        $telemetry += $mutationShockActivation;
        $selectionPressureActivation = $this->resolveRealSelectionPressureReductionActivation(
            generation: $currentGeneration,
            landscapeObservation: $observationPayload,
            eligible: true
        );
        $telemetry += $selectionPressureActivation;

        if ($observationPayload !== null) {
            $telemetry['landscape_observation'] = $observationPayload + [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
                'mutation_shock' => $this->mutationShockObservationPayload(
                    $mutationShockActivation,
                    $generationStep
                ),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $generationStep
                ),
            ];
        } elseif (($alnsTrigger['alns_trigger_eligible'] ?? false) === true) {
            $telemetry['landscape_observation'] = [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
                'mutation_shock' => $this->mutationShockObservationPayload(
                    $mutationShockActivation,
                    $generationStep
                ),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $generationStep
                ),
            ];
        } elseif (
            ($mutationShockActivation['mutation_shock_trigger_eligible'] ?? false) === true
            || ($selectionPressureActivation['selection_pressure_trigger_eligible'] ?? false) === true
        ) {
            $telemetry['landscape_observation'] = [
                'mutation_shock' => $this->mutationShockObservationPayload(
                    $mutationShockActivation,
                    $generationStep
                ),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $generationStep
                ),
            ];
        }

        if (($alnsTrigger['alns_triggered'] ?? false) === true) {
            $this->lastAlnsGeneration = $currentGeneration;
            $alnsTelemetry = $this->applyLns(
                population: $newPopulation,
                triggerTelemetry: $alnsTrigger,
                landscapeObservation: $telemetry['landscape_observation'] ?? null
            );
            $telemetry += $alnsTelemetry;

            if (isset($telemetry['landscape_observation']) && is_array($telemetry['landscape_observation'])) {
                $telemetry['landscape_observation'] = $this->mergeAlnsObservationTelemetry(
                    $telemetry['landscape_observation'],
                    $alnsTrigger,
                    $alnsTelemetry
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
        ] + $telemetry;

        $this->evolutionGeneration++;

        return $newPopulation;
    }

    private function applyLns(
        array &$population,
        array $triggerTelemetry = [],
        ?array $landscapeObservation = null
    ): array {
        if ($this->lns === null || $population === []) {
            return [];
        }

        $best = $this->getBest($population);
        $candidate = $this->lns->improve($best->copy(), [
            'trigger' => $triggerTelemetry,
            'landscape_observation' => $landscapeObservation,
        ]);
        $candidate = $this->problem->repair($candidate);
        $this->problem->evaluate($candidate);
        $this->replacement->replace($population, $candidate);
        $this->problem->clearFitnessCache();

        $telemetry = $this->lns->lastTelemetry();

        if ($telemetry !== []) {
            Log::info('ga.alns.applied', [
                'execution_id' => $this->executionMetrics?->getExecutionId(),
                'trigger_reason' => $triggerTelemetry['alns_trigger_reason'] ?? null,
                'effective_frequency' => $triggerTelemetry['alns_effective_frequency'] ?? null,
                'destroy_operator' => $telemetry['alns_destroy_operator'] ?? null,
                'repair_operator' => $telemetry['alns_repair_operator'] ?? null,
                'improvement' => $telemetry['alns_improvement'] ?? null,
                'destroy_uses' => $telemetry['alns_destroy_stats']['uses'] ?? null,
                'destroy_mean_reward' => $telemetry['alns_destroy_stats']['mean_reward'] ?? null,
                'repair_uses' => $telemetry['alns_repair_stats']['uses'] ?? null,
                'repair_mean_reward' => $telemetry['alns_repair_stats']['mean_reward'] ?? null,
            ]);
        }

        return $triggerTelemetry + $telemetry;
    }

    /**
     * @param  Cromossomo[]  $population
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
        array $populationTrajectory
    ): array {
        $landscapeState = null;
        $heatmap = [];
        $activateDynamicLNS = false;
        $telemetry = [];
        $observationPayload = null;
        $selectionPressureTelemetry = $this->selectionPressureTelemetry();

        if ($this->landscapeEngine !== null) {
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
                bestSignature: (string) ($populationTrajectory['best_signature'] ?? '')
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
                $landscapeState
            );
            $telemetry += $selectionPressureTelemetry;

            if ($observation = $this->landscapeEngine->observation()) {
                $telemetry['landscape_phenomenon'] = $observation->phenomenon->value;
                $observationPayload = $observation->toArray();
                $telemetry['landscape_observation'] = $observationPayload;
            }
        }

        $alnsTrigger = $this->buildAlnsTriggerTelemetry(
            generation: $generation,
            landscapeState: $landscapeState,
            landscapeObservation: $observationPayload,
            activateDynamicLns: $activateDynamicLNS
        );
        $telemetry += $alnsTrigger;
        $mutationShockActivation = $this->resolveRealMutationShockActivation(
            generation: $generation,
            landscapeObservation: $observationPayload,
            eligible: true
        );
        $telemetry += $mutationShockActivation;
        $selectionPressureActivation = $this->resolveRealSelectionPressureReductionActivation(
            generation: $generation,
            landscapeObservation: $observationPayload,
            eligible: true
        );
        $telemetry += $selectionPressureActivation;

        if ($observationPayload !== null) {
            $telemetry['landscape_observation'] = $observationPayload + [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
                'mutation_shock' => $this->mutationShockObservationPayload(
                    $mutationShockActivation,
                    $populationTrajectory
                ),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $populationTrajectory
                ),
            ];
        } elseif (($alnsTrigger['alns_trigger_eligible'] ?? false) === true) {
            $telemetry['landscape_observation'] = [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
                'mutation_shock' => $this->mutationShockObservationPayload(
                    $mutationShockActivation,
                    $populationTrajectory
                ),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $populationTrajectory
                ),
            ];
        } elseif (
            ($mutationShockActivation['mutation_shock_trigger_eligible'] ?? false) === true
            || ($selectionPressureActivation['selection_pressure_trigger_eligible'] ?? false) === true
        ) {
            $telemetry['landscape_observation'] = [
                'mutation_shock' => $this->mutationShockObservationPayload(
                    $mutationShockActivation,
                    $populationTrajectory
                ),
                'selection_pressure' => $this->selectionPressureObservationPayload(
                    $selectionPressureTelemetry,
                    $selectionPressureActivation,
                    $populationTrajectory
                ),
            ];
        }

        if (($alnsTrigger['alns_triggered'] ?? false) === true) {
            $this->lastAlnsGeneration = $generation;
            $alnsTelemetry = $this->applyLns(
                population: $population,
                triggerTelemetry: $alnsTrigger,
                landscapeObservation: $telemetry['landscape_observation'] ?? null
            );
            $telemetry += $alnsTelemetry;

            if (isset($telemetry['landscape_observation']) && is_array($telemetry['landscape_observation'])) {
                $telemetry['landscape_observation'] = $this->mergeAlnsObservationTelemetry(
                    $telemetry['landscape_observation'],
                    $alnsTrigger,
                    $alnsTelemetry
                );
            }

            $metrics = $this->metrics->recordExtended(
                generation: $generation,
                population: $population,
                mutationRate: $mutationRate,
                stagnation: $stagnation,
                landscapeState: $landscapeState,
                forceRefreshStatistics: true
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
        array $alnsTelemetry
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

        $progress = new EvolutionProgress;

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
     * @param  array<string, mixed>|null  $landscapeObservation
     * @return array<string, mixed>
     */
    private function buildAlnsTriggerTelemetry(
        int $generation,
        ?string $landscapeState,
        ?array $landscapeObservation,
        bool $activateDynamicLns
    ): array {
        $baseFrequency = max(2, $this->lnsFrequency);
        $maxGenerations = max(1, $this->termination->getMaxGenerations() ?? ($generation + 1));
        $budgetFrequency = $this->budgetAwareLnsFrequency($maxGenerations);
        $landscapeFrequency = $this->landscapeAwareLnsFrequency(
            budgetFrequency: $budgetFrequency,
            landscapeState: $landscapeState,
            landscapeObservation: $landscapeObservation,
            activateDynamicLns: $activateDynamicLns
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
            eligible: $this->lns !== null && $generation > 0
        );
        $landscapePressure = $this->hasLandscapePressure(
            landscapeState: $landscapeState,
            landscapeObservation: $landscapeObservation,
            activateDynamicLns: $activateDynamicLns
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
                : ($cooldownSatisfied ? ($landscapePressure ? 'waiting_interval' : 'not_due') : (($cooldownBrake['applied'] ?? false) ? 'cooldown_recent_low_return' : 'cooldown')),
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
     * @param  array<string, float|int>  $recentEffectiveness
     * @return array{applied: bool, extra_generations: int, reason: ?string}
     */
    private function adaptiveAlnsCooldownBrake(array $recentEffectiveness): array
    {
        $sampleSize = (int) ($recentEffectiveness['sample_size'] ?? 0);
        $meanImprovement = (float) ($recentEffectiveness['mean_improvement'] ?? 0.0);
        $successRate = (float) ($recentEffectiveness['success_rate'] ?? 0.0);

        if ($sampleSize < 3) {
            return [
                'applied' => false,
                'extra_generations' => 0,
                'reason' => null,
            ];
        }

        if ($meanImprovement <= -5.0 || ($meanImprovement <= 0.0 && $successRate <= 0.15)) {
            return [
                'applied' => true,
                'extra_generations' => 3,
                'reason' => 'Recent ALNS outcomes are consistently negative or null.',
            ];
        }

        if ($meanImprovement <= 0.0 || $successRate <= 0.34) {
            return [
                'applied' => true,
                'extra_generations' => 2,
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
     * @param  array<string, mixed>|null  $landscapeObservation
     */
    private function landscapeAwareLnsFrequency(
        int $budgetFrequency,
        ?string $landscapeState,
        ?array $landscapeObservation,
        bool $activateDynamicLns
    ): ?int {
        if (! $this->hasLandscapePressure($landscapeState, $landscapeObservation, $activateDynamicLns)) {
            return null;
        }

        return max(2, (int) ceil($budgetFrequency / 2));
    }

    /**
     * @param  array<string, mixed>|null  $landscapeObservation
     */
    private function hasLandscapePressure(
        ?string $landscapeState,
        ?array $landscapeObservation,
        bool $activateDynamicLns
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
     * @param  array<string, mixed>  $alnsTrigger
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
     * @param  array<string, mixed>|null  $landscapeObservation
     * @return array<string, mixed>
     */
    private function resolveRealAlnsActivation(
        ?array $landscapeObservation,
        ?int $generationsSinceLastTrigger,
        bool $eligible
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
     * @param  array<string, mixed>|null  $landscapeObservation
     * @return array<string, mixed>
     */
    private function resolveRealMutationShockActivation(
        int $generation,
        ?array $landscapeObservation,
        bool $eligible
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
     * @param  array<string, mixed>  $mutationShockActivation
     * @param  array<string, mixed>  $populationTrajectory
     * @return array<string, mixed>
     */
    private function mutationShockObservationPayload(
        array $mutationShockActivation,
        array $populationTrajectory = []
    ): array {
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
     * @param  array<string, mixed>  $selectionPressureTelemetry
     * @param  array<string, mixed>  $selectionPressureActivation
     * @param  array<string, mixed>  $populationTrajectory
     * @return array<string, mixed>
     */
    private function selectionPressureObservationPayload(
        array $selectionPressureTelemetry,
        array $selectionPressureActivation,
        array $populationTrajectory = []
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
     * @param  array<string, mixed>  $landscapeObservation
     * @param  array<string, mixed>  $alnsTrigger
     * @param  array<string, mixed>  $alnsTelemetry
     * @return array<string, mixed>
     */
    private function mergeAlnsObservationTelemetry(
        array $landscapeObservation,
        array $alnsTrigger,
        array $alnsTelemetry
    ): array {
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
     * @param  Cromossomo[]  $population
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
        $entropy = $this->metrics->lastEntropy();
        $diversity = $this->metrics->lastDiversity();
        $baseMutationRate = $this->adaptiveMutation->computeRate($entropy);
        $mutationShock = $this->consumeActiveMutationShock($baseMutationRate, $generation);
        $mutationRate = $mutationShock['mutation_rate'];
        $selectionPressure = $this->consumeActiveSelectionPressureReduction(
            $this->currentSelectionPressureMultiplier,
            $generation
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
            force: true
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
                ]
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
            force: true
        );
        $this->populationEvaluator->evaluate($newPopulation);
        $trajectorySignals = $this->calculateTrajectorySignals(
            previousPopulation: $population,
            newPopulation: $newPopulation,
            rewards: $allRewards
        );

        return [
            'population' => $newPopulation,
            'mutation_rate' => $mutationRate,
            'operator_used' => $operatorUsed ?? 'none',
            'operator_reward' => $this->summarizeOperatorReward($allRewards),
            'diversity' => $diversity,
            'entropy' => $entropy,
        ] + $trajectorySignals + $mutationShock['telemetry'] + $selectionPressure['telemetry'];
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
        ?string $landscapeState = null
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
     * @param  array<string, mixed>|null  $landscapeObservation
     * @return array<string, mixed>
     */
    private function resolveRealSelectionPressureReductionActivation(
        int $generation,
        ?array $landscapeObservation,
        bool $eligible
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
                    'selection_pressure_effective_multiplier' => round(
                        (float) ($telemetry['selection_pressure_multiplier'] ?? $baseMultiplier),
                        6
                    ),
                    'selection_pressure_reduction_active' => false,
                ],
            ];
        }

        $reduction = $this->activeSelectionPressureReduction;
        $multiplier = max(0.34, min(1.0, (float) ($reduction['multiplier'] ?? 1.0)));
        $remainingBefore = max(0, (int) ($reduction['remaining_generations'] ?? 0));
        $effectiveMultiplier = min(max(0.34, $baseMultiplier), $multiplier);
        $telemetry = $this->applySelectionPressureMultiplier(
            $effectiveMultiplier,
            'activation_gate',
            null
        );

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
     * @param  float[]  $operatorRewards
     */
    private function summarizeOperatorReward(array $operatorRewards): float
    {
        if ($operatorRewards === []) {
            return 0.0;
        }

        return array_sum($operatorRewards) / count($operatorRewards);
    }

    /**
     * @param  Cromossomo[]  $previousPopulation
     * @param  Cromossomo[]  $newPopulation
     * @param  float[]  $rewards
     * @return array{
     *     improvement_acceptance_rate: float,
     *     worsening_acceptance_rate: float,
     *     population_turnover: float,
     *     best_signature_changed: bool,
     *     elite_similarity: float,
     *     best_signature: string
     * }
     */
    private function calculateTrajectorySignals(array $previousPopulation, array $newPopulation, array $rewards): array
    {
        $rewardCount = count($rewards);
        $improvements = count(array_filter($rewards, static fn (float $reward): bool => $reward > 0.0));
        $worsenings = count(array_filter($rewards, static fn (float $reward): bool => $reward < 0.0));
        $previousSignatureSet = $this->signatureSet($previousPopulation);
        $newSignatureSet = $this->signatureSet($newPopulation);
        $newSignatureCount = max(1, count($newPopulation));
        $newBestSignature = $this->getBest($newPopulation)->signature();
        $previousBestSignature = $previousPopulation === []
            ? ''
            : $this->getBest($previousPopulation)->signature();

        $turnoverCount = 0;

        foreach ($newPopulation as $individual) {
            if (! isset($previousSignatureSet[$individual->signature()])) {
                $turnoverCount++;
            }
        }

        return [
            'improvement_acceptance_rate' => $rewardCount === 0 ? 0.0 : $improvements / $rewardCount,
            'worsening_acceptance_rate' => $rewardCount === 0 ? 0.0 : $worsenings / $rewardCount,
            'population_turnover' => $turnoverCount / $newSignatureCount,
            'best_signature_changed' => $previousBestSignature !== '' && $previousBestSignature !== $newBestSignature,
            'elite_similarity' => $this->eliteSimilarity($previousPopulation, $newPopulation),
            'best_signature' => $newBestSignature,
        ];
    }

    /**
     * @param  Cromossomo[]  $population
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
     * @param  Cromossomo[]  $previousPopulation
     * @param  Cromossomo[]  $newPopulation
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
     * @param  Cromossomo[]  $population
     * @return string[]
     */
    private function topSignatures(array $population, int $limit): array
    {
        usort($population, static fn (Cromossomo $left, Cromossomo $right): int => $right->fitness() <=> $left->fitness());

        return array_map(
            static fn (Cromossomo $individual): string => $individual->signature(),
            array_slice($population, 0, $limit)
        );
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
     * @param  array<string, mixed>  $context
     */
    private function reportOperationalHeartbeat(
        int $generation,
        string $stage,
        string $operation,
        float $operationStartedAt,
        array $context = [],
        bool $force = false
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
            && (
                $this->lastLongRunningOperationLogAt === null
                || ($now - $this->lastLongRunningOperationLogAt) >= self::LONG_RUNNING_OPERATION_LOG_INTERVAL_SECONDS
            );

        if (! $shouldEmitHeartbeat && ! $shouldLogLongRunning) {
            return;
        }

        $payload = [
            'phase' => 'evolution',
            'stage' => $stage,
            'generation' => $generation,
            'max_generations' => $this->termination->getMaxGenerations() ?? 0,
            'execution_id' => $this->executionMetrics?->getExecutionId(),
            'current_operation' => $stage,
            'operation_label' => $operation,
            'operation_elapsed_seconds' => (int) round($elapsedSeconds),
        ] + $context;

        if ($shouldEmitHeartbeat) {
            $this->progress->report($payload);
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
}
