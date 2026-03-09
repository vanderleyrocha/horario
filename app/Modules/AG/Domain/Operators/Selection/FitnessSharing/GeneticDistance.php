<?php

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class GeneticDistance {
    public function hamming(Cromossomo $a, Cromossomo $b): float {
        $genesA = $a->genes();
        $genesB = $b->genes();

        $size = min(count($genesA), count($genesB));

        if ($size === 0) {
            return 0.0;
        }

        $distance = 0;

        for ($i = 0; $i < $size; $i++) {

            if (
                $genesA[$i]->diaSemana() !== $genesB[$i]->diaSemana()
                ||
                $genesA[$i]->periodoDia() !== $genesB[$i]->periodoDia()
            ) {
                $distance++;
            }
        }

        return $distance / $size;
    }
}
