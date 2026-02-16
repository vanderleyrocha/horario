<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

final class ConflictGuidedMutation implements MutationOperatorInterface
{
    public function mutate(Cromossomo $cromossomo): void
    {
        $genes = $cromossomo->getGenes();
        $size = $cromossomo->count();

        if ($size === 0) {
            return;
        }

        $index = random_int(0, $size - 1);
        $gene = $genes[$index];

        $novoDia = random_int(1, 5);
        $novoPeriodo = random_int(1, 6);

        $novoGene = $gene->withDiaPeriodo($novoDia, $novoPeriodo);

        $cromossomo->replaceGene($index, $novoGene);
    }
}