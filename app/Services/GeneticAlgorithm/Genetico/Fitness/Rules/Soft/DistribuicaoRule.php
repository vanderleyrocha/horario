<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\SoftRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;

final class DistribuicaoRule implements SoftRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;
        $map = [];

        foreach ($context->genes as $gene) {

            if ($gene->isEmpty() || $gene->aulaId === null) {
                continue;
            }

            $map[$gene->aulaId][$gene->diaSemana] = true;
        }

        foreach ($map as $dias) {

            if (count($dias) < 2) {
                $penalty++;
            }
        }

        return new RuleResult($penalty);
    }
}