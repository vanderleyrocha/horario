<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS\Acceptance;

use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface AlnsAcceptanceCriterion
{
    public function shouldAccept(Cromossomo $current, FitnessResult $currentResult, Cromossomo $candidate, FitnessResult $candidateResult, int $generation, int $stagnation): bool;
}
