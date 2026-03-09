<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class PopulationEntropyCalculator {
    /**
     * @param Cromossomo[] $population
     */
    public function calculate(array $population): float {
        $n = count($population);

        if ($n === 0) {
            return 0.0;
        }

        // Frequência de cada assinatura genética
        $counts = [];

        foreach ($population as $c) {
            $sig = $c->signature();
            $counts[$sig] = ($counts[$sig] ?? 0) + 1;
        }

        // Entropia de Shannon
        $entropy = 0.0;

        foreach ($counts as $count) {
            $p = $count / $n;
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }

    /**
     * Normaliza a entropia para [0,1]
     */
    public function normalized(array $population): float {
        $n = count($population);

        if ($n <= 1) {
            return 0.0;
        }

        $entropy = $this->calculate($population);

        $maxEntropy = log($n, 2);

        return $maxEntropy > 0 ? $entropy / $maxEntropy : 0.0;
    }
}
