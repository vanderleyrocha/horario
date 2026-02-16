<?php

namespace App\Services\GeneticAlgorithm\Genetico\Termination;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

interface TerminationCriterionInterface
{
    public function shouldTerminate(array $population, int $generation, ?Cromossomo $bestOverall): bool;

    public function getGenerationsWithoutImprovement(): int;
}