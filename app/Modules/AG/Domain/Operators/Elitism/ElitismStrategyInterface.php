<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Elitism;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface ElitismStrategyInterface {
    /**
     * Seleciona os indivíduos elite da população atual.
     *
     * @param Cromossomo[] $population
     * @return Cromossomo[]
     */
    public function selectElites(array $population): array;
}
