<?php

namespace App\Modules\AG\Domain\Intensification\LNS;

use App\Modules\AG\Domain\Intensification\LNS\Destroy\DestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RepairOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class LargeNeighborhoodSearch
{
    private DestroyOperatorInterface $destroy;

    private RepairOperatorInterface $repair;

    public function __construct(
        DestroyOperatorInterface $destroy,
        RepairOperatorInterface $repair,
    ) {
        $this->destroy = $destroy;
        $this->repair = $repair;
    }

    public function improve(Cromossomo $solution, array $context = []): Cromossomo
    {
        $partial = $this->destroy->destroy($solution);

        return $this->repair->repair($partial, $context);
    }
}
