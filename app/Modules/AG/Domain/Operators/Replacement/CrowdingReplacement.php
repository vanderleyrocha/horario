<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Replacement;

use App\Modules\AG\Domain\Metrics\GeneticDistance;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class CrowdingReplacement implements ReplacementStrategyInterface
{
    public function __construct(private GeneticDistance $distance)
    {
    }

    public function replace(array &$population, Cromossomo $incoming): void
    {
        if (empty($population)) {

            $population[] = $incoming;
            return;
        }

        $closestIndex = null;
        $closestDistance = INF;

        foreach ($population as $i => $individual) {

            $d = $this->distance->distance($incoming, $individual);

            if ($d < $closestDistance) {

                $closestDistance = $d;
                $closestIndex = $i;
            }
        }

        if ($closestIndex === null) {
            return;
        }

        if (
            $incoming->fitness() >
            $population[$closestIndex]->fitness()
        ) {

            $population[$closestIndex] = $incoming;
        }
    }
}
