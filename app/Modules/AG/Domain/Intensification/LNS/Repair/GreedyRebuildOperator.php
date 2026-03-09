<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class GreedyRebuildOperator implements RepairOperatorInterface {
    public function repair(PartialSolution $partial): Cromossomo {
        $genes = $partial->assignedGenes;

        foreach ($partial->unassignedGenes as $gene) {

            // placeholder simples
            // posteriormente implementar busca de melhor slot

            $genes[] = $gene;
        }

        return new Cromossomo($genes);
    }
}
