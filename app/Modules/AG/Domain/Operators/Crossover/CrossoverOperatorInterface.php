<?php

namespace App\Modules\AG\Domain\Operators\Crossover;

use App\Modules\AG\Domain\Operators\EvolutionaryOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface CrossoverOperatorInterface extends EvolutionaryOperatorInterface
{
    public function crossover(Cromossomo $parentA, Cromossomo $parentB): array;
}
