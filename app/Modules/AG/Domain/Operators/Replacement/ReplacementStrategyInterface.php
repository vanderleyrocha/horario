<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Replacement;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface ReplacementStrategyInterface
{
    /**
     * Substitui um indivíduo da população.
     *
     * @param Cromossomo[] $population
     */
    public function replace(array &$population, Cromossomo $incoming): void;
}
