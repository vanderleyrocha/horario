<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

interface CrossoverOperatorInterface {

    public function crossover(Cromossomo $parentA, Cromossomo $parentB): array;
}
