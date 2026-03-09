<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Replacement;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class WorstIndividualReplacement implements ReplacementStrategyInterface {
    public function __construct(
        private readonly int $eliteProtection = 2
    ) {
    }

    public function replace(array &$population, Cromossomo $incoming): void {
        if (empty($population)) {
            $population[] = $incoming;
            return;
        }

        usort(
            $population,
            fn(Cromossomo $a, Cromossomo $b) =>
            $b->fitness() <=> $a->fitness()
        );

        $index = count($population) - 1;

        if ($index < $this->eliteProtection) {
            $population[] = $incoming;
            return;
        }

        $population[$index] = $incoming;
    }
}
