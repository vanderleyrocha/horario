<?php

namespace App\Modules\AG\Domain\Operators\Replacement;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface ReplacementStrategyInterface {
    /**
     * @param Cromossomo[] $population
     */
    public function replace(array &$population, Cromossomo $incoming): void;
}
