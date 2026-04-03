<?php

namespace App\Modules\Horarios\Domain\Evaluation\HardRules;

use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\Delta\Dependency\RuleDependency;
use App\Modules\AG\Domain\Fitness\Incremental\IncrementalRule;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class WorkloadExceededRule implements HardRuleInterface, IncrementalRule
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;

        $cargaEsperada = $context->cargaEsperada();
        $cargaTurma = $context->cargaTurma();

        foreach ($cargaTurma as $turmaId => $cargaReal) {

            $esperada = $cargaEsperada[$turmaId] ?? null;

            if ($esperada !== null && $cargaReal > $esperada) {
                $penalty += ($cargaReal - $esperada);
            }
        }

        return new RuleResult($penalty, self::class);
    }

    /**
     * Avaliação incremental O(1)
     */
    public function evaluateIncremental(EvaluationContext $context, AffectedRegion $region): float
    {

        $penalty = 0.0;

        $cargaEsperada = $context->cargaEsperada();
        $cargaTurma = $context->cargaTurma();

        foreach ($region->turmas as $turmaId) {

            $real = $cargaTurma[$turmaId] ?? 0;
            $esperada = $cargaEsperada[$turmaId] ?? null;

            if ($esperada !== null && $real > $esperada) {
                $penalty += ($real - $esperada);
            }
        }

        return $penalty;
    }

    public function dependencies(): array
    {
        return [
            RuleDependency::TURMA,
        ];
    }

    public function isHard(): bool
    {
        return true;
    }
}
