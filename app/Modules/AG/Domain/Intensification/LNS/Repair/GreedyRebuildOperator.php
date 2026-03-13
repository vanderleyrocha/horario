<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class GreedyRebuildOperator implements RepairOperatorInterface
{
    public function repair(PartialSolution $partial): Cromossomo
    {
        // usar getters da PartialSolution
        $genes = $partial->assigned();

        foreach ($partial->unassigned() as $gene) {

            // versão simples (placeholder)
            // posteriormente pode virar um regret insertion

            $genes[] = $gene;
        }

        return new Cromossomo($genes);
    }

    public function getName(): string
    {
        return 'GreedyRebuildOperator';
    }
}
