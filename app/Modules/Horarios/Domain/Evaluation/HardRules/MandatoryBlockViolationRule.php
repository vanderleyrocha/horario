<?php

namespace App\Modules\Horarios\Domain\Evaluation\HardRules;

use App\Modules\AG\Domain\Fitness\Dependency\RuleDependency;
use App\Modules\AG\Domain\Fitness\Incremental\IncrementalRule;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class MandatoryBlockViolationRule implements HardRuleInterface, IncrementalRule {

    public function evaluate(EvaluationContext $context): RuleResult {
        $penalty = 0.0;

        $slotsPorAula = $context->cromossomo()->aulaSlotsIndex();

        foreach ($slotsPorAula as $aulaId => $dias) {

            foreach ($dias as $dia => $periodos) {

                sort($periodos);

                for ($i = 1; $i < count($periodos); $i++) {

                    if ($periodos[$i] - $periodos[$i - 1] !== 1) {
                        $penalty++;
                    }
                }
            }
        }

        return new RuleResult($penalty, self::class);
    }

    /**
     * Incremental evaluation
     */
    public function evaluateIncremental(EvaluationContext $context, AffectedRegion $region): float {


        $slotsPorAula = $context->cromossomo()->aulaSlotsIndex();

        $affectedAulas = [];

        foreach ($region->geneIndexes as $index) {

            $gene = $context->gene($index);

            $affectedAulas[$gene->aulaId()] = true;
        }

        $penalty = 0.0;

        foreach (array_keys($affectedAulas) as $aulaId) {

            if (!isset($slotsPorAula[$aulaId])) {
                continue;
            }

            foreach ($slotsPorAula[$aulaId] as $dia => $periodos) {

                sort($periodos);

                for ($i = 1; $i < count($periodos); $i++) {

                    if ($periodos[$i] - $periodos[$i - 1] !== 1) {
                        $penalty++;
                    }
                }
            }
        }

        return $penalty;
    }

    public function dependencies(): array {
        return [
            RuleDependency::SLOT
        ];
    }

    public function isHard(): bool {
        return true;
    }
}
