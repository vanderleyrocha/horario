<?php

namespace App\Modules\Horarios\Domain\Evaluation\HardRules;

use App\Modules\AG\Domain\Fitness\Incremental\IncrementalRule;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\Delta\Dependency\RuleDependency;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class ClassConflictRule implements HardRuleInterface, IncrementalRule
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;

        $index = $context->cromossomo()->turmaPeriodoIndex();

        foreach ($index as $turmaId => $dias) {

            foreach ($dias as $dia => $periodos) {

                foreach ($periodos as $periodo => $genes) {

                    if (count($genes) > 1) {
                        $penalty += count($genes) - 1;
                    }
                }
            }
        }

        return new RuleResult($penalty, self::class);
    }

    /**
     * Incremental evaluation O(1)
     */
    public function evaluateIncremental(EvaluationContext $context, AffectedRegion $region): float
    {

        $penalty = 0.0;

        $index = $context->cromossomo()->turmaPeriodoIndex();

        foreach ($region->turmas as $turmaId) {

            if (!isset($index[$turmaId])) {
                continue;
            }

            foreach ($index[$turmaId] as $dia => $periodos) {

                foreach ($periodos as $periodo => $genes) {

                    if (count($genes) > 1) {
                        $penalty += count($genes) - 1;
                    }
                }
            }
        }

        return $penalty;
    }

    public function dependencies(): array
    {
        return [
            RuleDependency::TURMA,
            RuleDependency::SLOT
        ];
    }

    public function isHard(): bool
    {
        return true;
    }
}
