<?php

namespace App\Modules\AG\Domain\Fitness\Incremental;

use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;

interface IncrementalRule
{
    public function evaluateIncremental(EvaluationContext $context, AffectedRegion $region): float;
}
