<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use Illuminate\Support\Collection;

class SinglePointCrossover implements CrossoverOperatorInterface
{
    public function crossover(Cromossomo $parent1, Cromossomo $parent2): Collection
    {
        $child1 = $parent1->clone();
        $child2 = $parent2->clone();

        $genes1 = $child1->genes->toArray();
        $genes2 = $child2->genes->toArray();

        $crossoverPoint = rand(1, min(count($genes1), count($genes2)) - 1);

        // Troca os genes a partir do ponto de cruzamento
        for ($i = $crossoverPoint; $i < count($genes1); $i++) {
            if (isset($genes2[$i])) {
                $child1->genes[$i] = $genes2[$i]->clone();
            }
        }
        for ($i = $crossoverPoint; $i < count($genes2); $i++) {
            if (isset($genes1[$i])) {
                $child2->genes[$i] = $genes1[$i]->clone();
            }
        }

        return new Collection([$child1, $child2]);
    }
}
