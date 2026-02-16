<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\HardRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;

final class ConflitoTurmaRule implements HardRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;
        $ocupacao = [];

        foreach ($context->genes as $gene) {

            if ($gene->isEmpty() || $gene->turmaId === null) {
                continue;
            }

            for ($i = 0; $i < $gene->duracaoTempos; $i++) {

                $slot = $gene->turmaId . '_' .
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