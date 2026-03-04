<?php

namespace App\Modules\Horarios\Domain\Evaluation\HardRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class WorkloadExceededRule implements HardRuleInterface {
    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        $cargaEsperada = $context->cargaEsperada();

        foreach ($context->cargaTurma() as $turmaId => $cargaReal) {

            $esperada = $cargaEsperada[$turmaId] ?? null;

            if ($esperada !== null && $cargaReal > $esperada) {
                $penalty += ($cargaReal - $esperada);
            }
        }

        return new RuleResult($penalty, self::class);
    }

    public function isHard(): bool {
        return true;
    }
}
