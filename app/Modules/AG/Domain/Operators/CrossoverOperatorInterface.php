<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

interface CrossoverOperatorInterface {
    /**
     * Executa crossover entre dois pais.
     *
     * @return Cromossomo[] Array contendo dois filhos.
     */
    public function crossover(
        Cromossomo $parentA,
        Cromossomo $parentB
    ): array;
}
