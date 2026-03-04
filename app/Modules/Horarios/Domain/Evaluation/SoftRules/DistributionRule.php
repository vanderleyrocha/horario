<?php

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class DistributionRule implements SoftRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        $alocacoes = $context->alocacoesPorAula();

        foreach ($alocacoes as $aulaId => $slots) {

            $dias = [];

            foreach ($slots as $slot) {
                $dias[$slot['dia']] = true;
            }

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
