<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Evaluators;

use App\Modules\Horarios\Domain\Constraints\Results\ConstraintEvaluationSummary;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;

interface ConstraintEvaluatorInterface
{
    public function supports(CustomConstraintData $constraint): bool;

    public function evaluate(CustomConstraintData $constraint, EvaluationContext $context): ConstraintEvaluationSummary;
}
