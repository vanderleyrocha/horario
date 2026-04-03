<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Analysis;

use App\Modules\Horarios\Domain\Constraints\Analysis\DTO\ConstraintFeasibilityReport;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

final class ConstraintFeasibilityAnalyzer
{
    public function analyze(ScheduleData $data): ConstraintFeasibilityReport
    {
        $blockingIssues = [];
        $warnings = [];
        $firstPeriodPressure = [];

        foreach ($data->customConstraints as $constraint) {
            if (! $constraint instanceof CustomConstraintData || ! $constraint->isActive) {
                continue;
            }

            match ($constraint->type) {
                'SYNC_SAME_TIMESLOT' => $this->analyzeSyncConstraint($constraint, $data, $blockingIssues, $warnings),
                'MUTUAL_EXCLUSION' => $this->analyzeMutualExclusionConstraint($constraint, $data, $blockingIssues, $warnings),
                'TIME_PLACEMENT' => $this->analyzeTimePlacementConstraint($constraint, $data, $blockingIssues, $warnings, $firstPeriodPressure),
                default => $blockingIssues[] = $this->issue(
                    $constraint,
                    'UNSUPPORTED_CONSTRAINT_TYPE',
                    "Constraint {$constraint->id} usa o tipo {$constraint->type}, que nao e suportado pelo analyzer de viabilidade.",
                ),
            };
        }

        $this->appendFirstPeriodPressureIssues($firstPeriodPressure, $blockingIssues, $warnings);

        return new ConstraintFeasibilityReport(
            blockingIssues: $blockingIssues,
            warnings: $warnings,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $blockingIssues
     * @param  list<array<string, mixed>>  $warnings
     */
    private function analyzeSyncConstraint(CustomConstraintData $constraint, ScheduleData $data, array &$blockingIssues, array &$warnings): void
    {
        $payload = $constraint->payload;
        $leftLessonIds = $this->extractLessonIds($payload, 'left_group');
        $rightLessonIds = $this->extractLessonIds($payload, 'right_group');
        $allLessonIds = array_values(array_unique(array_merge($leftLessonIds, $rightLessonIds)));

        $this->appendMissingLessonIssues($constraint, $allLessonIds, $data, $blockingIssues);

        $leftLessons = $this->resolveLessons($leftLessonIds, $data);
        $rightLessons = $this->resolveLessons($rightLessonIds, $data);

        if ($leftLessons === [] || $rightLessons === []) {
            return;
        }

        $occurrenceMode = $payload['occurrence_mode'] ?? null;
        $matchMode = $payload['match_mode'] ?? null;

        if (! in_array($occurrenceMode, ['ALL', 'AT_LEAST_ONE'], true)) {
            $blockingIssues[] = $this->issue(
                $constraint,
                'INVALID_OCCURRENCE_MODE',
                "Constraint {$constraint->id} possui occurrence_mode invalido para sincronizacao.",
                ['occurrence_mode' => $occurrenceMode],
            );

            return;
        }

        if (! in_array($matchMode, ['ALL_TO_ALL', 'FIRST_WITH_FIRST'], true)) {
            $blockingIssues[] = $this->issue(
                $constraint,
                'INVALID_MATCH_MODE',
                "Constraint {$constraint->id} possui match_mode invalido para sincronizacao.",
                ['match_mode' => $matchMode],
            );

            return;
        }

        $sharedSlots = $this->commonCandidateSlots(array_merge($leftLessons, $rightLessons), $data);

        if ($sharedSlots === []) {
            $blockingIssues[] = $this->issue(
                $constraint,
                'SYNC_WITHOUT_SHARED_SLOTS',
                "Constraint {$constraint->id} nao possui nenhum slot em comum entre os grupos sincronizados.",
                [
                    'left_group' => $leftLessonIds,
                    'right_group' => $rightLessonIds,
                ],
            );

            return;
        }

        if (count($sharedSlots) === 1) {
            $warnings[] = $this->issue(
                $constraint,
                'LOW_SYNC_FLEXIBILITY',
                "Constraint {$constraint->id} possui apenas um slot compartilhado para sincronizacao.",
                ['shared_slots' => $sharedSlots],
            );
        }

        if ($occurrenceMode !== 'ALL') {
            return;
        }

        $occurrences = array_map(
            static fn (LessonData $lesson): int => $lesson->weeklyOccurrences,
            array_merge($leftLessons, $rightLessons),
        );
        $uniqueOccurrences = array_values(array_unique($occurrences));

        if (count($uniqueOccurrences) !== 1) {
            $blockingIssues[] = $this->issue(
                $constraint,
                'SYNC_OCCURRENCE_MISMATCH',
                "Constraint {$constraint->id} exige sincronizacao completa entre aulas com quantidades semanais diferentes.",
                ['weekly_occurrences' => $occurrences],
            );

            return;
        }

        $requiredOccurrences = $uniqueOccurrences[0];

        if (count($sharedSlots) < $requiredOccurrences) {
            $blockingIssues[] = $this->issue(
                $constraint,
                'SYNC_SHARED_CAPACITY_EXCEEDED',
                "Constraint {$constraint->id} exige {$requiredOccurrences} ocorrencias sincronizadas, mas so existem ".count($sharedSlots).' slots compartilhados viaveis.',
                [
                    'required_occurrences' => $requiredOccurrences,
                    'shared_slots' => $sharedSlots,
                ],
            );

            return;
        }

        if (count($sharedSlots) === $requiredOccurrences) {
            $warnings[] = $this->issue(
                $constraint,
                'SYNC_WITHOUT_SLACK',
                "Constraint {$constraint->id} zera a folga da sincronizacao: toda a capacidade compartilhada precisara ser usada.",
                [
                    'required_occurrences' => $requiredOccurrences,
                    'shared_slots' => $sharedSlots,
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $blockingIssues
     * @param  list<array<string, mixed>>  $warnings
     */
    private function analyzeMutualExclusionConstraint(CustomConstraintData $constraint, ScheduleData $data, array &$blockingIssues, array &$warnings): void
    {
        $payload = $constraint->payload;
        $leftLessonIds = $this->extractLessonIds($payload, 'left_group');
        $rightLessonIds = $this->extractLessonIds($payload, 'right_group');
        $allLessonIds = array_values(array_unique(array_merge($leftLessonIds, $rightLessonIds)));

        $this->appendMissingLessonIssues($constraint, $allLessonIds, $data, $blockingIssues);

        $leftLessons = $this->resolveLessons($leftLessonIds, $data);
        $rightLessons = $this->resolveLessons($rightLessonIds, $data);

        if ($leftLessons === [] || $rightLessons === []) {
            return;
        }

        foreach ($leftLessons as $leftLesson) {
            $leftCandidates = $this->candidateSlotIdsForLesson($leftLesson, $data);

            foreach ($rightLessons as $rightLesson) {
                $rightCandidates = $this->candidateSlotIdsForLesson($rightLesson, $data);
                $sharedCandidates = array_values(array_intersect($leftCandidates, $rightCandidates));

                if (
                    $constraint->level === 'HARD'
                    && $leftLesson->weeklyOccurrences === 1
                    && $rightLesson->weeklyOccurrences === 1
                    && count($leftCandidates) === 1
                    && count($rightCandidates) === 1
                    && $sharedCandidates !== []
                ) {
                    $blockingIssues[] = $this->issue(
                        $constraint,
                        'MUTUAL_EXCLUSION_WITHOUT_ESCAPE',
                        "Constraint {$constraint->id} nao pode ser satisfeita: as aulas {$leftLesson->id} e {$rightLesson->id} so possuem o mesmo slot viavel.",
                        [
                            'left_lesson_id' => $leftLesson->id,
                            'right_lesson_id' => $rightLesson->id,
                            'shared_slots' => $sharedCandidates,
                        ],
                    );

                    return;
                }

                if ($sharedCandidates !== [] && count(array_unique(array_merge($leftCandidates, $rightCandidates))) <= 2) {
                    $warnings[] = $this->issue(
                        $constraint,
                        'MUTUAL_EXCLUSION_TIGHT_WINDOW',
                        "Constraint {$constraint->id} opera com janela muito estreita entre as aulas {$leftLesson->id} e {$rightLesson->id}.",
                        [
                            'left_lesson_id' => $leftLesson->id,
                            'right_lesson_id' => $rightLesson->id,
                            'shared_slots' => $sharedCandidates,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $blockingIssues
     * @param  list<array<string, mixed>>  $warnings
     * @param  array<string, array<string, mixed>>  $firstPeriodPressure
     */
    private function analyzeTimePlacementConstraint(CustomConstraintData $constraint, ScheduleData $data, array &$blockingIssues, array &$warnings, array &$firstPeriodPressure): void
    {
        $payload = $constraint->payload;
        $lessonIds = $this->extractLessonIds($payload, 'target_group');

        $this->appendMissingLessonIssues($constraint, $lessonIds, $data, $blockingIssues);

        $lessons = $this->resolveLessons($lessonIds, $data);

        if ($lessons === []) {
            return;
        }

        $mode = $payload['mode'] ?? null;

        if (! in_array($mode, ['REQUIRED', 'PREFERRED', 'FORBIDDEN'], true)) {
            $blockingIssues[] = $this->issue(
                $constraint,
                'INVALID_TIME_PLACEMENT_MODE',
                "Constraint {$constraint->id} possui mode invalido para posicionamento temporal.",
                ['mode' => $mode],
            );

            return;
        }

        $allowedDays = $this->normalizeIntList($payload['allowed_days'] ?? []);
        $allowedPeriods = $this->normalizeIntList($payload['allowed_periods'] ?? []);
        $validDays = $this->validDays($data);
        $validPeriods = $this->validPeriods($data);
        $invalidDays = array_values(array_diff($allowedDays, $validDays));
        $invalidPeriods = array_values(array_diff($allowedPeriods, $validPeriods));

        if ($invalidDays !== [] || $invalidPeriods !== []) {
            $blockingIssues[] = $this->issue(
                $constraint,
                'TIME_WINDOW_OUT_OF_RANGE',
                "Constraint {$constraint->id} referencia dias ou periodos fora da configuracao do horario.",
                [
                    'invalid_days' => $invalidDays,
                    'invalid_periods' => $invalidPeriods,
                ],
            );

            return;
        }

        foreach ($lessons as $lesson) {
            $candidateSlots = $this->candidateSlotIdsForLesson($lesson, $data);
            $windowSlots = $this->filterSlotsByWindow($candidateSlots, $allowedDays, $allowedPeriods, $data);

            if ($mode === 'REQUIRED' && count($windowSlots) < $lesson->weeklyOccurrences) {
                $blockingIssues[] = $this->issue(
                    $constraint,
                    'TIME_PLACEMENT_REQUIRED_IMPOSSIBLE',
                    "Constraint {$constraint->id} exige {$lesson->weeklyOccurrences} ocorrencias da aula {$lesson->id} dentro da janela, mas apenas ".count($windowSlots).' slots viaveis foram encontrados.',
                    [
                        'lesson_id' => $lesson->id,
                        'candidate_slots' => $candidateSlots,
                        'window_slots' => $windowSlots,
                    ],
                );

                continue;
            }

            if ($mode === 'FORBIDDEN' && (count($candidateSlots) - count($windowSlots)) < $lesson->weeklyOccurrences) {
                $blockingIssues[] = $this->issue(
                    $constraint,
                    'TIME_PLACEMENT_FORBIDDEN_IMPOSSIBLE',
                    "Constraint {$constraint->id} remove slots demais da aula {$lesson->id} e inviabiliza a quantidade semanal exigida.",
                    [
                        'lesson_id' => $lesson->id,
                        'candidate_slots' => $candidateSlots,
                        'forbidden_slots' => $windowSlots,
                    ],
                );

                continue;
            }

            if ($mode === 'PREFERRED' && $windowSlots === []) {
                $warnings[] = $this->issue(
                    $constraint,
                    'TIME_PLACEMENT_PREFERENCE_UNREACHABLE',
                    "Constraint {$constraint->id} nao possui nenhum slot preferencial viavel para a aula {$lesson->id}.",
                    ['lesson_id' => $lesson->id],
                );
            }

            if ($allowedPeriods === [1]) {
                $this->registerFirstPeriodPressure($firstPeriodPressure, $constraint, $lesson, $data, $allowedDays, $mode);

                if ($mode !== 'FORBIDDEN' && count($windowSlots) === $lesson->weeklyOccurrences && $windowSlots !== []) {
                    $warnings[] = $this->issue(
                        $constraint,
                        'FIRST_PERIOD_WITHOUT_SLACK',
                        "Constraint {$constraint->id} deixa a aula {$lesson->id} sem folga fora do primeiro tempo permitido.",
                        [
                            'lesson_id' => $lesson->id,
                            'window_slots' => $windowSlots,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $pressure
     * @param  list<array<string, mixed>>  $blockingIssues
     * @param  list<array<string, mixed>>  $warnings
     */
    private function appendFirstPeriodPressureIssues(array $pressure, array &$blockingIssues, array &$warnings): void
    {
        foreach ($pressure as $entry) {
            $capacity = (int) ($entry['capacity'] ?? 0);

            if ($capacity <= 0) {
                continue;
            }

            $requiredDemand = (int) ($entry['required_demand'] ?? 0);
            $preferredDemand = (int) ($entry['preferred_demand'] ?? 0);
            $entityLabel = (string) ($entry['entity_label'] ?? 'entidade');
            $issueDetails = [
                'entity' => $entry['entity'],
                'entity_id' => $entry['entity_id'],
                'capacity' => $capacity,
                'required_demand' => $requiredDemand,
                'preferred_demand' => $preferredDemand,
                'days' => $entry['days'],
                'constraints' => array_values(array_unique($entry['constraints'] ?? [])),
            ];

            if ($requiredDemand > $capacity) {
                $blockingIssues[] = [
                    'constraint_id' => 0,
                    'constraint_name' => 'First period pressure',
                    'constraint_type' => 'TIME_PLACEMENT',
                    'code' => 'FIRST_PERIOD_REQUIRED_OVERLOAD',
                    'message' => "As constraints exigem {$requiredDemand} ocorrencias no primeiro tempo para {$entityLabel}, mas a capacidade real e {$capacity}.",
                    'details' => $issueDetails,
                ];

                continue;
            }

            $softRatio = ($requiredDemand + $preferredDemand) / $capacity;

            if ($softRatio >= 0.8) {
                $warnings[] = [
                    'constraint_id' => 0,
                    'constraint_name' => 'First period pressure',
                    'constraint_type' => 'TIME_PLACEMENT',
                    'code' => 'FIRST_PERIOD_PRESSURE_HIGH',
                    'message' => "As constraints concentram demanda elevada no primeiro tempo para {$entityLabel} ({$requiredDemand} obrigatorias e {$preferredDemand} preferenciais para {$capacity} slots).",
                    'details' => $issueDetails,
                ];
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $pressure
     */
    private function registerFirstPeriodPressure(array &$pressure, CustomConstraintData $constraint, LessonData $lesson, ScheduleData $data, array $allowedDays, string $mode): void
    {
        foreach ([
            ['prefix' => 'professor', 'entity_id' => $lesson->professorId],
            ['prefix' => 'class', 'entity_id' => $lesson->classId],
        ] as $entity) {
            $key = $entity['prefix'].':'.$entity['entity_id'];
            $capacity = $this->firstPeriodCapacity($entity['prefix'], (int) $entity['entity_id'], $data, $allowedDays);

            if (! isset($pressure[$key])) {
                $pressure[$key] = [
                    'entity' => $entity['prefix'],
                    'entity_id' => $entity['entity_id'],
                    'entity_label' => $entity['prefix'] === 'professor'
                        ? "professor {$entity['entity_id']}"
                        : "turma {$entity['entity_id']}",
                    'capacity' => $capacity,
                    'required_demand' => 0,
                    'preferred_demand' => 0,
                    'constraints' => [],
                    'days' => $allowedDays,
                ];
            }

            $pressure[$key]['capacity'] = min($pressure[$key]['capacity'], $capacity);
            $pressure[$key]['constraints'][] = $constraint->id;

            if ($mode === 'REQUIRED') {
                $pressure[$key]['required_demand'] += $lesson->weeklyOccurrences;
            }

            if ($mode === 'PREFERRED') {
                $pressure[$key]['preferred_demand'] += $lesson->weeklyOccurrences;
            }
        }
    }

    private function firstPeriodCapacity(string $entityType, int $entityId, ScheduleData $data, array $allowedDays): int
    {
        $allowedSlotIds = $entityType === 'professor'
            ? ($data->availableSlotsByProfessor[$entityId] ?? [])
            : ($data->availableSlotsByClass[$entityId] ?? []);

        $allowedLookup = array_fill_keys($allowedSlotIds, true);
        $daysLookup = $allowedDays === [] ? [] : array_fill_keys($allowedDays, true);
        $capacity = 0;

        foreach ($data->timeSlots as $slotId => $slot) {
            if ($slot->lessonNumber !== 1) {
                continue;
            }

            if ($allowedDays !== [] && ! isset($daysLookup[$slot->day])) {
                continue;
            }

            if (! isset($allowedLookup[$slotId])) {
                continue;
            }

            $capacity++;
        }

        return $capacity;
    }

    /**
     * @param  list<int>  $lessonIds
     * @param  list<array<string, mixed>>  $blockingIssues
     */
    private function appendMissingLessonIssues(CustomConstraintData $constraint, array $lessonIds, ScheduleData $data, array &$blockingIssues): void
    {
        $missingLessonIds = array_values(array_filter(
            $lessonIds,
            static fn (int $lessonId): bool => ! isset($data->lessons[$lessonId]),
        ));

        if ($missingLessonIds !== []) {
            $blockingIssues[] = $this->issue(
                $constraint,
                'UNKNOWN_LESSON_REFERENCE',
                "Constraint {$constraint->id} referencia aulas inexistentes no snapshot do solver.",
                ['missing_lesson_ids' => $missingLessonIds],
            );
        }
    }

    /**
     * @return list<int>
     */
    private function extractLessonIds(array $payload, string $groupKey): array
    {
        $group = $payload[$groupKey] ?? null;

        if (! is_array($group)) {
            return [];
        }

        return $this->normalizeIntList($group['lesson_ids'] ?? []);
    }

    /**
     * @param  list<int>  $lessonIds
     * @return list<LessonData>
     */
    private function resolveLessons(array $lessonIds, ScheduleData $data): array
    {
        $lessons = [];

        foreach ($lessonIds as $lessonId) {
            if (isset($data->lessons[$lessonId])) {
                $lessons[] = $data->lessons[$lessonId];
            }
        }

        return $lessons;
    }

    /**
     * @param  list<LessonData>  $lessons
     * @return list<int>
     */
    private function commonCandidateSlots(array $lessons, ScheduleData $data): array
    {
        $common = null;

        foreach ($lessons as $lesson) {
            $candidateSlots = $this->candidateSlotIdsForLesson($lesson, $data);
            $common = $common === null
                ? $candidateSlots
                : array_values(array_intersect($common, $candidateSlots));
        }

        return array_values($common ?? []);
    }

    /**
     * @return list<int>
     */
    private function candidateSlotIdsForLesson(LessonData $lesson, ScheduleData $data): array
    {
        $candidateSlotIds = [];
        $professorAvailable = $data->availableSlotsByProfessor[$lesson->professorId] ?? [];
        $classAvailable = $data->availableSlotsByClass[$lesson->classId] ?? [];
        $availableIntersection = array_fill_keys(array_values(array_intersect($professorAvailable, $classAvailable)), true);

        foreach ($data->timeSlots as $slotId => $slot) {
            if (! $this->slotSupportsDuration($lesson, $slot, $data)) {
                continue;
            }

            if (! isset($availableIntersection[$slotId])) {
                continue;
            }

            $candidateSlotIds[] = $slotId;
        }

        return $candidateSlotIds;
    }

    private function slotSupportsDuration(LessonData $lesson, TimeSlot $slot, ScheduleData $data): bool
    {
        return ($slot->lessonNumber + $lesson->requiredSlots - 1) <= max(array_map(
            static fn (TimeSlot $timeSlot): int => $timeSlot->lessonNumber,
            $data->timeSlots,
        ));
    }

    /**
     * @param  list<int>  $slotIds
     * @param  list<int>  $allowedDays
     * @param  list<int>  $allowedPeriods
     * @return list<int>
     */
    private function filterSlotsByWindow(array $slotIds, array $allowedDays, array $allowedPeriods, ScheduleData $data): array
    {
        $daysLookup = $allowedDays === [] ? [] : array_fill_keys($allowedDays, true);
        $periodsLookup = $allowedPeriods === [] ? [] : array_fill_keys($allowedPeriods, true);
        $filtered = [];

        foreach ($slotIds as $slotId) {
            $slot = $data->timeSlots[$slotId] ?? null;

            if (! $slot instanceof TimeSlot) {
                continue;
            }

            if ($allowedDays !== [] && ! isset($daysLookup[$slot->day])) {
                continue;
            }

            if ($allowedPeriods !== [] && ! isset($periodsLookup[$slot->lessonNumber])) {
                continue;
            }

            $filtered[] = $slotId;
        }

        return $filtered;
    }

    /**
     * @return list<int>
     */
    private function normalizeIntList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): int => (int) $item,
            array_filter($value, static fn (mixed $item): bool => is_numeric($item)),
        ));
    }

    /**
     * @return list<int>
     */
    private function validDays(ScheduleData $data): array
    {
        return array_values(array_unique(array_map(
            static fn (TimeSlot $slot): int => $slot->day,
            $data->timeSlots,
        )));
    }

    /**
     * @return list<int>
     */
    private function validPeriods(ScheduleData $data): array
    {
        return array_values(array_unique(array_map(
            static fn (TimeSlot $slot): int => $slot->lessonNumber,
            $data->timeSlots,
        )));
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function issue(CustomConstraintData $constraint, string $code, string $message, array $details = []): array
    {
        return [
            'constraint_id' => $constraint->id,
            'constraint_name' => $constraint->name,
            'constraint_type' => $constraint->type,
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ];
    }
}
