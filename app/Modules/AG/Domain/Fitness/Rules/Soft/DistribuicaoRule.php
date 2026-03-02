<?php

namespace App\Modules\AG\Domain\Fitness\Rules\Soft;

use App\Modules\AG\Domain\Fitness\EvaluationContext;
use App\Modules\AG\Domain\Fitness\Rules\SoftRuleInterface;
use App\Modules\AG\Domain\Fitness\Rules\RuleResult;

final class DistribuicaoRule implements SoftRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;
        $map = [];

        foreach ($context->genes as $gene) {
            if ($gene->isEmpty()) continue;
            $map[$gene->getAulaId()][$gene->getDiaSemana()] = true;
        }

        foreach ($map as $dias) {

            if (count($dias) < 2) {
                $penalty++;
            }
        }

        return new RuleResult($penalty);
    }
}
