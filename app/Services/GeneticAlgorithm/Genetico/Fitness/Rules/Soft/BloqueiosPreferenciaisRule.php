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

            $preferidos = $context->diasPreferidos[$gene->getAulaId()] ?? [];

            if (!in_array($gene->getDiaSemana(), $preferidos)) {
                $penalty++;
            }
        }

        return new RuleResult($penalty);
    }
}