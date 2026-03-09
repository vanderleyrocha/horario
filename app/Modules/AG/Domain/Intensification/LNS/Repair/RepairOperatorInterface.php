<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface RepairOperatorInterface {
    public function repair(PartialSolution $partial): Cromossomo;
}
