<?php

namespace App\Modules\AG\Domain\Fitness\Rules\Soft;

use App\Modules\AG\Domain\Fitness\EvaluationContext;
use App\Modules\AG\Domain\Fitness\Rules\SoftRuleInterface;
use App\Modules\AG\Domain\Fitness\Rules\RuleResult;

final class BloqueiosPreferenciaisRule implements SoftRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        foreach ($context->genes() as $gene) {
            if ($gene->isEmpty()) continue;
            $preferidos = $context->diasPreferidos()[$gene->getAulaId()] ?? [];

            if (!in_array($gene->getDiaSemana(), $preferidos)) {
                $penalty++;
            }
        }

        return new RuleResult($penalty);
    }
}
