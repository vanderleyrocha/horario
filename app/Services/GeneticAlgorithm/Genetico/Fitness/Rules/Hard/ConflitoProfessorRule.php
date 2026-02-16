<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\HardRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;

final class ConflitoProfessorRule implements HardRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;
        $ocupacao = [];

        foreach ($context->genes as $gene) {

            if ($gene->isEmpty() || $gene->professorId === null) {
                continue;
            }

            for ($i = 0; $i < $gene->duracaoTempos; $i++) {

                $slot = $gene->professorId . '_' .
                        $gene->diaSemana . '_' .
                        ($gene->periodoDia + $i);

                if (isset($ocupacao[$slot])) {
                    $penalty++;
                }

                $ocupacao[$slot] = true;
            }
        }

        return new RuleResult($penalty);
    }
}