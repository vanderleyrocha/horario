<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class BlockPreservingCrossover implements CrossoverOperatorInterface {
    /**
     * @return Cromossomo[] Array contendo dois filhos.
     */
    public function crossover(
        Cromossomo $parentA,
        Cromossomo $parentB
    ): array {

        $size = $parentA->count();

        if ($size !== $parentB->count()) {
            throw new \RuntimeException(
                'Pais com tamanhos diferentes no crossover.'
            );
        }

        if ($size < 2) {
            return [
                $parentA->copy(),
                $parentB->copy()
            ];
        }

        $start = random_int(0, $size - 2);
        $end   = random_int($start + 1, $size - 1);

        $genesA = $parentA->genes();
        $genesB = $parentB->genes();

        $childGenesA = $genesA;
        $childGenesB = $genesB;

        for ($i = $start; $i <= $end; $i++) {
            $childGenesA[$i] = $genesB[$i];
            $childGenesB[$i] = $genesA[$i];
        }

        return [
            new Cromossomo($childGenesA),
            new Cromossomo($childGenesB),
        ];
    }
}
