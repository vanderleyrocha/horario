<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Evaluators;

use App\Modules\Horarios\Domain\Constraints\Results\ConstraintEvaluationSummary;
use App\Modules\Horarios\Domain\Constraints\Results\ConstraintViolationResult;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;

final class TimePlacementConstraintEvaluator implements ConstraintEvaluatorInterface
{
    public function supports(CustomConstraintData $constraint): bool
    {
        return $constraint->type === 'TIME_PLACEMENT';
    }

    public function evaluate(CustomConstraintData $constraint, EvaluationContext $context): ConstraintEvaluationSummary
    {
        $summary = ConstraintEvaluationSummary::empty();
        $payload = $constraint->payload;
        $targetLessonIds = $this->normalizeLessonIds($payload['target_group']['lesson_ids'] ?? []);
        $mode = strtoupper((string) ($payload['mode'] ?? 'REQUIRED'));
        $allowedDays = $this->normalizeList($payload['allowed_days'] ?? []);
        $allowedPeriods = $this->normalizeList($payload['allowed_periods'] ?? []);
        $allocations = $context->alocacoesPorAula();

        foreach ($targetLessonIds as $lessonId) {
            foreach ($allocations[$lessonId] ?? [] as $slot) {
                $day = (int) $slot['dia'];
                $period = (int) $slot['periodo'];
                $matchesWindow = $this->matchesWindow($day, $period, $allowedDays, $allowedPeriods);
                $isViolation = match ($mode) {
                    'FORBIDDEN' => $matchesWindow,
                    default => ! $matchesWindow,
                };

                if (! $isViolation) {
                    continue;
                }

                $summary->addViolation(new ConstraintViolationResult(
                    constraintId: $constraint->id,
                    constraintName: $constraint->name,
                    constraintType: $constraint->type,
                    constraintLevel: $constraint->level,
                    rawPenalty: 1.0,
                    effectivePenalty: $this->effectivePenalty($constraint, 1.0),
                    message: sprintf(
                        'Aula %d violou o posicionamento temporal no dia %d, periodo %d.',
                        $lessonId,
                        $day,
                        $period,
                    ),
                    details: [
                        'lesson_id' => $lessonId,
                        'mode' => $mode,
                        'day' => $day,
                        'period' => $period,
                        'allowed_days' => $allowedDays,
                        'allowed_periods' => $allowedPeriods,
                    ],
                ));
            }
        }

        return $summary;
    }

    private function effectivePenalty(CustomConstraintData $constraint, float $rawPenalty): float
    {
        if (strtoupper($constraint->level) === 'SOFT') {
            return $rawPenalty * max(1, $constraint->weight);
        }

        return $rawPenalty;
    }

    private function matchesWindow(int $day, int $period, array $allowedDays, array $allowedPeriods): bool
    {
        $matchesDay = $allowedDays === [] || in_array($day, $allowedDays, true);
        $matchesPeriod = $allowedPeriods === [] || in_array($period, $allowedPeriods, true);

        return $matchesDay && $matchesPeriod;
    }

    private function normalizeLessonIds(array $lessonIds): array
    {
        $normalized = array_values(array_unique(array_map('intval', $lessonIds)));
        sort($normalized);

        return $normalized;
    }

    private function normalizeList(array $values): array
    {
        $normalized = array_values(array_unique(array_map('intval', $values)));
        sort($normalized);

        return $normalized;
    }
}