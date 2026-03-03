<?php

namespace App\Modules\AG\Domain\Fitness\Rules\Hard;

use App\Modules\AG\Domain\Fitness\EvaluationContext;
use App\Modules\AG\Domain\Fitness\Rules\HardRuleInterface;
use App\Modules\AG\Domain\Fitness\Rules\RuleResult;

final class ConflitoProfessorRule implements HardRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;
        $ocupacao = [];

        foreach ($context->genes() as $gene) {
            if ($gene->isEmpty()) continue;
            for ($i = 0; $i < $gene->getDuracaoTempos(); $i++) {

                $slot = $gene->getProfessorId() . '_' . $gene->getDiaSemana() . '_' . ($gene->getPeriodoDia() + $i);

                if (isset($ocupacao[$slot])) {
                    $penalty++;
                }

                $ocupacao[$slot] = true;
            }
        }

        return new RuleResult($penalty);
    }
}
