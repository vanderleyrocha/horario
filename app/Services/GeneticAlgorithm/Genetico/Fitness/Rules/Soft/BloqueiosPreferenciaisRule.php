<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\SoftRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;

final class BloqueiosPreferenciaisRule implements SoftRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;

        foreach ($context->genes as $gene) {

            if ($gene->isEmpty() || $gene->aulaId === null) {
                continue;
            }

            $preferidos =
                $context->diasPreferidos[$gene->aulaId] ?? [];

            if (!in_array($gene->diaSemana, $preferidos)) {
                $penalty++;
            }
        }

        return new RuleResult($penalty);
    }
}