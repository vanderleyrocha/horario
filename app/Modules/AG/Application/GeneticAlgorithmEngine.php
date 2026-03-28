<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Helpers\DateTimeHelper;
use App\Modules\AG\Domain\Contracts\FitnessEvaluatorInterface;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\HyperHeuristic\LearningHyperHeuristicController;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
use App\Modules\AG\Domain\Landscape\LandscapeEngine;
use App\Modules\AG\Domain\Landscape\LandscapeMetrics;
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
use App\Modules\AG\Application\Progress\EvolutionProgress;
use App\Models\ScheduleExecution;
use App\Modules\AG\Support\Exceptions\ExecutionCancelledException;
use Illuminate\Support\Facades\Log;

final class GeneticAlgorithmEngine
{
    private array $mutationPool;
    private array $lastEvolutionTelemetry = [];

    public function __construct(private readonly GeneticProblem $problem, private readonly SelectionOperatorInterface $selection, private readonly CrossoverOperatorInterface $crossover, private readonly MutationOperatorInterface $mutation, private readonly TerminationCriterionInterface $termination, private readonly MetricsRecorder $metrics, private readonly ElitismStrategyInterface $elitism, private readonly AdaptiveMutationController $adaptiveMutation, private readonly FitnessEvaluatorInterface $populationEvaluator, private readonly ReplacementStrategyInterface $replacement, private readonly ?LearningHyperHeuristicController $hyperHeuristic, private readonly ?AdaptiveLargeNeighborhoodSearch $lns = null, private readonly ?ProgressReporterInterface $progress = null, private readonly int $lnsFrequency = 50, private readonly ?ExecutionMetricsRecorder $executionMetrics = null, private readonly ?LandscapeEngine $landscapeEngine = null)
    {
        $this->mutationPool = [$this->mutation];
    }

    public function run(int $populationSize): Cromossomo
    {
        $this->assertNotCancelled();
        $population = $this->initializePopulation($populationSize);

        $generation = 0;

        while (!$this->termination->shouldTerminate($generation, $population)) {
            $this->assertNotCancelled();

            $operatorRewards = [];
            $operatorUsed = null;
            $operatorReward = 0.0;

            $landscapeState = null;
            $activateDynamicLNS = false;
            $diversificationBoost = 0.0;

            $entropy = $this->metrics->lastEntropy();
            $diversity = $this->metrics->lastDiversity();

            $mutationRate = $this->adaptiveMutation->computeRate($entropy);

            if ($this->mutation instanceof DiversityAwareMutationInterface) {
                $this->mutation->setDiversity($diversity);
            }

            $newPopulation = [];

            foreach ($this->elitism->selectElites($population) as $elite) {
                $newPopulation[] = $elite;
            }

            while (count($newPopulation) < $populationSize) {
                $this->assertNotCancelled();

                $parentA = $this->selection->select($population);
                $parentB = $this->selection->select($population);

                [$childA, $childB] = $this->crossover->crossover($parentA, $parentB);

                /* CHILD A */

                $beforeA = $childA->fitness();

                if ($this->shouldMutate($mutationRate)) {

                    if ($this->hyperHeuristic) {

                        $operator = $this->hyperHeuristic->selectOperator($this->mutationPool);

                        if (!$operator instanceof MutationOperatorInterface) {
                            throw new \RuntimeException("Selected operator is not a mutation operator");
                        }

                    } else {
                        $operator = $this->mutation;
                    }

                    $childA = $operator->mutate($childA);
                }

                $childA = $this->problem->repair($childA);

                $resultA = $this->problem->evaluate($childA);

                $afterA = $resultA->score();

                $reward = $afterA - $beforeA;

                $name = method_exists($operator, 'getName')
                    ? $operator->getName()
                    : class_basename($operator);

                $operatorRewards[$name][] = $reward;
                $operatorUsed = $name;

                if ($this->hyperHeuristic) {
                    $this->hyperHeuristic->record($operator ?? $this->mutation, $beforeA, $afterA);
                }

                /* CHILD B */

                $beforeB = $childB->fitness();

                if ($this->shouldMutate($mutationRate)) {

                    $operator = $this->hyperHeuristic ? $this->hyperHeuristic->selectOperator($this->mutationPool) : $this->mutation;

                    if (!$operator instanceof MutationOperatorInterface) {
                        throw new \RuntimeException("Selected operator is not a mutation operator");
                    }

                    $childB = $operator->mutate($childB);
                }

                $childB = $this->problem->repair($childB);

                $resultB = $this->problem->evaluate($childB);

                $afterB = $resultB->score();

                $reward = $afterB - $beforeB;

                $name = method_exists($operator, 'getName')
                    ? $operator->getName()
                    : class_basename($operator);

                $operatorRewards[$name][] = $reward;
                $operatorUsed = $name;

                if ($this->hyperHeuristic) {
                    $this->hyperHeuristic->record($operator ?? $this->mutation, $beforeB, $afterB);
                }

                $newPopulation[] = $childA;

                if (count($newPopulation) < $populationSize) {
                    $newPopulation[] = $childB;
                }
            }

            $this->populationEvaluator->evaluate($newPopulation);

            $population = $newPopulation;

            $stagnation = $this->termination->getGenerationsWithoutImprovement();

            $metrics = $this->metrics->recordExtended($generation, $population, $mutationRate, $stagnation);


            Log::info("Generation {$generation} | best={$metrics->bestFitness} | avg={$metrics->avgFitness} | div={$metrics->diversity}");

            /* calcular reward médio */

            if (!empty($operatorRewards)) {

                $total = 0;
                $count = 0;

                foreach ($operatorRewards as $rewards) {
                    foreach ($rewards as $r) {
                        $total += $r;
                        $count++;
                    }
                }

                if ($count > 0) {
                    $operatorReward = $total / $count;
                }
            }

            /* LANDSCAPE */

            if ($this->landscapeEngine !== null) {

                $landscapeMetrics = new LandscapeMetrics($generation, $metrics->bestFitness, $metrics->avgFitness, $metrics->variance, $metrics->diversity, $metrics->entropy, $stagnation);

                $response = $this->landscapeEngine->evaluate($landscapeMetrics);

                if ($this->hyperHeuristic) {
                    $this->hyperHeuristic->updateLandscapeState($response->state);
                }

                $heatmap = $this->landscapeEngine->heatmap();

                $landscapeState = $response->state->value;

                $mutationRate *= $response->mutationMultiplier;

                $mutationRate = max(0.001, min($mutationRate, 0.9));

                $activateDynamicLNS = $response->activateALNS;
                $diversificationBoost = $response->diversificationBoost;
            }

            $metrics->landscapeState = $landscapeState;
            $metrics->operatorUsed = $operatorUsed;
            $metrics->operatorReward = $operatorReward;

            if ($this->executionMetrics !== null) {
                $this->executionMetrics->recordGeneration($metrics);
            }

            /* STREAMING DE MÉTRICAS */


            $this->metrics->publishGenerationMetrics([
                'execution_id' => $this->executionMetrics?->getExecutionId(),
                'generation' => $generation,
                'best_fitness' => $metrics->bestFitness,
                'avg_fitness' => $metrics->avgFitness,
                'variance' => $metrics->variance,
                'diversity' => $metrics->diversity,
                'entropy' => $metrics->entropy,
                'mutation_rate' => $mutationRate,
                'stagnation' => $stagnation,
                'landscape_state' => $landscapeState ?? 'unknown',
                'operator_used' => $operatorUsed,
                'operator_reward' => $operatorReward,
                'landscape_heatmap' => $heatmap,
                'timestamp' => microtime(true)
            ], $this->executionMetrics?->getExecutionId() ?? 0);




            /* LNS */

            if (
                $this->lns !== null &&
                $generation > 0 &&
                ($generation % $this->lnsFrequency === 0 || $activateDynamicLNS)
            ) {

                $best = $this->getBest($population);

                $candidate = $this->lns->improve($best->copy());

                $candidate = $this->problem->repair($candidate);

                $this->problem->evaluate($candidate);

                $this->replacement->replace($population, $candidate);

                $this->problem->clearFitnessCache();
            }

            /* PROGRESS */

            if ($this->progress) {

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

                $this->progress->report($progress->toArray());
            }

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

        for ($i = 0; $i < $size; $i++) {
            $this->assertNotCancelled();


            Log::info("Criando indivíduo {$i}");

            $individual = $this->problem->createIndividual();

            $individual = $this->problem->repair($individual);

            $population[] = $individual;
        }

        $this->populationEvaluator->evaluate($population);

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
        $entropy = $this->metrics->lastEntropy();
        $diversity = $this->metrics->lastDiversity();

        $mutationRate = $this->adaptiveMutation->computeRate($entropy);

        if ($this->mutation instanceof DiversityAwareMutationInterface) {
            $this->mutation->setDiversity($diversity);
        }

        $newPopulation = [];
        $operatorRewards = [];
        $operatorUsed = null;

        /* ELITISMO */

        foreach ($this->elitism->selectElites($population) as $elite) {
            $newPopulation[] = $elite;
        }

        /* EVOLUÇÃO */

        while (count($newPopulation) < $populationSize) {
            $this->assertNotCancelled();

            $parentA = $this->selection->select($population);
            $parentB = $this->selection->select($population);

            [$childA, $childB] = $this->crossover->crossover($parentA, $parentB);

            /* CHILD A */
            $beforeA = $childA->fitness();

            if ($this->shouldMutate($mutationRate)) {

                $operator = $this->hyperHeuristic
                    ? $this->hyperHeuristic->selectOperator($this->mutationPool)
                    : $this->mutation;

                if (!$operator instanceof MutationOperatorInterface) {
                    throw new \RuntimeException("Selected operator is not a mutation operator");
                }

                $childA = $operator->mutate($childA);
                $operatorUsed = method_exists($operator, 'getName')
                    ? $operator->getName()
                    : class_basename($operator);
            }

            $childA = $this->problem->repair($childA);
            $afterA = $this->problem->evaluate($childA)->score();
            $operatorRewards[] = $afterA - $beforeA;

            /* CHILD B */
            $beforeB = $childB->fitness();

            if ($this->shouldMutate($mutationRate)) {

                $operator = $this->hyperHeuristic
                    ? $this->hyperHeuristic->selectOperator($this->mutationPool)
                    : $this->mutation;

                if (!$operator instanceof MutationOperatorInterface) {
                    throw new \RuntimeException("Selected operator is not a mutation operator");
                }

                $childB = $operator->mutate($childB);
                $operatorUsed = method_exists($operator, 'getName')
                    ? $operator->getName()
                    : class_basename($operator);
            }

            $childB = $this->problem->repair($childB);
            $afterB = $this->problem->evaluate($childB)->score();
            $operatorRewards[] = $afterB - $beforeB;

            $newPopulation[] = $childA;

            if (count($newPopulation) < $populationSize) {
                $newPopulation[] = $childB;
            }
        }


        $this->populationEvaluator->evaluate($newPopulation);

        /* calcular reward médio */

        $operatorReward = 0.0;

        if (!empty($operatorRewards)) {

            $operatorReward = array_sum($operatorRewards) / count($operatorRewards);

        }

        $this->lastEvolutionTelemetry = [
            'mutation_rate' => $mutationRate,
            'operator_used' => $operatorUsed ?? 'none',
            'operator_reward' => $operatorReward,
            'diversity' => $diversity,
            'entropy' => $entropy
        ];


        return $newPopulation;
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
