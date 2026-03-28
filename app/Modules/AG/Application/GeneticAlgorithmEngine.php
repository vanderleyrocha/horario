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
                operatorReward: $generationStep['operator_reward']
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
        $alnsTelemetry = [];
        $stagnation = $this->termination->getGenerationsWithoutImprovement();

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
                stagnation: $stagnation
            ));

            $alnsTelemetry['landscape_state'] = $this->landscapeEngine->state()?->value;
            $alnsTelemetry['landscape_phenomenon'] = $observation->phenomenon->value;
            $alnsTelemetry['landscape_observation'] = $observation->toArray();
        }

        if (
            $this->lns !== null &&
            $currentGeneration > 0 &&
            $currentGeneration % $this->lnsFrequency === 0
        ) {
            $alnsTelemetry = $this->applyLns($newPopulation);
        }

        $this->lastEvolutionTelemetry = [
            'mutation_rate' => $generationStep['mutation_rate'],
            'operator_used' => $generationStep['operator_used'],
            'operator_reward' => $generationStep['operator_reward'],
            'diversity' => $generationStep['diversity'],
            'entropy' => $generationStep['entropy'],
        ] + $alnsTelemetry;

        $this->evolutionGeneration++;

        return $newPopulation;
    }

    private function applyLns(array &$population): array
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
                'destroy_operator' => $telemetry['alns_destroy_operator'] ?? null,
                'repair_operator' => $telemetry['alns_repair_operator'] ?? null,
                'improvement' => $telemetry['alns_improvement'] ?? null,
                'destroy_uses' => $telemetry['alns_destroy_stats']['uses'] ?? null,
                'destroy_mean_reward' => $telemetry['alns_destroy_stats']['mean_reward'] ?? null,
                'repair_uses' => $telemetry['alns_repair_stats']['uses'] ?? null,
                'repair_mean_reward' => $telemetry['alns_repair_stats']['mean_reward'] ?? null,
            ]);
        }

        return $telemetry;
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
        float $operatorReward
    ): array {
        $landscapeState = null;
        $heatmap = [];
        $activateDynamicLNS = false;
        $alnsTelemetry = [];

        if ($this->landscapeEngine !== null) {
            $landscapeMetrics = new LandscapeMetrics(
                $generation,
                $metrics->bestFitness,
                $metrics->avgFitness,
                $metrics->variance,
                $metrics->diversity,
                $metrics->entropy,
                $stagnation
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
        }

        if (
            $this->lns !== null &&
            $generation > 0 &&
            ($generation % $this->lnsFrequency === 0 || $activateDynamicLNS)
        ) {
            $alnsTelemetry = $this->applyLns($population);

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
        $metrics->alnsDestroyOperator = $alnsTelemetry['alns_destroy_operator'] ?? null;
        $metrics->alnsRepairOperator = $alnsTelemetry['alns_repair_operator'] ?? null;
        $metrics->alnsImprovement = $alnsTelemetry['alns_improvement'] ?? null;
        $metrics->landscapePhenomenon = $alnsTelemetry['landscape_phenomenon'] ?? null;
        $metrics->landscapeObservation = $alnsTelemetry['landscape_observation'] ?? null;

        return [
            'metrics' => $metrics,
            'mutation_rate' => $mutationRate,
            'landscape_state' => $landscapeState,
            'heatmap' => $heatmap,
            'alns_telemetry' => $alnsTelemetry,
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
     * @param  Cromossomo[]  $population
     * @return array{
     *     population: array<int, Cromossomo>,
     *     mutation_rate: float,
     *     operator_used: string,
     *     operator_reward: float,
     *     diversity: float,
     *     entropy: float
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
        $operatorRewards = [];
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
                $operatorRewards[] = $childAResult['reward'];
                $operatorUsed = $childAResult['operator_name'];
            }

            if ($childBResult['operator_name'] !== null) {
                $operatorRewards[] = $childBResult['reward'];
                $operatorUsed = $childBResult['operator_name'];
            }

            $newPopulation[] = $childA;

            if (count($newPopulation) < $populationSize) {
                $newPopulation[] = $childB;
            }
        }

        $this->populationEvaluator->evaluate($newPopulation);

        return [
            'population' => $newPopulation,
            'mutation_rate' => $mutationRate,
            'operator_used' => $operatorUsed ?? 'none',
            'operator_reward' => $this->summarizeOperatorReward($operatorRewards),
            'diversity' => $diversity,
            'entropy' => $entropy,
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
