<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

final class StructuredSwapMutation implements MutationOperatorInterface
{
    public function mutate(Cromossomo $cromossomo): void
    {
        $size = $cromossomo->count();
        if ($size < 2) {
            return;
        }

        $i = random_int(0, $size - 1);
        $j = random_int(0, $size - 1);

        $cromossomo->swapGenes($i, $j);
    }
}