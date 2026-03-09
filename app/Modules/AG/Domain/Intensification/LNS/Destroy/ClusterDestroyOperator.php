<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class ClusterDestroyOperator implements DestroyOperatorInterface {
    public function destroy(Cromossomo $solution): PartialSolution {
        $genes = $solution->genes();

        $targetTurma =
            $genes[array_rand($genes)]->turmaId();

        $assigned = [];
        $unassigned = [];

        foreach ($genes as $gene) {

            if ($gene->turmaId() === $targetTurma) {
                $unassigned[] = $gene;
            } else {
                $assigned[] = $gene;
            }
        }

        return new PartialSolution($assigned, $unassigned);
    }
}
