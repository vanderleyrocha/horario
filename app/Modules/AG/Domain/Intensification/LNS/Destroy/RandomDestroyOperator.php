<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class RandomDestroyOperator implements DestroyOperatorInterface {
    public function destroy(Cromossomo $solution): PartialSolution {
        $genes = $solution->genes();

        $removeCount = (int) floor(count($genes) * 0.2);

        $indexes = array_rand($genes, $removeCount);

        $assigned = [];
        $unassigned = [];

        foreach ($genes as $i => $gene) {

            if (in_array($i, (array) $indexes, true)) {
                $unassigned[] = $gene;
            } else {
                $assigned[] = $gene;
            }
        }

        return new PartialSolution($assigned, $unassigned);
    }
}
