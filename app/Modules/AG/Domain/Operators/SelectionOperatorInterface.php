<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

interface SelectionOperatorInterface {
    /**
     * Seleciona N indivíduos da população.
     *
     * @param Cromossomo[] $population
     * @param int $count
     * @return Cromossomo[]
     */
    public function select(array $population, int $count): array;
}
