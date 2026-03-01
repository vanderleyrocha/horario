<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use Illuminate\Support\Facades\Log;

final class BlockPreservingCrossover implements CrossoverOperatorInterface {
    public function crossover(Cromossomo $pai1, Cromossomo $pai2): array {
        $size = $pai1->count();
        if ($size < 2) {
            return [$pai1->copy(), $pai2->copy()];
        }

        $start = random_int(0, $size - 2);
        $end   = random_int($start + 1, $size - 1);

        $p1 = array_values($pai1->getGenes());
        $p2 = array_values($pai2->getGenes());

        $child1 = $p1;
        $child2 = $p2;

        for ($i = $start; $i <= $end; $i++) {
            $child1[$i] = $p2[$i];
            $child2[$i] = $p1[$i];
        }

        return [
            new Cromossomo($child1),
            new Cromossomo($child2),
        ];
    }
}
