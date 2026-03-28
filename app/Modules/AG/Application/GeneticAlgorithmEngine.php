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
use App\Modules\AG\Domain\Operators\Selection\SelectionOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Termination\TerminationCriterionInterface;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Support\Exceptions\ExecutionCancelledException;
use Illuminate\Support\Facades\Log;

final class GeneticAlgorithmEngine
{
    private const INITIAL_POPULATION_LOG_SAMPLE_SIZE = 3;

    private array $mutationPool;

    private array $lastEvolutionTelemetry = [];

    private int $evolutionGeneration = 0;

    private ?int $lastAlnsGeneration = null;

    public function __construct(private readonly GeneticProblem $problem, private readonly SelectionOperatorInterface $selection, private readonly CrossoverOperatorInterface $crossover, private readonly MutationOperatorInterface $mutation, private readonly TerminationCriterionInterface $termination, private readonly MetricsRecorder $metrics, private readonly ElitismStrategyInterface $elitism, private readonly AdaptiveMutationController $adaptiveMutation, private readonly FitnessEvaluatorInterface $populationEvaluator, private readonly ReplacementStrategyInterface $replacement, private readonly ?LearningHyperHeuristicController $hyperHeuristic, private readonly ?AdaptiveLargeNeighborhoodSearch $lns = null, private readonly ?ProgressReporterInterface $progress = null, private readonly int $lnsFrequency = 50, private readonly ?ExecutionMetricsRecorder $executionMetrics = null, private readonly ?LandscapeEngine $landscapeEngine = null)
    {
        $this->mutationPool = [$this->mutation];
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

        if ($this->landscapeEngine !== null) {
            $localMetrics = $this->metrics->recordExtended(
                generation: $currentGeneration,
                population: $newPopulation,
                mutationRate: $generationStep['mutation_rate'],
                stagnation: $stagnation
            );

            $observation = $this->landscapeEngine->observe(new LandscapeMetrics(
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

            $landscapeState = $this->landscapeEngine->state()?->value;
            $activateDynamicLns = in_array($landscapeState, ['plateau', 'premature_convergence'], true);
            $telemetry['landscape_state'] = $landscapeState;
            $telemetry['landscape_phenomenon'] = $observation->phenomenon->value;
            $observationPayload = $observation->toArray();
            $telemetry['landscape_observation'] = $observationPayload;
        }

        $alnsTrigger = $this->buildAlnsTriggerTelemetry(
            generation: $currentGeneration,
            landscapeState: $landscapeState,
            landscapeObservation: $observationPayload,
            activateDynamicLns: $activateDynamicLns
        );
        $telemetry += $alnsTrigger;

        if ($observationPayload !== null) {
            $telemetry['landscape_observation'] = $observationPayload + [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
            ];
        }

        if (($alnsTrigger['alns_triggered'] ?? false) === true) {
            $this->lastAlnsGeneration = $currentGeneration;
            $telemetry += $this->applyLns($newPopulation, $alnsTrigger);
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
        ] + $telemetry;

        $this->evolutionGeneration++;

        return $newPopulation;
    }

    private function applyLns(array &$population, array $triggerTelemetry = []): array
    {
        if ($this->lns === null || $population === []) {
            return [];
        }

        $best = $this->getBest($population);
        $candidate = $this->lns->improve($best->copy());
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

        if ($observationPayload !== null) {
            $telemetry['landscape_observation'] = $observationPayload + [
                'alns_trigger' => $this->alnsTriggerObservationPayload($alnsTrigger),
            ];
        }

        if (($alnsTrigger['alns_triggered'] ?? false) === true) {
            $this->lastAlnsGeneration = $generation;
            $telemetry += $this->applyLns($population, $alnsTrigger);

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

        $cooldown = max(1, (int) floor($effectiveFrequency / 2));
        $generationsSinceLastTrigger = $this->lastAlnsGeneration === null
            ? null
            : $generation - $this->lastAlnsGeneration;
        $cooldownSatisfied = $generationsSinceLastTrigger === null || $generationsSinceLastTrigger >= $cooldown;
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

        $eligible = $this->lns !== null && $generation > 0;
        $triggered = $eligible && $triggerSources !== [];

        return [
            'alns_base_frequency' => $baseFrequency,
            'alns_budget_frequency' => $budgetFrequency,
            'alns_landscape_frequency' => $landscapeFrequency,
            'alns_effective_frequency' => $effectiveFrequency,
            'alns_cooldown_generations' => $cooldown,
            'alns_generations_since_last_trigger' => $generationsSinceLastTrigger,
            'alns_landscape_pressure' => $landscapePressure,
            'alns_trigger_eligible' => $eligible,
            'alns_triggered' => $triggered,
            'alns_trigger_reason' => $triggered
                ? implode('+', $triggerSources)
                : ($cooldownSatisfied ? ($landscapePressure ? 'waiting_interval' : 'not_due') : 'cooldown'),
            'alns_trigger_sources' => $triggerSources,
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
            'cooldown_generations' => $alnsTrigger['alns_cooldown_generations'] ?? null,
            'generations_since_last_trigger' => $alnsTrigger['alns_generations_since_last_trigger'] ?? null,
            'landscape_pressure' => $alnsTrigger['alns_landscape_pressure'] ?? false,
            'eligible' => $alnsTrigger['alns_trigger_eligible'] ?? false,
            'triggered' => $alnsTrigger['alns_triggered'] ?? false,
            'reason' => $alnsTrigger['alns_trigger_reason'] ?? null,
            'sources' => $alnsTrigger['alns_trigger_sources'] ?? [],
        ];
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
        $entropy = $this->metrics->lastEntropy();
        $diversity = $this->metrics->lastDiversity();
        $mutationRate = $this->adaptiveMutation->computeRate($entropy);

        if ($this->mutation instanceof DiversityAwareMutationInterface) {
            $this->mutation->setDiversity($diversity);
        }

        $newPopulation = [];
        $allRewards = [];
        $operatorUsed = null;

        foreach ($this->elitism->selectElites($population) as $elite) {
            $newPopulation[] = $elite;
        }

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
        }

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
        ] + $trajectorySignals;
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

        $executionId = $this->executionMetrics->getExecutionId();

        $status = ScheduleExecution::query()
            ->whereKey($executionId)
            ->value('status');

        if (in_array($status, ['cancel_requested', 'cancelled'], true)) {
            throw ExecutionCancelledException::forExecution($executionId);
        }
    }
}
