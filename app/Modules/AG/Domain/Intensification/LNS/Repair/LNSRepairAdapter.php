<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class LNSRepairAdapter implements RepairOperatorInterface
{
    public function __construct(private readonly GreedyRepairOperator $repairOperator, private readonly ScheduleData $data)
    {
    }

    public function repair(PartialSolution $partial, array $context = []): Cromossomo
    {
        $abortIfTimedOut = is_callable($context['abort_if_timed_out'] ?? null)
            ? $context['abort_if_timed_out']
            : null;
        $progressHeartbeat = is_callable($context['progress_heartbeat'] ?? null)
            ? $context['progress_heartbeat']
            : null;
        $limits = is_array($context['limits'] ?? null)
            ? $context['limits']
            : [];

        if ($abortIfTimedOut !== null) {
            $abortIfTimedOut('lns_repair_adapter_before_merge');
        }

        // usar os getters corretos da PartialSolution
        $genes = array_merge($partial->assigned(), $partial->unassigned());

        $cromossomo = new Cromossomo($genes);

        if ($abortIfTimedOut !== null) {
            $abortIfTimedOut('lns_repair_adapter_before_repair');
        }

        $repaired = $this->repairOperator->repair(
            $cromossomo,
            $this->data,
            fitnessProbe: null,
            progressHeartbeat: $progressHeartbeat,
            limits: $limits,
        );

        if ($abortIfTimedOut !== null) {
            $abortIfTimedOut('lns_repair_adapter_after_repair');
        }

        return $repaired;
    }

    public function getName(): string
    {
        return 'LNSRepairAdapter';
    }
}
