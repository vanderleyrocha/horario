<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class HashDiversityCalculator implements DiversityCalculatorInterface
{
    /**
     * Diversidade baseada em assinatura genética
     * Complexidade O(n)
     *
     * Usa Simpson Diversity Index
     */
    public function calculate(array $population): float
    {
        $n = count($population);

        if ($n <= 1) {
            return 0.0;
        }

        $counts = [];

        foreach ($population as $c) {

            /** @var Cromossomo $c */
            $sig = $c->signature();

            if (isset($counts[$sig])) {
                $counts[$sig]++;
            } else {
                $counts[$sig] = 1;
            }
        }

        /*
         |---------------------------------------------
         | Simpson diversity index
         |---------------------------------------------
         |
         | D = 1 − Σ(p_i²)
         |
         */

        $sum = 0.0;

        foreach ($counts as $count) {

            $p = $count / $n;

            $sum += $p * $p;
        }

        return 1.0 - $sum;
    }
}
