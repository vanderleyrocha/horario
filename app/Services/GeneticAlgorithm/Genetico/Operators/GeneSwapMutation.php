<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

final class GeneSwapMutation implements MutationOperatorInterface
{
    public function mutate(Cromossomo $cromossomo): void
    {
        $size = count($cromossomo->genes);

        if ($size < 2) {
            return;
        }

        // Escolhe dois índices distintos
        $i = random_int(0, $size - 1);
        $j = random_int(0, $size - 1);

        if ($i === $j) {
            return;
        }

        // Swap estrutural
        $temp = $cromossomo->genes[$i];
        $cromossomo->genes[$i] = $cromossomo->genes[$j];
        $cromossomo->genes[$j] = $temp;

        // Reconstruir índices para manter consistência
        $cromossomo->rebuildIndexes();

        // Fitness será recalculado pelo loop principal
        $cromossomo->setFitness(INF);
    }
}