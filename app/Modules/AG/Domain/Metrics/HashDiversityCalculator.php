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
     * @param Cromossomo[] $population
     */
    public function calculate(array $population): float
    {
        $n = count($population);

        if ($n <= 1) {
            return 0.0;
        }

        $counts = [];

        foreach ($population as $c) {

            $sig = $c->signature();

            $counts[$sig] = ($counts[$sig] ?? 0) + 1;
        }

        /*
        Simpson diversity index
        */

        $sum = 0.0;

        foreach ($counts as $count) {

            $p = $count / $n;

            $sum += $p * $p;
        }

        return 1.0 - $sum;
    }
}
