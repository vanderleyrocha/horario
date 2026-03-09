<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

final class OperatorRewardCalculator
{
    /**
     * Calcula recompensa baseada na melhoria de fitness
     */
    public function calculate(float $beforeFitness, float $afterFitness): float
    {

        $delta = $afterFitness - $beforeFitness;

        /*
        |----------------------------------------------
        | Melhoria positiva
        |----------------------------------------------
        */

        if ($delta > 0) {
            return $delta;
        }

        /*
        |----------------------------------------------
        | Penalidade pequena se piorar
        |----------------------------------------------
        */

        if ($delta < 0) {
            return $delta * 0.1;
        }

        /*
        |----------------------------------------------
        | Nenhuma mudança
        |----------------------------------------------
        */

        return 0.0;
    }
}
