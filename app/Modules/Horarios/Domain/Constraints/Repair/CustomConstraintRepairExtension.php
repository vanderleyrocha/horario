<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Repair;

use App\Modules\AG\Domain\Repair\Contracts\RepairHeuristicExtension;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class CustomConstraintRepairExtension implements RepairHeuristicExtension
{
    public function augmentRepairTargets(Cromossomo $chromosome, ScheduleData $data): array
    {
        $genes = $chromosome->genes();
        $violationsByGene = $this->buildViolationsByGene($chromosome, $data);
        $targets = [];

        foreach ($violationsByGene as $geneIndex => $payload) {
            if (! isset($genes[$geneIndex])) {
                continue;
            }

            $targets[$geneIndex] = [
                'index' => $geneIndex,
                'count' => max(1, (int) ($payload['count'] ?? 0)),
                'duration' => $genes[$geneIndex]->duracaoTempos(),
                'peers' => array_values(array_unique(array_map('intval', $payload['peers'] ?? []))),
                'violations' => array_values(array_unique(array_map('strval', $payload['violations'] ?? []))),
            ];
        }

        return $targets;
    }

    public function filterCandidateStartSlots(Gene $gene, ScheduleData $data, array $slotIds): array
    {
        $constraints = $this->timePlacementConstraintsForLesson($data, $gene->aulaId());

        if ($constraints === []) {
            return $slotIds;
        }

        $slotsByDayPeriod = $this->slotsByDayPeriod($data);
        $filtered = [];

        foreach ($slotIds as $slotId) {
            $slot = $data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            $isAllowed = true;

            foreach ($constraints as $constraint) {
                if (! $this->candidateRespectsTimePlacementConstraint($slot->day, $slot->lessonNumber, $gene, $constraint, $slotsByDayPeriod)) {
                    $isAllowed = false;
                    break;
                }
            }

            if ($isAllowed) {
                $filtered[] = (int) $slotId;
            }
        }

        if ($filtered === []) {
            return $slotIds;
        }

        return array_values(array_unique($filtered));
    }

    public function candidateRankingPenalty(
        Cromossomo $chromosome,
        Gene $candidate,
        int $sourceGeneIndex,
        array $violationTypes,
        ScheduleData $data,
    ): float {
        return 0.0;
    }

    public function countTargetViolations(
        Cromossomo $chromosome,
        int $geneIndex,
        array $violationTypes,
        ScheduleData $data,
    ): int {
        if (! $this->hasCustomViolationType($violationTypes)) {
            return 0;
        }

        return (int) ($this->buildViolationsByGene($chromosome, $data)[$geneIndex]['count'] ?? 0);
    }

    /**
     * @return array<int, array{count:int,peers:array<int, int>,violations:array<int, string>}>
     */
    private function buildViolationsByGene(Cromossomo $chromosome, ScheduleData $data): array
    {
        $violations = [];
        $constraints = $this->activeSupportedConstraints($data);

        if ($constraints === []) {
            return $violations;
        }

        $slotsByLesson = $this->slotIndexByLesson($chromosome);

        foreach ($constraints as $constraint) {
            $type = strtoupper((string) $constraint->type);

            if ($type === 'TIME_PLACEMENT') {
                $this->collectTimePlacementViolations($constraint->payload, $slotsByLesson, $violations);
                continue;
            }

            if ($type === 'MUTUAL_EXCLUSION') {
                $this->collectMutualExclusionViolations($constraint->payload, $slotsByLesson, $violations);
                continue;
            }

            if ($type === 'SYNC_SAME_TIMESLOT') {
                $this->collectSyncSameTimeslotViolations($constraint->payload, $slotsByLesson, $violations);
            }
        }

        return $violations;
    }

    /**
     * @return array<int, CustomConstraintData>
     */
    private function activeSupportedConstraints(ScheduleData $data): array
    {
        $supported = ['TIME_PLACEMENT', 'MUTUAL_EXCLUSION', 'SYNC_SAME_TIMESLOT'];

        return array_values(array_filter(
            $data->customConstraints,
            static fn (mixed $constraint): bool => $constraint instanceof \App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData
                && $constraint->isActive
                && in_array(strtoupper((string) $constraint->type), $supported, true),
        ));
    }

    /**
     * @return array<int, array{slot_keys:array<string, bool>,slot_to_indexes:array<string, array<int, int>>,gene_indexes:array<int, int>}>
     */
    private function slotIndexByLesson(Cromossomo $chromosome): array
    {
        $map = [];

        foreach ($chromosome->genes() as $geneIndex => $gene) {
            $lessonId = $gene->aulaId();
            $map[$lessonId]['gene_indexes'][] = $geneIndex;

            foreach ($gene->timeslots() as $period) {
                $key = $this->slotKey($gene->diaSemana(), $period);
                $map[$lessonId]['slot_keys'][$key] = true;
                $map[$lessonId]['slot_to_indexes'][$key][] = $geneIndex;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array{slot_keys:array<string, bool>,slot_to_indexes:array<string, array<int, int>>,gene_indexes:array<int, int>}> $slotsByLesson
     * @param array<int, array{count:int,peers:array<int, int>,violations:array<int, string>}> $violations
     */
    private function collectTimePlacementViolations(array $payload, array $slotsByLesson, array &$violations): void
    {
        $lessonIds = $this->normalizeLessonIds($payload['target_group']['lesson_ids'] ?? []);
        $mode = strtoupper((string) ($payload['mode'] ?? 'REQUIRED'));
        $allowedDays = $this->normalizeIntList($payload['allowed_days'] ?? []);
        $allowedPeriods = $this->normalizeIntList($payload['allowed_periods'] ?? []);

        foreach ($lessonIds as $lessonId) {
            $slotKeys = array_keys($slotsByLesson[$lessonId]['slot_keys'] ?? []);

            foreach ($slotKeys as $slotKey) {
                [$day, $period] = $this->decodeSlotKey($slotKey);
                $matchesWindow = $this->matchesWindow($day, $period, $allowedDays, $allowedPeriods);
                $isViolation = $mode === 'FORBIDDEN' ? $matchesWindow : ! $matchesWindow;

                if (! $isViolation) {
                    continue;
                }

                foreach ($slotsByLesson[$lessonId]['slot_to_indexes'][$slotKey] ?? [] as $geneIndex) {
                    $this->registerViolation($violations, $geneIndex, 'custom_time_placement', []);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array{slot_keys:array<string, bool>,slot_to_indexes:array<string, array<int, int>>,gene_indexes:array<int, int>}> $slotsByLesson
     * @param array<int, array{count:int,peers:array<int, int>,violations:array<int, string>}> $violations
     */
    private function collectMutualExclusionViolations(array $payload, array $slotsByLesson, array &$violations): void
    {
        $leftLessonIds = $this->normalizeLessonIds($payload['left_group']['lesson_ids'] ?? []);
        $rightLessonIds = $this->normalizeLessonIds($payload['right_group']['lesson_ids'] ?? null);

        foreach ($this->resolvePairings($leftLessonIds, $rightLessonIds, 'ALL_TO_ALL') as [$leftLessonId, $rightLessonId]) {
            $leftKeys = array_keys($slotsByLesson[$leftLessonId]['slot_keys'] ?? []);
            $rightKeys = array_keys($slotsByLesson[$rightLessonId]['slot_keys'] ?? []);
            $overlaps = array_values(array_intersect($leftKeys, $rightKeys));

            foreach ($overlaps as $slotKey) {
                $leftIndexes = $slotsByLesson[$leftLessonId]['slot_to_indexes'][$slotKey] ?? [];
                $rightIndexes = $slotsByLesson[$rightLessonId]['slot_to_indexes'][$slotKey] ?? [];

                foreach ($leftIndexes as $leftIndex) {
                    $this->registerViolation($violations, $leftIndex, 'custom_mutual_exclusion', $rightIndexes);
                }

                foreach ($rightIndexes as $rightIndex) {
                    $this->registerViolation($violations, $rightIndex, 'custom_mutual_exclusion', $leftIndexes);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array{slot_keys:array<string, bool>,slot_to_indexes:array<string, array<int, int>>,gene_indexes:array<int, int>}> $slotsByLesson
     * @param array<int, array{count:int,peers:array<int, int>,violations:array<int, string>}> $violations
     */
    private function collectSyncSameTimeslotViolations(array $payload, array $slotsByLesson, array &$violations): void
    {
        $leftLessonIds = $this->normalizeLessonIds($payload['left_group']['lesson_ids'] ?? []);
        $rightLessonIds = $this->normalizeLessonIds($payload['right_group']['lesson_ids'] ?? null);
        $occurrenceMode = strtoupper((string) ($payload['occurrence_mode'] ?? 'ALL'));
        $matchMode = strtoupper((string) ($payload['match_mode'] ?? 'ALL_TO_ALL'));

        foreach ($this->resolvePairings($leftLessonIds, $rightLessonIds, $matchMode) as [$leftLessonId, $rightLessonId]) {
            $leftKeys = array_keys($slotsByLesson[$leftLessonId]['slot_keys'] ?? []);
            $rightKeys = array_keys($slotsByLesson[$rightLessonId]['slot_keys'] ?? []);
            $overlapCount = count(array_intersect($leftKeys, $rightKeys));
            $rawPenalty = $occurrenceMode === 'AT_LEAST_ONE'
                ? ($overlapCount > 0 ? 0 : 1)
                : max(0, max(count($leftKeys), count($rightKeys)) - $overlapCount);

            if ($rawPenalty <= 0) {
                continue;
            }

            $leftIndexes = $slotsByLesson[$leftLessonId]['gene_indexes'] ?? [];
            $rightIndexes = $slotsByLesson[$rightLessonId]['gene_indexes'] ?? [];

            foreach ($leftIndexes as $leftIndex) {
                $this->registerViolation($violations, $leftIndex, 'custom_sync_same_timeslot', $rightIndexes, $rawPenalty);
            }

            foreach ($rightIndexes as $rightIndex) {
                $this->registerViolation($violations, $rightIndex, 'custom_sync_same_timeslot', $leftIndexes, $rawPenalty);
            }
        }
    }

    /**
     * @param array<int, array{count:int,peers:array<int, int>,violations:array<int, string>}> $violations
     * @param array<int, int> $peers
     */
    private function registerViolation(array &$violations, int $geneIndex, string $violationType, array $peers, int $increment = 1): void
    {
        if (! isset($violations[$geneIndex])) {
            $violations[$geneIndex] = [
                'count' => 0,
                'peers' => [],
                'violations' => [],
            ];
        }

        $violations[$geneIndex]['count'] += max(1, $increment);
        $violations[$geneIndex]['violations'][] = $violationType;
        $violations[$geneIndex]['peers'] = array_values(array_unique([
            ...$violations[$geneIndex]['peers'],
            ...array_values(array_map('intval', $peers)),
        ]));
    }

    /**
     * @return array<int, CustomConstraintData>
     */
    private function timePlacementConstraintsForLesson(ScheduleData $data, int $lessonId): array
    {
        $constraints = [];

        foreach ($this->activeSupportedConstraints($data) as $constraint) {
            if (strtoupper((string) $constraint->type) !== 'TIME_PLACEMENT') {
                continue;
            }

            $lessonIds = $this->normalizeLessonIds($constraint->payload['target_group']['lesson_ids'] ?? []);

            if (in_array($lessonId, $lessonIds, true)) {
                $constraints[] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * @return array<string, bool>
     */
    private function slotsByDayPeriod(ScheduleData $data): array
    {
        $map = [];

        foreach ($data->timeSlots as $slot) {
            $map[$this->slotKey((int) $slot->day, (int) $slot->lessonNumber)] = true;
        }

        return $map;
    }

    /**
     * @param array<string, bool> $slotsByDayPeriod
     */
    private function candidateRespectsTimePlacementConstraint(
        int $startDay,
        int $startPeriod,
        Gene $gene,
        CustomConstraintData $constraint,
        array $slotsByDayPeriod,
    ): bool {
        $payload = $constraint->payload;
        $mode = strtoupper((string) ($payload['mode'] ?? 'REQUIRED'));
        $allowedDays = $this->normalizeIntList($payload['allowed_days'] ?? []);
        $allowedPeriods = $this->normalizeIntList($payload['allowed_periods'] ?? []);

        for ($offset = 0; $offset < $gene->duracaoTempos(); $offset++) {
            $day = $startDay;
            $period = $startPeriod + $offset;

            if (! isset($slotsByDayPeriod[$this->slotKey($day, $period)])) {
                return false;
            }

            $matchesWindow = $this->matchesWindow($day, $period, $allowedDays, $allowedPeriods);

            if ($mode === 'FORBIDDEN' && $matchesWindow) {
                return false;
            }

            if ($mode !== 'FORBIDDEN' && ! $matchesWindow) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, int> $leftLessonIds
     * @param array<int, int>|null $rightLessonIds
     * @return array<int, array{0:int,1:int}>
     */
    private function resolvePairings(array $leftLessonIds, ?array $rightLessonIds, string $matchMode): array
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

    /**
     * @param mixed $lessonIds
     * @return array<int, int>|null
     */
    private function normalizeLessonIds(mixed $lessonIds): ?array
    {
        if ($lessonIds === null) {
            return null;
        }

        if (! is_array($lessonIds)) {
            return [];
        }

        $normalized = array_values(array_unique(array_map('intval', $lessonIds)));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param mixed $values
     * @return array<int, int>
     */
    private function normalizeIntList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = array_values(array_unique(array_map('intval', $values)));
        sort($normalized);

        return $normalized;
    }

    private function slotKey(int $day, int $period): string
    {
        return $day . ':' . $period;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function decodeSlotKey(string $slotKey): array
    {
        [$day, $period] = array_pad(explode(':', $slotKey, 2), 2, '0');

        return [(int) $day, (int) $period];
    }

    /**
     * @param array<int, int> $allowedDays
     * @param array<int, int> $allowedPeriods
     */
    private function matchesWindow(int $day, int $period, array $allowedDays, array $allowedPeriods): bool
    {
        $matchesDay = $allowedDays === [] || in_array($day, $allowedDays, true);
        $matchesPeriod = $allowedPeriods === [] || in_array($period, $allowedPeriods, true);

        return $matchesDay && $matchesPeriod;
    }

    /**
     * @param string[] $violationTypes
     */
    private function hasCustomViolationType(array $violationTypes): bool
    {
        if (in_array('custom_constraint', $violationTypes, true)) {
            return true;
        }

        foreach ($violationTypes as $violationType) {
            if (str_starts_with((string) $violationType, 'custom_')) {
                return true;
            }
        }

        return false;
    }
}
