<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO; // Adicionado para setContext

interface MutationOperatorInterface
{
    public function mutate(Cromossomo $cromossomo, float $mutationRate): void;
    public function setContext(GeneticAlgorithmConfigDTO $configAG): void; // ✅ ADICIONADO
}
