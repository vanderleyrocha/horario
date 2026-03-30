<?php

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class DistributionRule implements SoftRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $penalty = 0.0;

        $slotsPorAula = $context->cromossomo()->aulaSlotsIndex();
        $lessons = $context->data()->lessons;

        foreach ($slotsPorAula as $aulaId => $dias) {
            $lesson = $lessons[$aulaId] ?? null;

            // ✅ AÇÃO 02: Não penalizar aulas com aulas_semana < 2
            // Se weeklyOccurrences < 2 (aulas semanais de 1 ocorrência),
            // é intencional ter apenas 1 dia → skip completamente
            if ($lesson !== null && $lesson->weeklyOccurrences < 2) {
                continue;
            }

            // Para aulas que DEVEM ser distribuídas (weeklyOccurrences >= 2),
            // exigir alocação em >= 2 dias diferentes
            if (count($dias) < 2) {
                $penalty++;
            }
        }

        return new RuleResult($penalty, self::class);
    }

    public function isHard(): bool
    {
        return false;
    }
}
