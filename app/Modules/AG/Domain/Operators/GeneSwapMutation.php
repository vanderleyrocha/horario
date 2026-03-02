<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Core\Entities\Gene;

final class GeneSwapMutation implements MutationOperatorInterface {
    public function mutate(Cromossomo $cromossomo): void {
        $size = $cromossomo->count();

        if ($size < 2) {
            return;
        }

        $genes = $cromossomo->genes();

        $indexA = random_int(0, $size - 1);
        $indexB = random_int(0, $size - 1);

        if ($indexA === $indexB) {
            return;
        }

        $geneA = $genes[$indexA];
        $geneB = $genes[$indexB];

        // Evita swaps entre durações diferentes
        if ($geneA->duracaoTempos() !== $geneB->duracaoTempos()) {
            return;
        }

        // Evita conflito estrutural direto
        if ($geneA->conflictsWith($geneB)) {
            return;
        }

        $cromossomo->swapGenes($indexA, $indexB);
    }
}
