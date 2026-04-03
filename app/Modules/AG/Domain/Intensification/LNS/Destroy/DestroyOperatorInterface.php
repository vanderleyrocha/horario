<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Operators\EvolutionaryOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface DestroyOperatorInterface extends EvolutionaryOperatorInterface
{
    public function destroy(Cromossomo $solution): PartialSolution;
}
