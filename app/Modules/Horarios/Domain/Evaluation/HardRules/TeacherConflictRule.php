<?php

namespace App\Modules\Horarios\Domain\Evaluation\HardRules;

use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\Incremental\IncrementalRule;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class TeacherConflictRule implements HardRuleInterface, IncrementalRule
{
    public function isHard(): bool
    {
        return true;
    }

    /**
     * Avaliação completa
     */
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $index = $context->cromossomo()->professorPeriodoIndex();

        $penalty = 0;

        foreach ($index as $prof => $dias) {

            foreach ($dias as $dia => $periodos) {

                foreach ($periodos as $periodo => $genes) {

                    if (count($genes) > 1) {
                        $penalty += count($genes) - 1;
                    }
                }
            }
        }

        return new RuleResult(
            $penalty,
            self::class
        );
    }

    /**
     * Avaliação incremental
     */
    public function evaluateIncremental(
        EvaluationContext $context,
        AffectedRegion $region
    ): float {

        $index = $context->cromossomo()->professorPeriodoIndex();

        $penalty = 0;

        foreach ($region->professores as $professor) {

            if (! isset($index[$professor])) {
                continue;
            }

            foreach ($index[$professor] as $dia => $periodos) {

                foreach ($periodos as $periodo => $genes) {

                    if (count($genes) > 1) {
                        $penalty += count($genes) - 1;
                    }
                }
            }
        }

        return $penalty;
    }
}
