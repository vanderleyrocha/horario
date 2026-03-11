<?php

namespace App\Modules\AG\Domain\Evaluation\Window;

final class WindowCalculator
{
    public static function compute(array $periods): int
    {
        if (empty($periods)) {
            return 0;
        }

        $periods = array_unique($periods);

        sort($periods);

        $windows = 0;
        $prev = null;

        foreach ($periods as $period) {

            if ($prev !== null && $period - $prev > 1) {
                $windows += ($period - $prev - 1);
            }

            $prev = $period;
        }

        return $windows;
    }
}
