<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

class SinglePointCrossover implements CrossoverOperatorInterface
{
    public function crossover(Cromossomo $parent1, Cromossomo $parent2): array {

        $size = count($parent1->genes);

        if ($size <= 1) {
            return [$parent1->copy(), $parent2->copy()];
        }

        $cutPoint = rand(1, $size - 1);

        $child1Genes = array_merge(array_slice($parent1->genes, 0, $cutPoint), array_slice($parent2->genes, $cutPoint));

        $child2Genes = array_merge(array_slice($parent2->genes, 0, $cutPoint), array_slice($parent1->genes, $cutPoint));

        return [new Cromossomo($child1Genes), new Cromossomo($child2Genes),];
    }
}