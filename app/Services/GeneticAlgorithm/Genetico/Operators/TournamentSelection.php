<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

class TournamentSelection implements SelectionOperatorInterface
{
    public function __construct(private int $tournamentSize = 3) {}

    public function select(array $population, int $count): array
    {
        $selected = [];

        for ($i = 0; $i < $count; $i++) {
            $selected[] = $this->runTournament($population);
        }

        return $selected;
    }

    public function getElites(array $population, int $elitismCount): array
    {
        $sorted = $population;

        usort($sorted, fn($a, $b) => $a->getFitness() <=> $b->getFitness());

        return array_slice($sorted, 0, $elitismCount);
    }

    private function runTournament(array $population): Cromossomo
    {
        $best = null;

        for ($i = 0; $i < $this->tournamentSize; $i++) {
            $candidate = $population[array_rand($population)];

            if (!$best || $candidate->getFitness() < $best->getFitness()) {
                $best = $candidate;
            }
        }

        return $best;
    }
}