<?php

namespace App\Modules\AG\Domain\Fitness\Rules\Hard;

use App\Modules\AG\Domain\Fitness\EvaluationContext;
use App\Modules\AG\Domain\Fitness\Rules\HardRuleInterface;
use App\Modules\AG\Domain\Fitness\Rules\RuleResult;

final class BloqueiosHardRule implements HardRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        foreach ($context->genes() as $gene) {
            if ($gene->isEmpty()) continue;
            if (
                isset($context->restricoesIndexadas['professor'][$gene->getProfessorId()][$gene->getDiaSemana()][$gene->getPeriodoDia()])
                ||
                isset($context->restricoesIndexadas['turma'][$gene->getTurmaId()][$gene->getDiaSemana()][$gene->getPeriodoDia()])
            ) {
                $penalty++;
            }
        }

        return new RuleResult($penalty);
    }
}
