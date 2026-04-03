<?php

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class MaxLessonsPerDayRule implements SoftRuleInterface
{
    private int $maxPerDay;

    public function __construct(int $maxPerDay = 7)
    {
        $this->maxPerDay = $maxPerDay;
    }

    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;

        foreach ($context->cargaTurmaPorDia() as $turmaId => $dias) {

            foreach ($dias as $dia => $quantidade) {

                if ($quantidade > $this->maxPerDay) {
                    $penalty += ($quantidade - $this->maxPerDay);
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
