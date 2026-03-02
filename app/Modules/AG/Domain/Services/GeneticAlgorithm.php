<?php

namespace App\Modules\AG\Domain\Services;

use App\Modules\AG\Domain\Contracts\GeneticProblem;

class GeneticAlgorithm {

    public function run(GeneticProblem $problem, int $populationSize = 100, int $maxGenerations = 500): array {

        $population = [];

        for ($i = 0; $i < $populationSize; $i++) {
            $population[] = $problem->createIndividual();
        }

        for ($generation = 0; $generation < $maxGenerations; $generation++) {

            usort(
                $population,
                fn($a, $b) =>
                $problem->fitness($b) <=> $problem->fitness($a)
            );

            $best = $population[0];

            if ($problem->isFeasible($best)) {
                return [
                    'solution' => $best,
                    'generation' => $generation,
                ];
            }

            $newPopulation = [];

            for ($i = 0; $i < $populationSize / 2; $i++) {

                $parentA = $population[array_rand($population)];
                $parentB = $population[array_rand($population)];

                $child = $problem->crossover($parentA, $parentB);
                $child = $problem->mutate($child);

                $newPopulation[] = $child;
            }

            $population = $newPopulation;
        }

        return [
            'solution' => null,
            'generation' => $maxGenerations,
        ];
    }
}
