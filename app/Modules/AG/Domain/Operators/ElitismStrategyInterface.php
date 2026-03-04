<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

interface ElitismStrategyInterface {
    /**
     * Seleciona os indivíduos elite da população atual.
     *
     * @param Cromossomo[] $population
     * @return Cromossomo[]
     */
    public function selectElites(array $population): array;
}
