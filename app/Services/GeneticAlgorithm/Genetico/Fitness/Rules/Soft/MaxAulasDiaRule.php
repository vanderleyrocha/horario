<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\SoftRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;

final class MaxAulasDiaRule implements SoftRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;
        $contador = [];

        foreach ($context->genes as $gene) {
            if ($gene->isEmpty()) continue;
            $contador[$gene->getTurmaId()][$gene->getDiaSemana()] = ($contador[$gene->getTurmaId()][$gene->getDiaSemana()] ?? 0) + 1;
        }

        $max = $context->maxAulasDia ?? 7;

        foreach ($contador as $dias) {

            foreach ($dias as $quantidade) {

                if ($quantidade > $max) {
                    $penalty += ($quantidade - $max);
                }
            }
        }

        return new RuleResult($penalty);
    }
}
