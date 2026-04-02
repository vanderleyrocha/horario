<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Evaluators;

use App\Modules\Horarios\Domain\Constraints\Results\ConstraintEvaluationSummary;
use App\Modules\Horarios\Domain\Constraints\Results\ConstraintViolationResult;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;

final class SyncSameTimeslotConstraintEvaluator implements ConstraintEvaluatorInterface
{
    public function supports(CustomConstraintData $constraint): bool
    {
        return $constraint->type === 'SYNC_SAME_TIMESLOT';
    }

    public function evaluate(CustomConstraintData $constraint, EvaluationContext $context): ConstraintEvaluationSummary
    {
        $summary = ConstraintEvaluationSummary::empty();
        $payload = $constraint->payload;
        $leftLessonIds = $this->normalizeLessonIds($payload['left_group']['lesson_ids'] ?? []);
        $rightLessonIds = $this->normalizeLessonIds($payload['right_group']['lesson_ids'] ?? null);
        $occurrenceMode = strtoupper((string) ($payload['occurrence_mode'] ?? 'ALL'));
        $matchMode = strtoupper((string) ($payload['match_mode'] ?? 'ALL_TO_ALL'));
        $slotMap = $this->slotMapByLesson($context);

        foreach ($this->resolvePairs($leftLessonIds, $rightLessonIds, $matchMode) as [$leftLessonId, $rightLessonId]) {
            $leftSlots = $slotMap[$leftLessonId] ?? [];
            $rightSlots = $slotMap[$rightLessonId] ?? [];
            $overlapKeys = array_intersect(array_keys($leftSlots), array_keys($rightSlots));
            $overlapCount = count($overlapKeys);

            $rawPenalty = 0.0;

            if ($occurrenceMode === 'AT_LEAST_ONE') {
                $rawPenalty = $overlapCount > 0 ? 0.0 : 1.0;
            } else {
                $rawPenalty = max(0.0, (float) (max(count($leftSlots), count($rightSlots)) - $overlapCount));
            }

            if ($rawPenalty <= 0.0) {
                continue;
            }

            $summary->addViolation(new ConstraintViolationResult(
                constraintId: $constraint->id,
                constraintName: $constraint->name,
                constraintType: $constraint->type,
                constraintLevel: $constraint->level,
                rawPenalty: $rawPenalty,
                effectivePenalty: $this->effectivePenalty($constraint, $rawPenalty),
                message: sprintf(
                    'Aulas %d e %d nao atenderam ao sincronismo esperado.',
                    $leftLessonId,
                    $rightLessonId,
                ),
                details: [
                    'left_lesson_id' => $leftLessonId,
                    'right_lesson_id' => $rightLessonId,
                    'occurrence_mode' => $occurrenceMode,
                    'match_mode' => $matchMode,
                    'left_slots' => array_values($leftSlots),
                    'right_slots' => array_values($rightSlots),
                    'overlap_slots' => array_values(array_map(
                        static fn (string $key): array => $leftSlots[$key],
                        $overlapKeys,
                    )),
                ],
            ));
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

    private function resolvePairs(array $leftLessonIds, ?array $rightLessonIds, string $matchMode): array
    {
        if ($rightLessonIds === null) {
            $pairs = [];
            $count = count($leftLessonIds);

            for ($leftIndex = 0; $leftIndex < $count; $leftIndex++) {
                for ($rightIndex = $leftIndex + 1; $rightIndex < $count; $rightIndex++) {
                    $pairs[] = [$leftLessonIds[$leftIndex], $leftLessonIds[$rightIndex]];
                }
            }

            return $pairs;
        }

        if ($matchMode === 'FIRST_WITH_FIRST') {
            $pairs = [];
            $pairCount = min(count($leftLessonIds), count($rightLessonIds));

            for ($index = 0; $index < $pairCount; $index++) {
                $pairs[] = [$leftLessonIds[$index], $rightLessonIds[$index]];
            }

            return $pairs;
        }

        $pairs = [];

        foreach ($leftLessonIds as $leftLessonId) {
            foreach ($rightLessonIds as $rightLessonId) {
                $pairs[] = [$leftLessonId, $rightLessonId];
            }
        }

        return $pairs;
    }

    private function slotMapByLesson(EvaluationContext $context): array
    {
        $map = [];

        foreach ($context->alocacoesPorAula() as $lessonId => $slots) {
            foreach ($slots as $slot) {
                $key = $this->slotKey((int) $slot['dia'], (int) $slot['periodo']);
                $map[$lessonId][$key] = [
                    'dia' => (int) $slot['dia'],
                    'periodo' => (int) $slot['periodo'],
                ];
            }
        }

        return $map;
    }

    private function slotKey(int $day, int $period): string
    {
        return $day . ':' . $period;
    }

    private function normalizeLessonIds(array|null $lessonIds): ?array
    {
        if ($lessonIds === null) {
            return null;
        }

        $normalized = array_values(array_unique(array_map('intval', $lessonIds)));
        sort($normalized);

        return $normalized;
    }
}