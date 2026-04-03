<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS\DTO;

use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class AlnsStepResult
{
    public function __construct(public readonly Cromossomo $current, public readonly FitnessResult $currentEvaluation, public readonly Cromossomo $candidate, public readonly FitnessResult $candidateEvaluation, public readonly Cromossomo $selected, public readonly FitnessResult $selectedEvaluation, public readonly bool $accepted, public readonly float $rawImprovement, public readonly float $acceptedImprovement, public readonly float $reward, public readonly ?string $destroyOperator, public readonly ?string $repairOperator) {}
}
