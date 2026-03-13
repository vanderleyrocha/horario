<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class LNSRepairAdapter implements RepairOperatorInterface
{
    public function __construct(private readonly GreedyRepairOperator $repairOperator, private readonly ScheduleData $data)
    {
    }

    public function repair(PartialSolution $partial): Cromossomo
    {
        // usar os getters corretos da PartialSolution
        $genes = array_merge($partial->assigned(), $partial->unassigned());

        $cromossomo = new Cromossomo($genes);

        return $this->repairOperator->repair($cromossomo, $this->data);
    }

    public function getName(): string
    {
        return 'LNSRepairAdapter';
    }
}
