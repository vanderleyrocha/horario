<?php

namespace App\Modules\AG\Domain\Fitness\Rules;

use App\Modules\AG\Domain\Fitness\EvaluationContext;

interface FitnessRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult;
}
