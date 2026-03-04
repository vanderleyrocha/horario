<?php

namespace App\Modules\Horarios\Domain\Evaluation\Contracts;

use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

interface RuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult;

    public function isHard(): bool;
}
