<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\HardRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;

final class BloqueiosHardRule implements HardRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        foreach ($context->genes as $gene) {

            if ($gene->isEmpty()) continue;

            if (
                isset($context->restricoesIndexadas['professor'][$gene->professorId][$gene->diaSemana][$gene->periodoDia])
                ||
                isset($context->restricoesIndexadas['turma'][$gene->turmaId][$gene->diaSemana][$gene->periodoDia])
            ) {
                $penalty++;
            }
        }

        return new RuleResult($penalty);
    }
}
