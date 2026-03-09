<?php

namespace App\Modules\AG\Domain\Fitness\Delta;

use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;

interface DeltaEvaluableRule {
    public function evaluateDelta(
        EvaluationContext $context,
        AffectedRegion $region
    ): float;
}
