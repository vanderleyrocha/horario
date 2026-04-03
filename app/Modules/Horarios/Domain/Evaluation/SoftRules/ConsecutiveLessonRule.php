<?php

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class ConsecutiveLessonRule implements SoftRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;

        $slotsPorAula = $context->cromossomo()->aulaSlotsIndex();

        foreach ($slotsPorAula as $aulaId => $dias) {

            foreach ($dias as $dia => $periodos) {

                sort($periodos);

                for ($i = 1; $i < count($periodos); $i++) {

                    if ($periodos[$i] - $periodos[$i - 1] !== 1) {
                        $penalty++;
                    }
                }
            }
        }

        return new RuleResult($penalty, self::class);
    }

    public function isHard(): bool
    {
        return false;
    }
}
