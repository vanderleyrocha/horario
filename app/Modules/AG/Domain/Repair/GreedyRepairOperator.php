<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Repair;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class GreedyRepairOperator
{
    private const MAX_PASSES = 4;

    private const LOCAL_REBUILD_MAX_NEIGHBORS = 3;

    private const HEARTBEAT_EVERY_INVALID_GENES = 25;

    /**
     * @var array<string, mixed>
     */
    private array $lastTelemetry = [];

    public function repair(
        Cromossomo $chromosome,
        ScheduleData $data,
        ?callable $fitnessProbe = null,
        ?callable $progressHeartbeat = null,
        array $limits = []
    ): Cromossomo {
        $child = $chromosome->copy();
        $this->lastTelemetry = $this->initializeTelemetry($child, $fitnessProbe);
        $startedAt = microtime(true);
        $maxMillis = isset($limits['max_millis']) ? max(1, (int) $limits['max_millis']) : null;
        $maxPassesWithoutProgress = isset($limits['max_passes_without_progress'])
            ? max(1, (int) $limits['max_passes_without_progress'])
            : null;
        $passesWithoutProgress = 0;

        for ($pass = 1; $pass <= self::MAX_PASSES; $pass++) {
            if ($this->timeBudgetExceeded($startedAt, $maxMillis)) {
                $this->lastTelemetry['aborted'] = true;
                $this->lastTelemetry['abort_reason'] = 'time_budget_exhausted';
                $this->lastTelemetry['time_budget_ms'] = $maxMillis;
                $this->emitAbortHeartbeat($progressHeartbeat, 'time_budget_exhausted', $pass, $maxMillis, $passesWithoutProgress);
                break;
            }

            $invalidIndexes = $this->prioritizeInvalidGeneIndexes($child);

            if ($invalidIndexes === []) {
                break;
            }

            $passTelemetry = $this->startPassTelemetry($pass, $child, $invalidIndexes, $fitnessProbe);
            $this->emitHeartbeat($progressHeartbeat, 'pass_started', $passTelemetry);
            $changed = false;
            $processedInvalidGenes = 0;

            foreach ($invalidIndexes as $index) {
                $currentGenes = $child->genes();

                if (! isset($currentGenes[$index])) {
                    continue;
                }

                if ($this->isValid($child, $currentGenes[$index], $index)) {
                    continue;
                }

                $candidate = $this->attemptRelocation($child, $index, $data);

                if ($candidate !== null) {
                    $child = $candidate;
                    $passTelemetry['relocations']++;
                    $changed = true;

                    continue;
                }

                $candidate = $this->attemptSwap($child, $index, $data);

                if ($candidate !== null) {
                    $child = $candidate;
                    $passTelemetry['swaps']++;
                    $changed = true;

                    continue;
                }

                $candidate = $this->attemptLocalRebuild($child, $index, $data);

                if ($candidate !== null) {
                    $child = $candidate;
                    $passTelemetry['local_rebuilds']++;
                    $changed = true;
                }

                $processedInvalidGenes++;
                $this->emitProgressHeartbeat(
                    progressHeartbeat: $progressHeartbeat,
                    passTelemetry: $passTelemetry,
                    processedInvalidGenes: $processedInvalidGenes,
                    totalInvalidGenes: count($invalidIndexes)
                );
            }

            $this->finishPassTelemetry($passTelemetry, $child, $fitnessProbe);
            $this->emitHeartbeat($progressHeartbeat, 'pass_finished', $passTelemetry);
            $this->lastTelemetry['passes'][] = $passTelemetry;

            $progressDelta = (float) ($passTelemetry['hard_penalty_delta'] ?? 0.0);

            if ($progressDelta <= 0.0) {
                $passesWithoutProgress++;
            } else {
                $passesWithoutProgress = 0;
            }

            if ($maxPassesWithoutProgress !== null && $passesWithoutProgress >= $maxPassesWithoutProgress) {
                $this->lastTelemetry['aborted'] = true;
                $this->lastTelemetry['abort_reason'] = 'no_progress';
                $this->lastTelemetry['passes_without_progress'] = $passesWithoutProgress;
                $this->emitAbortHeartbeat($progressHeartbeat, 'no_progress', $pass, $maxMillis, $passesWithoutProgress);
                break;
            }

            if (! $changed) {
                $this->lastTelemetry['aborted'] = true;
                $this->lastTelemetry['abort_reason'] = 'no_structural_moves';
                $this->emitAbortHeartbeat($progressHeartbeat, 'no_structural_moves', $pass, $maxMillis, $passesWithoutProgress);
                break;
            }
        }

        $this->finalizeTelemetry($child, $fitnessProbe);

        return $child;
    }

    /**
     * @return array<string, mixed>
     */
    public function lastTelemetry(): array
    {
        return $this->lastTelemetry;
    }

    /**
     * @return int[]
     */
    private function prioritizeInvalidGeneIndexes(Cromossomo $chromosome): array
    {
        $conflictMap = $this->buildConflictMap($chromosome);

        uasort($conflictMap, static function (array $left, array $right): int {
            return [$right['count'], $right['duration'], -$right['index']]
                <=>
                [$left['count'], $left['duration'], -$left['index']];
        });

        return array_keys($conflictMap);
    }

    /**
     * @return array<int, array{index:int,count:int,duration:int,peers:int[]}>
     */
    private function buildConflictMap(Cromossomo $chromosome): array
    {
        $conflicts = [];

        $this->accumulateConflicts($chromosome->professorPeriodoIndex(), $chromosome, $conflicts);
        $this->accumulateConflicts($chromosome->turmaPeriodoIndex(), $chromosome, $conflicts);

        foreach ($conflicts as $index => $data) {
            $conflicts[$index]['peers'] = array_values(array_unique($data['peers']));
        }

        return $conflicts;
    }

    /**
     * @param  array<int|string, mixed>  $indexMap
     * @param  array<int, array{index:int,count:int,duration:int,peers:int[]}>  $conflicts
     */
    private function accumulateConflicts(array $indexMap, Cromossomo $chromosome, array &$conflicts): void
    {
        foreach ($indexMap as $days) {
            foreach ($days as $periods) {
                foreach ($periods as $indexes) {
                    if (count($indexes) <= 1) {
                        continue;
                    }

                    foreach ($indexes as $index) {
                        if (! isset($conflicts[$index])) {
                            $gene = $chromosome->genes()[$index];
                            $conflicts[$index] = [
                                'index' => $index,
                                'count' => 0,
                                'duration' => $gene->duracaoTempos(),
                                'peers' => [],
                            ];
                        }

                        foreach ($indexes as $peerIndex) {
                            if ($peerIndex === $index) {
                                continue;
                            }

                            $conflicts[$index]['count']++;
                            $conflicts[$index]['peers'][] = (int) $peerIndex;
                        }
                    }
                }
            }
        }
    }

    private function attemptRelocation(Cromossomo $chromosome, int $sourceGeneIndex, ScheduleData $data): ?Cromossomo
    {
        $gene = $chromosome->genes()[$sourceGeneIndex];
        $candidate = $this->findBestRelocation(
            chromosome: $chromosome,
            gene: $gene,
            data: $data,
            sourceGeneIndex: $sourceGeneIndex,
            ignoredIndexes: [],
            allowSamePosition: false
        );

        if ($candidate === null) {
            return null;
        }

        $copy = $chromosome->copy();
        $copy->replaceGene($sourceGeneIndex, $candidate);

        return $copy;
    }

    private function attemptSwap(Cromossomo $chromosome, int $sourceGeneIndex, ScheduleData $data): ?Cromossomo
    {
        $genes = $chromosome->genes();
        $sourceGene = $genes[$sourceGeneIndex];

        foreach ($genes as $targetIndex => $targetGene) {
            if ($targetIndex === $sourceGeneIndex) {
                continue;
            }

            $swappedSource = $sourceGene->withDiaPeriodo($targetGene->diaSemana(), $targetGene->periodoDia());
            $swappedTarget = $targetGene->withDiaPeriodo($sourceGene->diaSemana(), $sourceGene->periodoDia());

            if (
                ! $this->canStartGeneAt($swappedSource, $data)
                || ! $this->canStartGeneAt($swappedTarget, $data)
            ) {
                continue;
            }

            $candidate = $chromosome->copy();
            $candidate->replaceGene($sourceGeneIndex, $swappedSource);
            $candidate->replaceGene($targetIndex, $swappedTarget);

            if (
                $this->isValid($candidate, $swappedSource, $sourceGeneIndex)
                && $this->isValid($candidate, $swappedTarget, $targetIndex)
            ) {
                return $candidate;
            }
        }

        return null;
    }

    private function attemptLocalRebuild(Cromossomo $chromosome, int $sourceGeneIndex, ScheduleData $data): ?Cromossomo
    {
        $conflictMap = $this->buildConflictMap($chromosome);
        $sourceConflicts = $conflictMap[$sourceGeneIndex] ?? null;

        if ($sourceConflicts === null || $sourceConflicts['peers'] === []) {
            return null;
        }

        $neighborhoodIndexes = array_slice(
            array_values(array_unique([
                $sourceGeneIndex,
                ...$sourceConflicts['peers'],
                ...$this->collectBlockingIndexes($chromosome, $chromosome->genes()[$sourceGeneIndex], $data, $sourceGeneIndex),
            ])),
            0,
            self::LOCAL_REBUILD_MAX_NEIGHBORS
        );

        if (count($neighborhoodIndexes) < 2) {
            return null;
        }

        $working = $chromosome->copy();
        $orderedNeighborhood = $this->orderNeighborhoodForRebuild($working, $data, $neighborhoodIndexes);
        $remainingIndexes = $orderedNeighborhood;

        foreach ($orderedNeighborhood as $geneIndex) {
            $gene = $working->genes()[$geneIndex];
            $ignoredIndexes = array_values(array_diff($remainingIndexes, [$geneIndex]));
            $candidate = $this->findBestRelocation(
                chromosome: $working,
                gene: $gene,
                data: $data,
                sourceGeneIndex: $geneIndex,
                ignoredIndexes: $ignoredIndexes,
                allowSamePosition: $geneIndex !== $sourceGeneIndex
            );

            if ($candidate === null) {
                return null;
            }

            $working->replaceGene($geneIndex, $candidate);
            $remainingIndexes = array_values(array_diff($remainingIndexes, [$geneIndex]));
        }

        foreach ($neighborhoodIndexes as $geneIndex) {
            $gene = $working->genes()[$geneIndex];

            if (! $this->isValid($working, $gene, $geneIndex)) {
                return null;
            }
        }

        return $working->signature() === $chromosome->signature()
            ? null
            : $working;
    }

    /**
     * @return int[]
     */
    private function collectBlockingIndexes(
        Cromossomo $chromosome,
        Gene $gene,
        ScheduleData $data,
        int $sourceGeneIndex
    ): array {
        $blockingIndexes = [];
        $professorIndex = $chromosome->professorPeriodoIndex();
        $turmaIndex = $chromosome->turmaPeriodoIndex();

        foreach ($this->candidateStartSlotsForGene($gene, $data) as $slotId) {
            $slot = $data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            foreach (range($slot->lessonNumber, $slot->lessonNumber + $gene->duracaoTempos() - 1) as $period) {
                foreach ($professorIndex[$gene->professorId()][$slot->day][$period] ?? [] as $occupiedGeneIndex) {
                    if ($occupiedGeneIndex !== $sourceGeneIndex) {
                        $blockingIndexes[] = (int) $occupiedGeneIndex;
                    }
                }

                foreach ($turmaIndex[$gene->turmaId()][$slot->day][$period] ?? [] as $occupiedGeneIndex) {
                    if ($occupiedGeneIndex !== $sourceGeneIndex) {
                        $blockingIndexes[] = (int) $occupiedGeneIndex;
                    }
                }
            }
        }

        return array_values(array_unique($blockingIndexes));
    }

    /**
     * @param  int[]  $ignoredIndexes
     */
    private function findBestRelocation(
        Cromossomo $chromosome,
        Gene $gene,
        ScheduleData $data,
        int $sourceGeneIndex,
        array $ignoredIndexes,
        bool $allowSamePosition
    ): ?Gene {
        $bestCandidate = null;
        $bestConflictScore = PHP_INT_MAX;

        foreach ($this->candidateStartSlotsForGene($gene, $data) as $slotId) {
            $slot = $data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            if (
                ! $allowSamePosition
                && $slot->day === $gene->diaSemana()
                && $slot->lessonNumber === $gene->periodoDia()
            ) {
                continue;
            }

            $candidate = $gene->withDiaPeriodo($slot->day, $slot->lessonNumber);

            if (! $this->isValid($chromosome, $candidate, $sourceGeneIndex, $ignoredIndexes)) {
                continue;
            }

            $conflictScore = $this->sameEntityLoadScore($chromosome, $candidate, $sourceGeneIndex);

            if ($conflictScore < $bestConflictScore) {
                $bestConflictScore = $conflictScore;
                $bestCandidate = $candidate;
            }
        }

        return $bestCandidate;
    }

    /**
     * @return int[]
     */
    private function candidateStartSlotsForGene(Gene $gene, ScheduleData $data): array
    {
        $profSlots = $data->availableSlotsByProfessor[$gene->professorId()] ?? [];
        $classSlots = $data->availableSlotsByClass[$gene->turmaId()] ?? [];
        $possible = array_values(array_intersect($profSlots, $classSlots));

        return array_values(array_filter(
            $possible,
            fn (int $slotId): bool => isset($data->timeSlots[$slotId])
                && $this->slotSupportsDuration(
                    $data->timeSlots[$slotId]->lessonNumber,
                    $gene->duracaoTempos(),
                    $data
                )
        ));
    }

    /**
     * @param  int[]  $neighborhoodIndexes
     * @return int[]
     */
    private function orderNeighborhoodForRebuild(Cromossomo $chromosome, ScheduleData $data, array $neighborhoodIndexes): array
    {
        $candidates = [];

        foreach ($neighborhoodIndexes as $index) {
            $gene = $chromosome->genes()[$index];
            $candidates[$index] = count($this->candidateStartSlotsForGene($gene, $data));
        }

        usort($neighborhoodIndexes, static fn (int $left, int $right): int => [$candidates[$left], $left] <=> [$candidates[$right], $right]);

        return $neighborhoodIndexes;
    }

    private function sameEntityLoadScore(Cromossomo $chromosome, Gene $candidate, int $sourceGeneIndex): int
    {
        $score = 0;
        $professorIndex = $chromosome->professorPeriodoIndex();
        $turmaIndex = $chromosome->turmaPeriodoIndex();

        foreach ($candidate->timeslots() as $period) {
            $score += count(array_filter(
                $professorIndex[$candidate->professorId()][$candidate->diaSemana()][$period] ?? [],
                static fn (int $occupiedIndex): bool => $occupiedIndex !== $sourceGeneIndex
            ));
            $score += count(array_filter(
                $turmaIndex[$candidate->turmaId()][$candidate->diaSemana()][$period] ?? [],
                static fn (int $occupiedIndex): bool => $occupiedIndex !== $sourceGeneIndex
            ));
        }

        return $score;
    }

    /**
     * @param  int[]  $ignoredIndexes
     */
    private function isValid(Cromossomo $cromossomo, Gene $gene, ?int $sourceGeneIndex = null, array $ignoredIndexes = []): bool
    {
        $professorPeriodoIndex = $cromossomo->professorPeriodoIndex();
        $turmaPeriodoIndex = $cromossomo->turmaPeriodoIndex();

        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $periodoInicial = $gene->periodoDia();

        for ($offset = 0; $offset < $gene->duracaoTempos(); $offset++) {
            $periodo = $periodoInicial + $offset;

            if (
                $this->hasExternalOccupation($professorPeriodoIndex, $prof, $dia, $periodo, $sourceGeneIndex, $ignoredIndexes) ||
                $this->hasExternalOccupation($turmaPeriodoIndex, $turma, $dia, $periodo, $sourceGeneIndex, $ignoredIndexes)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  int[]  $ignoredIndexes
     */
    private function hasExternalOccupation(
        array $indexMap,
        int $entityId,
        int $dia,
        int $periodo,
        ?int $sourceGeneIndex,
        array $ignoredIndexes
    ): bool {
        if (! isset($indexMap[$entityId][$dia][$periodo])) {
            return false;
        }

        foreach ($indexMap[$entityId][$dia][$periodo] as $occupiedGeneIndex) {
            if ($sourceGeneIndex !== null && (int) $occupiedGeneIndex === $sourceGeneIndex) {
                continue;
            }

            if (in_array((int) $occupiedGeneIndex, $ignoredIndexes, true)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function canStartGeneAt(Gene $gene, ScheduleData $data): bool
    {
        $slotId = $this->findSlotId($gene->diaSemana(), $gene->periodoDia(), $data);

        if ($slotId === null) {
            return false;
        }

        $profSlots = $data->availableSlotsByProfessor[$gene->professorId()] ?? [];
        $classSlots = $data->availableSlotsByClass[$gene->turmaId()] ?? [];

        return in_array($slotId, $profSlots, true)
            && in_array($slotId, $classSlots, true)
            && $this->slotSupportsDuration($gene->periodoDia(), $gene->duracaoTempos(), $data);
    }

    private function findSlotId(int $day, int $period, ScheduleData $data): ?int
    {
        foreach ($data->timeSlots as $slotId => $slot) {
            if ($slot->day === $day && $slot->lessonNumber === $period) {
                return (int) $slotId;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function initializeTelemetry(Cromossomo $chromosome, ?callable $fitnessProbe): array
    {
        $fitness = $this->probeFitness($chromosome, $fitnessProbe);
        $invalidCount = count($this->prioritizeInvalidGeneIndexes($chromosome));

        return [
            'passes' => [],
            'invalid_genes_before' => $invalidCount,
            'invalid_genes_after' => $invalidCount,
            'hard_penalty_before' => $fitness['hard_penalty'] ?? null,
            'hard_penalty_after' => $fitness['hard_penalty'] ?? null,
            'soft_penalty_before' => $fitness['soft_penalty'] ?? null,
            'soft_penalty_after' => $fitness['soft_penalty'] ?? null,
            'score_before' => $fitness['score'] ?? null,
            'score_after' => $fitness['score'] ?? null,
            'relocations' => 0,
            'swaps' => 0,
            'local_rebuilds' => 0,
        ];
    }

    /**
     * @param  int[]  $invalidIndexes
     * @return array<string, mixed>
     */
    private function startPassTelemetry(int $pass, Cromossomo $chromosome, array $invalidIndexes, ?callable $fitnessProbe): array
    {
        $fitness = $this->probeFitness($chromosome, $fitnessProbe);

        return [
            'pass' => $pass,
            'invalid_genes_before' => count($invalidIndexes),
            'invalid_genes_after' => count($invalidIndexes),
            'hard_penalty_before' => $fitness['hard_penalty'] ?? null,
            'hard_penalty_after' => $fitness['hard_penalty'] ?? null,
            'hard_penalty_delta' => 0.0,
            'relocations' => 0,
            'swaps' => 0,
            'local_rebuilds' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $passTelemetry
     */
    private function finishPassTelemetry(array &$passTelemetry, Cromossomo $chromosome, ?callable $fitnessProbe): void
    {
        $fitness = $this->probeFitness($chromosome, $fitnessProbe);
        $passTelemetry['invalid_genes_after'] = count($this->prioritizeInvalidGeneIndexes($chromosome));
        $passTelemetry['hard_penalty_after'] = $fitness['hard_penalty'] ?? null;

        if (
            isset($passTelemetry['hard_penalty_before'], $passTelemetry['hard_penalty_after'])
            && $passTelemetry['hard_penalty_before'] !== null
            && $passTelemetry['hard_penalty_after'] !== null
        ) {
            $passTelemetry['hard_penalty_delta'] = round(
                (float) $passTelemetry['hard_penalty_before'] - (float) $passTelemetry['hard_penalty_after'],
                4
            );
        }
    }

    private function finalizeTelemetry(Cromossomo $chromosome, ?callable $fitnessProbe): void
    {
        $fitness = $this->probeFitness($chromosome, $fitnessProbe);

        $this->lastTelemetry['invalid_genes_after'] = count($this->prioritizeInvalidGeneIndexes($chromosome));
        $this->lastTelemetry['hard_penalty_after'] = $fitness['hard_penalty'] ?? null;
        $this->lastTelemetry['soft_penalty_after'] = $fitness['soft_penalty'] ?? null;
        $this->lastTelemetry['score_after'] = $fitness['score'] ?? null;
        $this->lastTelemetry['relocations'] = array_sum(array_column($this->lastTelemetry['passes'], 'relocations'));
        $this->lastTelemetry['swaps'] = array_sum(array_column($this->lastTelemetry['passes'], 'swaps'));
        $this->lastTelemetry['local_rebuilds'] = array_sum(array_column($this->lastTelemetry['passes'], 'local_rebuilds'));
        $this->lastTelemetry['aborted'] = (bool) ($this->lastTelemetry['aborted'] ?? false);
        $this->lastTelemetry['abort_reason'] = $this->lastTelemetry['abort_reason'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $passTelemetry
     */
    private function emitHeartbeat(?callable $progressHeartbeat, string $event, array $passTelemetry): void
    {
        if ($progressHeartbeat === null) {
            return;
        }

        $progressHeartbeat([
            'event' => $event,
            'pass' => $passTelemetry['pass'] ?? null,
            'invalid_genes_before' => $passTelemetry['invalid_genes_before'] ?? null,
            'invalid_genes_after' => $passTelemetry['invalid_genes_after'] ?? null,
            'hard_penalty_before' => $passTelemetry['hard_penalty_before'] ?? null,
            'hard_penalty_after' => $passTelemetry['hard_penalty_after'] ?? null,
            'hard_penalty_delta' => $passTelemetry['hard_penalty_delta'] ?? null,
            'relocations' => $passTelemetry['relocations'] ?? 0,
            'swaps' => $passTelemetry['swaps'] ?? 0,
            'local_rebuilds' => $passTelemetry['local_rebuilds'] ?? 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $passTelemetry
     */
    private function emitProgressHeartbeat(
        ?callable $progressHeartbeat,
        array $passTelemetry,
        int $processedInvalidGenes,
        int $totalInvalidGenes
    ): void {
        if (
            $progressHeartbeat === null
            || $processedInvalidGenes <= 0
            || $processedInvalidGenes % self::HEARTBEAT_EVERY_INVALID_GENES !== 0
        ) {
            return;
        }

        $progressHeartbeat([
            'event' => 'pass_progress',
            'pass' => $passTelemetry['pass'] ?? null,
            'processed_invalid_genes' => $processedInvalidGenes,
            'total_invalid_genes' => $totalInvalidGenes,
            'relocations' => $passTelemetry['relocations'] ?? 0,
            'swaps' => $passTelemetry['swaps'] ?? 0,
            'local_rebuilds' => $passTelemetry['local_rebuilds'] ?? 0,
        ]);
    }

    private function emitAbortHeartbeat(
        ?callable $progressHeartbeat,
        string $reason,
        int $pass,
        ?int $timeBudgetMs,
        int $passesWithoutProgress
    ): void {
        if ($progressHeartbeat === null) {
            return;
        }

        $progressHeartbeat([
            'event' => 'repair_aborted',
            'pass' => $pass,
            'abort_reason' => $reason,
            'time_budget_ms' => $timeBudgetMs,
            'passes_without_progress' => $passesWithoutProgress,
        ]);
    }

    private function timeBudgetExceeded(float $startedAt, ?int $maxMillis): bool
    {
        if ($maxMillis === null) {
            return false;
        }

        return ((microtime(true) - $startedAt) * 1000) >= $maxMillis;
    }

    /**
     * @return array<string, float|null>
     */
    private function probeFitness(Cromossomo $chromosome, ?callable $fitnessProbe): array
    {
        if ($fitnessProbe === null) {
            return [];
        }

        $fitness = $fitnessProbe($chromosome);

        if (! is_array($fitness)) {
            return [];
        }

        return [
            'hard_penalty' => isset($fitness['hard_penalty']) ? (float) $fitness['hard_penalty'] : null,
            'soft_penalty' => isset($fitness['soft_penalty']) ? (float) $fitness['soft_penalty'] : null,
            'score' => isset($fitness['score']) ? (float) $fitness['score'] : null,
        ];
    }

    private function slotSupportsDuration(int $initialPeriod, int $duration, ScheduleData $data): bool
    {
        return ($initialPeriod + $duration - 1) <= $this->maxLessonNumber($data);
    }

    private function maxLessonNumber(ScheduleData $data): int
    {
        if ($data->timeSlots === []) {
            return 0;
        }

        return max(array_map(
            static fn ($slot) => $slot->lessonNumber,
            $data->timeSlots
        ));
    }
}
