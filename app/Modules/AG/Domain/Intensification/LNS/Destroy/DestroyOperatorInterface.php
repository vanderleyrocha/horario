<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;

interface DestroyOperatorInterface {
    public function destroy(Cromossomo $solution): PartialSolution;
}
