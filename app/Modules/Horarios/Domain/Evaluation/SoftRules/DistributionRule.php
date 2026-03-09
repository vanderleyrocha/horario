<?php

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class DistributionRule implements SoftRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        $slotsPorAula = $context->cromossomo()->aulaSlotsIndex();

        foreach ($slotsPorAula as $aulaId => $dias) {

            if (count($dias) < 2) {
                $penalty++;
            }
        }

        return new RuleResult($penalty, self::class);
    }

    public function isHard(): bool {
        return false;
    }
}
