<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use Illuminate\Support\Collection;

class TournamentSelection implements SelectionOperatorInterface
{
    private int $tournamentSize;

    public function __construct(int $tournamentSize = 3)
    {
        $this->tournamentSize = $tournamentSize;
    }

    public function select(Collection $population, int $selectionSize): Collection
    {
        $selected = new Collection();
        for ($i = 0; $i < $selectionSize; $i++) {
            $competitors = new Collection();
            for ($j = 0; $j < $this->tournamentSize; $j++) {
                $competitors->add($population->random());
            }
            $selected->add($competitors->sortByDesc(fn(Cromossomo $c) => $c->getFitnessScore())->first());
        }
        return $selected;
    }

    public function getElites(Collection $population, int $elitismCount): Collection
    {
        return $population->sortByDesc(fn(Cromossomo $c) => $c->getFitnessScore())->take($elitismCount);
    }
}
