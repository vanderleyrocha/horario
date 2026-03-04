<?php

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class ConsecutiveLessonRule implements SoftRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        $alocacoes = $context->alocacoesPorAula();

        foreach ($alocacoes as $aulaId => $slots) {

            for ($i = 1; $i < count($slots); $i++) {

                $anterior = $slots[$i - 1];
                $atual = $slots[$i];

                if ($anterior['dia'] === $atual['dia']) {

                    if ($atual['periodo'] - $anterior['periodo'] !== 1) {
                        $penalty++;
                    }
                }
            }
        }

        return new RuleResult($penalty, self::class);
    }

    public function isHard(): bool {
        return false;
    }
}
