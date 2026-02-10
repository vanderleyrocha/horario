<?php

namespace App\Services\GeneticAlgorithm\Genetico\Termination;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO; // Adicionado para setContext
use Illuminate\Support\Collection;

interface TerminationCriterionInterface
{
    public function shouldTerminate(Collection $population, int $currentGeneration, ?Cromossomo $bestCromossomoOverall): bool;
    public function setContext(GeneticAlgorithmConfigDTO $configAG): void;
    public function getGenerationsWithoutImprovement(): int;
}
