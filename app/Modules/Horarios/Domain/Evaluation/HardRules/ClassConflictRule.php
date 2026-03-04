<?php

namespace App\Modules\Horarios\Domain\Evaluation\HardRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class ClassConflictRule implements HardRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        $ocupacao = [];

        foreach ($context->genes() as $gene) {

            for ($i = 0; $i < $gene->duracaoTempos(); $i++) {

                $tempo = $gene->periodoDia() + $i;

                $slot = $gene->turmaId()
                    . '_' . $gene->diaSemana()
                    . '_' . $tempo;

                if (isset($ocupacao[$slot])) {
                    $penalty++;
                }

                $ocupacao[$slot] = true;
            }
        }

        return new RuleResult($penalty, self::class);
    }

    public function isHard(): bool {
        return true;
    }
}
