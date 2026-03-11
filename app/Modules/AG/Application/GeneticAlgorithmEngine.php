<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Helpers\DateTimeHelper;
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
use App\Modules\AG\Domain\Operators\Mutation\Interfaces\AdaptiveOperatorInterface;
use App\Modules\AG\Domain\Operators\Mutation\Interfaces\DiversityAwareMutationInterface;
use App\Modules\AG\Domain\Operators\Mutation\MutationOperatorInterface;
use App\Modules\AG\Domain\Operators\Replacement\ReplacementStrategyInterface;
use App\Modules\AG\Domain\Operators\Selection\SelectionOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Termination\TerminationCriterionInterface;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Application\Progress\EvolutionProgress;
use Illuminate\Support\Facades\Log;

final class GeneticAlgorithmEngine
{
    private array $mutationPool;

    public function __construct(private readonly GeneticProblem $problem, private readonly SelectionOperatorInterface $selection, private readonly CrossoverOperatorInterface $crossover, private readonly MutationOperatorInterface $mutation, private readonly TerminationCriterionInterface $termination, private readonly MetricsRecorder $metrics, private readonly ElitismStrategyInterface $elitism, private readonly AdaptiveMutationController $adaptiveMutation, private readonly PopulationFitnessEvaluator $populationEvaluator, private readonly ReplacementStrategyInterface $replacement, private readonly ?LearningHyperHeuristicController $hyperHeuristic, private readonly ?AdaptiveLargeNeighborhoodSearch $lns = null, private readonly ?ProgressReporterInterface $progress = null, private readonly int $lnsFrequency = 50, private readonly ?ExecutionMetricsRecorder $executionMetrics = null, private readonly ?LandscapeEngine $landscapeEngine = null)
    {
        $this->mutationPool = [
            $this->mutation
        ];
    }

    public function run(int $populationSize): Cromossomo
    {
        $population = $this->initializePopulation($populationSize);

        $generation = 0;

        Log::info("Iniciando criação da população inicial");
        while (!$this->termination->shouldTerminate($generation, $population)) {

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

            /* -------------------------------------------------
               ELITISMO
            ------------------------------------------------- */

            foreach ($this->elitism->selectElites($population) as $elite) {
                $newPopulation[] = $elite;
            }

            /* -------------------------------------------------
               EVOLUÇÃO
            ------------------------------------------------- */
            Log::info("Iniciando a evolução da população");
            while (count($newPopulation) < $populationSize) {

                $parentA = $this->selection->select($population);
                $parentB = $this->selection->select($population);

                [$childA, $childB] = $this->crossover->crossover($parentA, $parentB);

                /* ---------------- CHILD A ---------------- */

                $beforeA = $childA->fitness();

                if ($this->shouldMutate($mutationRate)) {

                    if ($this->hyperHeuristic) {
                        $operator = $this->hyperHeuristic->selectOperator($this->mutationPool);
                    } else {
                        $operator = $this->mutation;
                    }

                    $childA = $operator->mutate($childA);
                }

                $childA = $this->problem->repair($childA);

                $resultA = $this->problem->evaluate($childA);

                $afterA = $resultA->score();


                $this->hyperHeuristic->record($operator ?? $this->mutation, $beforeA, $afterA);


                /* ---------------- CHILD B ---------------- */

                $beforeB = $childB->fitness();

                if ($this->shouldMutate($mutationRate)) {

                    $operator = $this->hyperHeuristic->selectOperator($this->mutationPool);

                    $childB = $operator->mutate($childB);
                }

                $childB = $this->problem->repair($childB);

                $resultB = $this->problem->evaluate($childB);

                $afterB = $resultB->score();

                $this->hyperHeuristic->record($operator ?? $this->mutation, $beforeB, $afterB);

                $newPopulation[] = $childA;

                if (count($newPopulation) < $populationSize) {
                    $newPopulation[] = $childB;
                }
            }

            /* -------------------------------------------------
               AVALIAÇÃO
            ------------------------------------------------- */

            $this->populationEvaluator->evaluate($newPopulation);
            $population = $newPopulation;

            $stagnation = $this->termination->getGenerationsWithoutImprovement();

            /* -------------------------------------------------
               MÉTRICAS
            ------------------------------------------------- */

            $metrics = $this->metrics->recordExtended($generation, $population, $mutationRate, $stagnation);

            /* -------------------------------------------------
               LANDSCAPE ANALYSIS
            ------------------------------------------------- */

            if ($this->landscapeEngine !== null) {

                $landscapeMetrics = new LandscapeMetrics($generation, $metrics->bestFitness, $metrics->avgFitness, $metrics->variance, $metrics->diversity, $metrics->entropy, $stagnation);

                $response = $this->landscapeEngine->evaluate($landscapeMetrics);

                $landscapeState = $response->state->value;

                $mutationRate *= $response->mutationMultiplier;
                $mutationRate = max(0.001, min($mutationRate, 0.9));

                $activateDynamicLNS = $response->activateALNS;
                $diversificationBoost = $response->diversificationBoost;
            }

            /* -------------------------------------------------
               DIVERSIFICATION
            ------------------------------------------------- */

            if ($diversificationBoost > 0) {

                $numNew = (int) round(count($population) * $diversificationBoost);

                for ($i = 0; $i < $numNew; $i++) {

                    $ind = $this->problem->createIndividual();
                    $ind = $this->problem->repair($ind);

                    $this->problem->evaluate($ind);

                    $this->replacement->replace($population, $ind);
                }
            }

            $metrics->landscapeState = $landscapeState;

            if ($this->executionMetrics !== null) {
                $this->executionMetrics->recordGeneration($metrics);
            }

            /* -------------------------------------------------
               LNS
            ------------------------------------------------- */

            $shouldRunLNS =
                $this->lns !== null &&
                $generation > 0 &&
                ($generation % $this->lnsFrequency === 0 ||
                    $activateDynamicLNS);

            if ($shouldRunLNS) {

                $best = $this->getBest($population);

                $candidate = $this->lns->improve($best->copy());

                $candidate = $this->problem->repair($candidate);

                $this->problem->evaluate($candidate);

                $this->replacement->replace($population, $candidate);

                $this->problem->clearFitnessCache();
            }

            /* -------------------------------------------------
               PROGRESS
            ------------------------------------------------- */

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

        Log::info("Iniciando geração da população de tamanho {$size}");

        for ($i = 0; $i < $size; $i++) {


            Log::info("A tentar gerar o indivíduo {$i}...");

            $individual = $this->problem->createIndividual();
            $individual = $this->problem->repair($individual);

            $population[] = $individual;

            if ($this->progress) {
                $this->progress->report([
                    'phase' => 'initial_population',
                    'current' => $i + 1,
                    'total' => $size
                ]);
            }
        }

        Log::info("População criada com sucesso! A avaliar fitness inicial...");

        $this->populationEvaluator->evaluate($population);

        return $population;
    }

    private function getBest(array $population): Cromossomo
    {
        usort($population, fn ($a, $b) => $b->fitness() <=> $a->fitness());
        return $population[0];
    }

    public function evolveGeneration_old(array $population, int $populationSize): array
    {
        $entropy = $this->metrics->lastEntropy();
        $diversity = $this->metrics->lastDiversity();

        $mutationRate = $this->adaptiveMutation->computeRate($entropy);

        if ($this->mutation instanceof DiversityAwareMutationInterface) {
            $this->mutation->setDiversity($diversity);
        }

        $newPopulation = [];

        /*
        |------------------------------------------
        | ELITISMO
        |------------------------------------------
        */

        foreach ($this->elitism->selectElites($population) as $elite) {
            $newPopulation[] = $elite;
        }

        /*
        |------------------------------------------
        | EVOLUÇÃO
        |------------------------------------------
        */

        $elapsed = DateTimeHelper::formatElapsedTime(app('app.start_time'));

        Log::info("Iniciando evolução da população com {$populationSize} indivíduos! Tempo decorrido = {$elapsed}");

        while (count($newPopulation) < $populationSize) {

            $parentA = $this->selection->select($population);
            $parentB = $this->selection->select($population);

            [$childA, $childB] = $this->crossover->crossover($parentA, $parentB);

            /*
            | CHILD A
            */

            $beforeA = $childA->fitness();

            if ($this->shouldMutate($mutationRate)) {

                if ($this->hyperHeuristic) {
                    $operator = $this->hyperHeuristic->selectOperator($this->mutationPool);
                } else {
                    $operator = $this->mutation;
                }

                $childA = $operator->mutate($childA);
            }

            $childA = $this->problem->repair($childA);

            $resultA = $this->problem->evaluate($childA);

            $afterA = $resultA->score();

            if ($this->hyperHeuristic) {
                $this->hyperHeuristic->record($operator ?? $this->mutation, $beforeA, $afterA);
            }

            /*
            | CHILD B
            */

            $beforeB = $childB->fitness();

            if ($this->shouldMutate($mutationRate)) {

                if ($this->hyperHeuristic) {
                    $operator =
                        $this->hyperHeuristic->selectOperator($this->mutationPool);
                } else {
                    $operator = $this->mutation;
                }

                $childB = $operator->mutate($childB);
            }

            $childB = $this->problem->repair($childB);

            $resultB = $this->problem->evaluate($childB);

            $afterB = $resultB->score();

            if ($this->hyperHeuristic) {
                $this->hyperHeuristic->record($operator ?? $this->mutation, $beforeB, $afterB);
            }

            $newPopulation[] = $childA;

            if (count($newPopulation) < $populationSize) {
                $newPopulation[] = $childB;
            }
        }

        $this->populationEvaluator->evaluate($newPopulation);

        $elapsed = DateTimeHelper::formatElapsedTime(app('app.start_time'));

        Log::info("Evolução concluída com sucesso! Tempo decorrido = {$elapsed}");
        return $newPopulation;
    }

    public function evolveGeneration(array $population, int $populationSize): array
    {
        $newPopulation = [];
        $mutationRate = $this->adaptiveMutation->computeRate(0.5); // Simplificado para o escopo

        // Taxa de intensificação (ALNS): Apenas 5% dos filhos passarão pela busca local pesada
        $alnsIntensificationRate = 0.05;

        while (count($newPopulation) < $populationSize) {

            $parentA = $this->selection->select($population);
            $parentB = $this->selection->select($population);

            [$childA, $childB] = $this->crossover->crossover($parentA, $parentB);

            /* ================= CHILD A ================= */
            if ($this->shouldMutate($mutationRate)) {
                $childA = $this->mutation->mutate($childA);
            }

            // Reparo ALNS apenas se sortear dentro da taxa (ex: 5% de chance)
            if ($this->shouldMutate($alnsIntensificationRate)) {
                $childA = $this->problem->repair($childA);
            }

            $newPopulation[] = $childA;

            if (count($newPopulation) >= $populationSize) {
                break;
            }

            /* ================= CHILD B ================= */
            if ($this->shouldMutate($mutationRate)) {
                $childB = $this->mutation->mutate($childB);
            }

            if ($this->shouldMutate($alnsIntensificationRate)) {
                $childB = $this->problem->repair($childB);
            }

            $newPopulation[] = $childB;
        }

        $this->populationEvaluator->evaluate($newPopulation);

        return $newPopulation;
    }

    public function createIndividual(): Cromossomo
    {
        $individual = $this->problem->createIndividual();

        $individual = $this->problem->repair($individual);

        $this->problem->evaluate($individual);

        return $individual;
    }
}
