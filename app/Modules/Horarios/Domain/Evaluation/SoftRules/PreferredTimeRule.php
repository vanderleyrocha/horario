<?php

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class PreferredTimeRule implements SoftRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;

        $preferencias = $context->diasPreferidos();

        foreach ($context->genes() as $gene) {

            $aulaId = $gene->aulaId();
            $preferidos = $preferencias[$aulaId] ?? [];

            if (! empty($preferidos) && ! in_array($gene->diaSemana(), $preferidos)) {
                $penalty++;
            }
        }

        return new RuleResult($penalty, self::class);
    }

    public function isHard(): bool
    {
        return false;
    }
}
