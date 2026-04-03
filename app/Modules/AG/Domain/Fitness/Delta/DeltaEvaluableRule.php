<?php

namespace App\Modules\AG\Domain\Fitness\Delta;

use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;

interface DeltaEvaluableRule
{
    public function evaluateDelta(
        EvaluationContext $context,
        AffectedRegion $region
    ): float;
}
