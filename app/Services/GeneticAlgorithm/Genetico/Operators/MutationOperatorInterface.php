<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

interface MutationOperatorInterface
{
    public function mutate(Cromossomo $cromossomo): void;
}