<?php

declare(strict_types=1);

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

final class SinglePointCrossover implements CrossoverOperatorInterface {
    public function crossover(Cromossomo $pai1, Cromossomo $pai2): array {
        $size = $pai1->count();

        if ($size === 0) {
            return [$pai1->copy(), $pai2->copy()];
        }

        $point = random_int(1, $size - 1);

        $genes1 = [];
        $genes2 = [];

        $p1Genes = $pai1->getGenes();
        $p2Genes = $pai2->getGenes();

        for ($i = 0; $i < $size; $i++) {
            if ($i < $point) {
                $genes1[] = $p1Genes[$i];
                $genes2[] = $p2Genes[$i];
            } else {
                $genes1[] = $p2Genes[$i];
                $genes2[] = $p1Genes[$i];
            }
        }

        return [
            new Cromossomo($genes1),
            new Cromossomo($genes2),
        ];
    }
}
