<?php

namespace App\Modules\AG\Domain\Fitness\Rules\Hard;

use App\Modules\AG\Domain\Fitness\EvaluationContext;
use App\Modules\AG\Domain\Fitness\Rules\HardRuleInterface;
use App\Modules\AG\Domain\Fitness\Rules\RuleResult;

final class CargaHorariaExcedidaRule implements HardRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;
        $contador = [];

        foreach ($context->genes() as $gene) {
            if ($gene->isEmpty()) continue;
            $contador[$gene->getAulaId()] = ($contador[$gene->getAulaId()] ?? 0) + 1;
        }

        foreach ($contador as $aulaId => $quantidade) {
            $carga = $context->cargaEsperada();
            $esperado = $carga[$aulaId] ?? null;

            if ($esperado !== null && $quantidade > $esperado) {
                $penalty += ($quantidade - $esperado);
            }
        }

        return new RuleResult($penalty);
    }
}
