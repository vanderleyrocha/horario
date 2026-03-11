<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

final class OperatorRewardCalculator
{
    /**
     * Peso aplicado quando o operador piora a solução
     */
    private float $penaltyFactor;

    public function __construct(float $penaltyFactor = 0.1)
    {
        $this->penaltyFactor = $penaltyFactor;
    }

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
        | Penalidade reduzida para piora
        |----------------------------------------------
        */

        if ($delta < 0) {
            return $delta * $this->penaltyFactor;
        }

        /*
        |----------------------------------------------
        | Nenhuma mudança
        |----------------------------------------------
        */

        return 0.0;
    }

    /**
     * Calcula recompensa normalizada
     * útil para algoritmos Softmax
     */
    public function normalized(float $reward, float $scale = 100.0): float
    {
        return $reward / $scale;
    }
}
