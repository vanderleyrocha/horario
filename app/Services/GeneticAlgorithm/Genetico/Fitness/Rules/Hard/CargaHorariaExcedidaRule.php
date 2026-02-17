<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\HardRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;

final class CargaHorariaExcedidaRule implements HardRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;
        $contador = [];

        foreach ($context->genes as $gene) {
            if ($gene->isEmpty()) continue;
            $contador[$gene->getAulaId()] = ($contador[$gene->getAulaId()] ?? 0) + 1;
        }

        foreach ($contador as $aulaId => $quantidade) {

            $esperado = $context->cargaEsperada[$aulaId] ?? null;

            if ($esperado !== null && $quantidade > $esperado) {
                $penalty += ($quantidade - $esperado);
            }
        }

        return new RuleResult($penalty);
    }
}