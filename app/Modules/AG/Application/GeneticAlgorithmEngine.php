<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Operators\SelectionOperatorInterface;
use App\Modules\AG\Domain\Operators\CrossoverOperatorInterface;
use App\Modules\AG\Domain\Operators\MutationOperatorInterface;
use App\Modules\AG\Domain\Operators\ElitismStrategyInterface;
use App\Modules\AG\Domain\Termination\TerminationCriterionInterface;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;

final class GeneticAlgorithmEngine {
    public function __construct(
        private readonly GeneticProblem $problem,
        private readonly SelectionOperatorInterface $selection,
        private readonly CrossoverOperatorInterface $crossover,
        private readonly MutationOperatorInterface $mutation,
        private readonly TerminationCriterionInterface $termination,
        private readonly MetricsRecorder $metrics,
        private readonly ElitismStrategyInterface $elitism,
        private readonly ?ProgressReporterInterface $progress = null,
    ) {
    }

    public function run(int $populationSize): Cromossomo {
        $population = $this->initializePopulation($populationSize);

        $generation = 0;

        while (!$this->termination->shouldTerminate($generation, $population)) {

            $newPopulation = [];

            /**
             * 1️⃣ Preserva elites
             */
            $elites = $this->elitism->selectElites($population);

            foreach ($elites as $elite) {
                $newPopulation[] = $elite;
            }

            /**
             * 2️⃣ Evolução da população
             */
            while (count($newPopulation) < $populationSize) {

                $parentA = $this->selection->select($population);
                $parentB = $this->selection->select($population);

                [$childA, $childB] = $this->crossover->crossover($parentA, $parentB);

                $childA = $this->mutation->mutate($childA);
                $childB = $this->mutation->mutate($childB);

                $childA = $this->problem->repair($childA);
                $childB = $this->problem->repair($childB);

                $this->problem->evaluate($childA);
                $this->problem->evaluate($childB);

                $newPopulation[] = $childA;

                if (count($newPopulation) < $populationSize) {
                    $newPopulation[] = $childB;
                }
            }

            $population = $newPopulation;

            /**
             * 3️⃣ Métricas
             */
            $this->metrics->record($generation, $population);

            /**
             * 4️⃣ Progresso
             */
            if ($this->progress) {
                $this->progress->report([
                    'phase' => 'evolution',
                    'generation' => $generation,
                    'best_fitness' => $this->getBest($population)->fitness(),
                ]);
            }

            $generation++;
        }

        return $this->getBest($population);
    }

    /**
     * ============================================================
     * Inicialização
     * ============================================================
     */

    private function initializePopulation(int $size): array {
        $population = [];

        for ($i = 0; $i < $size; $i++) {

            $individual = $this->problem->createIndividual();
            $individual = $this->problem->repair($individual);
            $this->problem->evaluate($individual);

            $population[] = $individual;

            if ($this->progress) {
                $this->progress->report([
                    'phase' => 'initial_population',
                    'current' => $i + 1,
                    'total' => $size,
                ]);
            }
        }

        return $population;
    }

    /**
     * ============================================================
     * Melhor indivíduo
     * ============================================================
     */

    private function getBest(array $population): Cromossomo {
        usort(
            $population,
            fn(Cromossomo $a, Cromossomo $b) => $b->fitness() <=> $a->fitness()
        );

        return $population[0];
    }
}
