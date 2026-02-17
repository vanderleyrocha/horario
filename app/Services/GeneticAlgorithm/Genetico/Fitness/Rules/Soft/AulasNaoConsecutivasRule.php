<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\SoftRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;

final class AulasNaoConsecutivasRule implements SoftRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;
        $map = [];

        foreach ($context->genes as $gene) {
            if ($gene->isEmpty()) continue;
            $map[$gene->getTurmaId()][$gene->getDiaSemana()][] = $gene->getPeriodoDia();
        }

        foreach ($map as $dias) {

            foreach ($dias as $periodos) {

                sort($periodos);

                for ($i = 1; $i < count($periodos); $i++) {

                    if ($periodos[$i] - $periodos[$i - 1] === 1) {
                        $penalty++;
                    }
                }
            }
        }

        return new RuleResult($penalty);
    }
}