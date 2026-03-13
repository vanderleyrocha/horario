<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Operators\EvolutionaryOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface RepairOperatorInterface extends EvolutionaryOperatorInterface
{
    public function repair(PartialSolution $partial): Cromossomo;
}
