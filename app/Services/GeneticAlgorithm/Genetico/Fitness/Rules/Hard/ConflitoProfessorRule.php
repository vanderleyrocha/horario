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