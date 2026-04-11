<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class GreedyRebuildOperator implements RepairOperatorInterface
{
    public function repair(PartialSolution $partial, array $context = []): Cromossomo
    {
        $abortIfTimedOut = is_callable($context['abort_if_timed_out'] ?? null)
            ? $context['abort_if_timed_out']
            : null;

        // usar getters da PartialSolution
        $genes = $partial->assigned();

        foreach ($partial->unassigned() as $gene) {
            if ($abortIfTimedOut !== null) {
                $abortIfTimedOut('greedy_rebuild_append_gene');
            }

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
