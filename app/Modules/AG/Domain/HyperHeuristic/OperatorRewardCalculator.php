<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

final class OperatorRewardCalculator
{
    public function compute(float $before, float $after): float
    {

        if ($before == 0) {
            return 0;
        }

        $delta = $after - $before;

        return $delta / abs($before);
    }
}
