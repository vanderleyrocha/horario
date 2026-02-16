<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

interface SelectionOperatorInterface
{
    public function select(array $population, int $count): array;

    public function getElites(array $population, int $elitismCount): array;
}