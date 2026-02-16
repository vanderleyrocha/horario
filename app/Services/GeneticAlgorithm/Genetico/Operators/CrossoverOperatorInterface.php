<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

interface CrossoverOperatorInterface
{
    public function crossover(Cromossomo $parent1, Cromossomo $parent2): array;
}