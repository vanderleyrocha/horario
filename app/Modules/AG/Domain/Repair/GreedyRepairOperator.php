<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Repair;

use App\Modules\AG\Domain\Repair\Contracts\RepairHeuristicExtension;
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

    private ?ScheduleData $activeData = null;

    /**
     * @var callable|null
     */
    private $activeFitnessProbe = null;

    private ?float $activeDeadlineAt = null;

    private bool $activeTimeBudgetExceeded = false;

    /**
     * Cache local de fitness por assinatura para evitar reavaliações repetidas
     * durante o ranking de candidatos no mesmo ciclo de repair.
     *
     * @var array<string, array<string, float|null>>
     */
    private array $repairRankingFitnessCache = [];

    /**
     * @var array<int, RepairHeuristicExtension>
     */
    private readonly array $extensions;

    /**
     * @param array<int, RepairHeuristicExtension> $extensions
     */
    public function __construct(array $extensions = [])
    {
        $this->extensions = $extensions;
    }

    public function repair(
        Cromossomo $chromosome,
        ScheduleData $data,
        ?callable $fitnessProbe = null,
        ?callable $progressHeartbeat = null,
        array $limits = [],
    ): Cromossomo {
        $this->activeData = $data;
        $this->activeFitnessProbe = $fitnessProbe;
        $this->activeTimeBudgetExceeded = false;
        $this->repairRankingFitnessCache = [];

        try {
            $child = $chromosome->copy();
            $this->lastTelemetry = $this->initializeTelemetry($child, $fitnessProbe);
            $startedAt = microtime(true);
            $maxMillis = isset($limits['max_millis']) ? max(1, (int) $limits['max_millis']) : null;
            $this->activeDeadlineAt = $maxMillis !== null
                ? $startedAt + ($maxMillis / 1000)
                : null;
            $maxPassesWithoutProgress = isset($limits['max_passes_without_progress'])
                ? max(1, (int) $limits['max_passes_without_progress'])
                : null;
            $passesWithoutProgress = 0;

            for ($pass = 1; $pass <= self::MAX_PASSES; $pass++) {
                if ($this->deadlineExceeded()) {
                    $this->abortForTimeBudget($progressHeartbeat, $pass, $maxMillis, $passesWithoutProgress);
                    break;
                }

                $repairTargets = $this->prioritizeRepairTargets($child);

                if ($repairTargets === []) {
                    break;
                }

                $passTelemetry = $this->startPassTelemetry($pass, $child, $repairTargets, $fitnessProbe);
                $this->emitHeartbeat($progressHeartbeat, 'pass_started', $passTelemetry);
                $changed = false;
                $processedInvalidGenes = 0;

                foreach ($repairTargets as $target) {
                    if ($this->deadlineExceeded()) {
                        $this->abortForTimeBudget($progressHeartbeat, $pass, $maxMillis, $passesWithoutProgress);
                        break 2;
                    }

                    $index = $target['index'];
                    $currentGenes = $child->genes();

                    if (! isset($currentGenes[$index])) {
                        continue;
                    }

                    if ($this->isRepairTargetResolved($child, $index, $target['violations'] ?? [])) {
                        continue;
                    }

                    $candidate = $this->attemptRelocation($child, $index, $data, $target['violations'] ?? []);

                    if ($this->deadlineExceeded()) {
                        $this->abortForTimeBudget($progressHeartbeat, $pass, $maxMillis, $passesWithoutProgress);
                        break 2;
                    }

                    if ($candidate !== null) {
                        $child = $candidate;
                        $passTelemetry['relocations']++;
                        $changed = true;

                        continue;
                    }

                    $candidate = $this->attemptSwap($child, $index, $data, $target['violations'] ?? []);

                    if ($this->deadlineExceeded()) {
                        $this->abortForTimeBudget($progressHeartbeat, $pass, $maxMillis, $passesWithoutProgress);
                        break 2;
                    }

                    if ($candidate !== null) {
                        $child = $candidate;
                        $passTelemetry['swaps']++;
                        $changed = true;

                        continue;
                    }

                    $candidate = $this->attemptLocalRebuild($child, $index, $data, $target['violations'] ?? []);

                    if ($this->deadlineExceeded()) {
                        $this->abortForTimeBudget($progressHeartbeat, $pass, $maxMillis, $passesWithoutProgress);
                        break 2;
                    }

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
                        totalInvalidGenes: count($repairTargets),
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
        } finally {
            $this->activeData = null;
            $this->activeFitnessProbe = null;
            $this->activeDeadlineAt = null;
            $this->activeTimeBudgetExceeded = false;
            $this->repairRankingFitnessCache = [];
        }
    }

    /**
     * @return array{
     *     invalid_genes: int,
     *     repair_target_summary: array<string, int>,
     *     structural_violations: int,
     *     custom_violations: int,
     *     custom_only: bool
     * }
     */
    public function inspectTargets(Cromossomo $chromosome, ScheduleData $data): array
    {
        $previousData = $this->activeData;
        $previousFitnessProbe = $this->activeFitnessProbe;
        $previousDeadlineAt = $this->activeDeadlineAt;
        $previousBudgetExceeded = $this->activeTimeBudgetExceeded;

        try {
            $this->activeData = $data;
            $this->activeFitnessProbe = null;
            $this->activeDeadlineAt = null;
            $this->activeTimeBudgetExceeded = false;

            $targets = $this->prioritizeRepairTargets($chromosome);
            $summary = $this->summarizeRepairTargets($targets);
            $structuralViolations = (int) ($summary['overlap'] ?? 0) + (int) ($summary['mandatory_block'] ?? 0);
            $customViolations = 0;

            foreach ($summary as $type => $count) {
                if (str_starts_with((string) $type, 'custom_')) {
                    $customViolations += (int) $count;
                }
            }

            return [
                'invalid_genes' => count($targets),
                'repair_target_summary' => $summary,
                'structural_violations' => $structuralViolations,
                'custom_violations' => $customViolations,
                'custom_only' => count($targets) > 0 && $structuralViolations === 0 && $customViolations > 0,
            ];
        } finally {
            $this->activeData = $previousData;
            $this->activeFitnessProbe = $previousFitnessProbe;
            $this->activeDeadlineAt = $previousDeadlineAt;
            $this->activeTimeBudgetExceeded = $previousBudgetExceeded;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function lastTelemetry(): array
    {
        return $this->lastTelemetry;
    }

    /**
     * @return array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}>
     */
    private function prioritizeRepairTargets(Cromossomo $chromosome): array
    {
        $targetMap = $this->buildConflictMap($chromosome);
        $targetMap = $this->mergeRepairTargetMaps($targetMap, $this->buildMandatoryBlockViolationMap($chromosome));

        if ($this->activeData !== null) {
            foreach ($this->extensions as $extension) {
                $targetMap = $this->mergeRepairTargetMaps(
                    $targetMap,
                    $extension->augmentRepairTargets($chromosome, $this->activeData),
                );
            }
        }

        uasort($targetMap, static function (array $left, array $right): int {
            return [
                -count($left['violations'] ?? []),
                $right['count'],
                $right['duration'],
                -$right['index'],
            ]
                <=>
                [
                    -count($right['violations'] ?? []),
                    $left['count'],
                    $left['duration'],
                    -$left['index'],
                ];
        });

        return array_values($targetMap);
    }

    /**
     * @return array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}>
     */
    private function buildConflictMap(Cromossomo $chromosome): array
    {
        $conflicts = [];

        $this->accumulateConflicts($chromosome->professorPeriodoIndex(), $chromosome, $conflicts);
        $this->accumulateConflicts($chromosome->turmaPeriodoIndex(), $chromosome, $conflicts);

        foreach ($conflicts as $index => $data) {
            $conflicts[$index]['peers'] = array_values(array_unique($data['peers']));
            $conflicts[$index]['violations'] = ['overlap'];
        }

        return $conflicts;
    }

    /**
     * @return array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}>
     */
    private function buildMandatoryBlockViolationMap(Cromossomo $chromosome): array
    {
        if ($this->activeData === null) {
            return [];
        }

        $grouped = [];

        foreach ($chromosome->genes() as $index => $gene) {
            $lesson = $this->activeData->lessons[$gene->aulaId()] ?? null;

            if ($lesson === null || $lesson->requiresConsecutive !== true) {
                continue;
            }

            $grouped[$gene->aulaId()][$gene->diaSemana()][] = [
                'index' => $index,
                'start' => $gene->periodoDia(),
                'end' => $gene->endPeriodo(),
                'duration' => $gene->duracaoTempos(),
            ];
        }

        $violations = [];

        foreach ($grouped as $days) {
            foreach ($days as $entries) {
                if (count($entries) < 2) {
                    continue;
                }

                usort($entries, static fn (array $left, array $right): int => [$left['start'], $left['index']] <=> [$right['start'], $right['index']]);

                for ($position = 1; $position < count($entries); $position++) {
                    $previous = $entries[$position - 1];
                    $current = $entries[$position];

                    if ($current['start'] === ($previous['end'] + 1)) {
                        continue;
                    }

                    foreach ([$previous, $current] as $entry) {
                        $index = $entry['index'];

                        if (! isset($violations[$index])) {
                            $violations[$index] = [
                                'index' => $index,
                                'count' => 0,
                                'duration' => $entry['duration'],
                                'peers' => [],
                                'violations' => ['mandatory_block'],
                            ];
                        }

                        $violations[$index]['count']++;
                    }

                    $violations[$previous['index']]['peers'][] = $current['index'];
                    $violations[$current['index']]['peers'][] = $previous['index'];
                }
            }
        }

        foreach ($violations as $index => $data) {
            $violations[$index]['peers'] = array_values(array_unique($data['peers']));
        }

        return $violations;
    }

    /**
     * @param array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}> $baseMap
     * @param array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}> $extraMap
     * @return array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}>
     */
    private function mergeRepairTargetMaps(array $baseMap, array $extraMap): array
    {
        foreach ($extraMap as $index => $target) {
            if (! isset($baseMap[$index])) {
                $baseMap[$index] = $target;

                continue;
            }

            $baseMap[$index]['count'] += $target['count'];
            $baseMap[$index]['peers'] = array_values(array_unique([
                ...$baseMap[$index]['peers'],
                ...$target['peers'],
            ]));
            $baseMap[$index]['violations'] = array_values(array_unique([
                ...($baseMap[$index]['violations'] ?? []),
                ...($target['violations'] ?? []),
            ]));
        }

        return $baseMap;
    }

    /**
     * @param array<int|string, mixed> $indexMap
     * @param array<int, array{index:int,count:int,duration:int,peers:int[]}> $conflicts
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

    private function attemptRelocation(Cromossomo $chromosome, int $sourceGeneIndex, ScheduleData $data, array $violationTypes = []): ?Cromossomo
    {
        $gene = $chromosome->genes()[$sourceGeneIndex];
        $candidate = $this->findBestRelocation(
            chromosome: $chromosome,
            gene: $gene,
            data: $data,
            sourceGeneIndex: $sourceGeneIndex,
            ignoredIndexes: [],
            allowSamePosition: false,
            violationTypes: $violationTypes,
        );

        if ($candidate === null) {
            return null;
        }

        $copy = $chromosome->copy();
        $copy->replaceGene($sourceGeneIndex, $candidate);

        return $copy;
    }

    private function attemptSwap(Cromossomo $chromosome, int $sourceGeneIndex, ScheduleData $data, array $violationTypes = []): ?Cromossomo
    {
        $genes = $chromosome->genes();
        $sourceGene = $genes[$sourceGeneIndex];
        $baselineRanking = $this->buildRepairRanking($chromosome, $sourceGene, $sourceGeneIndex, $violationTypes);
        $bestCandidate = null;
        $bestRanking = null;

        foreach ($genes as $targetIndex => $targetGene) {
            if ($this->deadlineExceeded()) {
                return null;
            }

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
                $ranking = $this->buildRepairRanking($candidate, $candidate->genes()[$sourceGeneIndex], $sourceGeneIndex, $violationTypes);

                if ($bestRanking === null || $ranking < $bestRanking) {
                    $bestRanking = $ranking;
                    $bestCandidate = $candidate;
                }
            }
        }

        if ($bestCandidate === null || $bestRanking === null) {
            return null;
        }

        return $bestRanking < $baselineRanking ? $bestCandidate : null;
    }

    private function attemptLocalRebuild(Cromossomo $chromosome, int $sourceGeneIndex, ScheduleData $data, array $violationTypes = []): ?Cromossomo
    {
        $conflictMap = $this->buildConflictMap($chromosome);
        $sourceConflicts = $conflictMap[$sourceGeneIndex] ?? null;

        if ($sourceConflicts === null && in_array('mandatory_block', $violationTypes, true)) {
            $sourceConflicts = [
                'index' => $sourceGeneIndex,
                'peers' => $this->buildMandatoryBlockViolationMap($chromosome)[$sourceGeneIndex]['peers'] ?? [],
            ];
        }

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
            self::LOCAL_REBUILD_MAX_NEIGHBORS,
        );

        if (count($neighborhoodIndexes) < 2) {
            return null;
        }

        $working = $chromosome->copy();
        $orderedNeighborhood = $this->orderNeighborhoodForRebuild($working, $data, $neighborhoodIndexes);
        $remainingIndexes = $orderedNeighborhood;

        foreach ($orderedNeighborhood as $geneIndex) {
            if ($this->deadlineExceeded()) {
                return null;
            }

            $gene = $working->genes()[$geneIndex];
            $ignoredIndexes = array_values(array_diff($remainingIndexes, [$geneIndex]));
            $candidate = $this->findBestRelocation(
                chromosome: $working,
                gene: $gene,
                data: $data,
                sourceGeneIndex: $geneIndex,
                ignoredIndexes: $ignoredIndexes,
                allowSamePosition: $geneIndex !== $sourceGeneIndex,
                violationTypes: $geneIndex === $sourceGeneIndex ? $violationTypes : ['overlap'],
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

        if ($working->signature() === $chromosome->signature()) {
            return null;
        }

        $baselineRanking = $this->buildRepairRanking($chromosome, $chromosome->genes()[$sourceGeneIndex], $sourceGeneIndex, $violationTypes);
        $candidateRanking = $this->buildRepairRanking($working, $working->genes()[$sourceGeneIndex], $sourceGeneIndex, $violationTypes);

        return $candidateRanking < $baselineRanking ? $working : null;
    }

    /**
     * @return int[]
     */
    private function collectBlockingIndexes(
        Cromossomo $chromosome,
        Gene $gene,
        ScheduleData $data,
        int $sourceGeneIndex,
    ): array {
        $blockingIndexes = [];
        $professorIndex = $chromosome->professorPeriodoIndex();
        $turmaIndex = $chromosome->turmaPeriodoIndex();

        foreach ($this->candidateStartSlotsForGene($gene, $data) as $slotId) {
            if ($this->deadlineExceeded()) {
                break;
            }

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
     * @param int[] $ignoredIndexes
     */
    private function findBestRelocation(
        Cromossomo $chromosome,
        Gene $gene,
        ScheduleData $data,
        int $sourceGeneIndex,
        array $ignoredIndexes,
        bool $allowSamePosition,
        array $violationTypes = [],
    ): ?Gene {
        $bestCandidate = null;
        $bestRanking = null;
        $baselineRanking = $this->buildRepairRanking($chromosome, $gene, $sourceGeneIndex, $violationTypes);

        foreach ($this->candidateStartSlotsForGene($gene, $data) as $slotId) {
            if ($this->deadlineExceeded()) {
                break;
            }

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

            $candidateChromosome = $chromosome->copy();
            $candidateChromosome->replaceGene($sourceGeneIndex, $candidate);
            $ranking = $this->buildRepairRanking($candidateChromosome, $candidate, $sourceGeneIndex, $violationTypes);

            if ($bestRanking === null || $ranking < $bestRanking) {
                $bestRanking = $ranking;
                $bestCandidate = $candidate;
            }
        }

        if ($bestCandidate === null || $bestRanking === null) {
            return null;
        }

        return $bestRanking < $baselineRanking ? $bestCandidate : null;
    }

    /**
     * @return int[]
     */
    private function candidateStartSlotsForGene(Gene $gene, ScheduleData $data): array
    {
        $profSlots = $data->availableSlotsByProfessor[$gene->professorId()] ?? [];
        $classSlots = $data->availableSlotsByClass[$gene->turmaId()] ?? [];
        $possible = array_values(array_intersect($profSlots, $classSlots));

        $possible = array_values(array_filter(
            $possible,
            fn (int $slotId): bool => isset($data->timeSlots[$slotId])
                && $this->slotSupportsDuration(
                    $data->timeSlots[$slotId]->lessonNumber,
                    $gene->duracaoTempos(),
                    $data,
                ),
        ));

        foreach ($this->extensions as $extension) {
            $possible = array_values(array_unique($extension->filterCandidateStartSlots($gene, $data, $possible)));
        }

        return $possible;
    }

    /**
     * @param int[] $neighborhoodIndexes
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
                static fn (int $occupiedIndex): bool => $occupiedIndex !== $sourceGeneIndex,
            ));
            $score += count(array_filter(
                $turmaIndex[$candidate->turmaId()][$candidate->diaSemana()][$period] ?? [],
                static fn (int $occupiedIndex): bool => $occupiedIndex !== $sourceGeneIndex,
            ));
        }

        return $score;
    }

    /**
     * @param string[] $violationTypes
     * @return array{0: float, 1: int, 2: float, 3: int}
     */
    private function buildRepairRanking(Cromossomo $chromosome, Gene $candidate, int $sourceGeneIndex, array $violationTypes): array
    {
        $signature = $chromosome->signature();

        if (! isset($this->repairRankingFitnessCache[$signature])) {
            $this->repairRankingFitnessCache[$signature] = $this->probeFitness($chromosome, $this->activeFitnessProbe);
        }

        $fitness = $this->repairRankingFitnessCache[$signature];
        $hardPenalty = $fitness['hard_penalty'] ?? INF;
        $softPenalty = $fitness['soft_penalty'] ?? INF;
        $remainingViolations = $this->countRemainingTargetViolations($chromosome, $sourceGeneIndex, $violationTypes);
        $extensionPenalty = $this->extensionPenaltyForCandidate($chromosome, $candidate, $sourceGeneIndex, $violationTypes);
        $conflictScore = $this->sameEntityLoadScore($chromosome, $candidate, $sourceGeneIndex);

        return [$hardPenalty, $remainingViolations, $extensionPenalty, $softPenalty, $conflictScore];
    }

    /**
     * @param string[] $violationTypes
     */
    private function extensionPenaltyForCandidate(
        Cromossomo $chromosome,
        Gene $candidate,
        int $sourceGeneIndex,
        array $violationTypes,
    ): float {
        if ($this->activeData === null) {
            return 0.0;
        }

        $penalty = 0.0;

        foreach ($this->extensions as $extension) {
            $penalty += $extension->candidateRankingPenalty(
                $chromosome,
                $candidate,
                $sourceGeneIndex,
                $violationTypes,
                $this->activeData,
            );
        }

        return $penalty;
    }

    /**
     * @param string[] $violationTypes
     */
    private function countRemainingTargetViolations(Cromossomo $chromosome, int $geneIndex, array $violationTypes): int
    {
        $remaining = 0;
        $gene = $chromosome->genes()[$geneIndex] ?? null;

        if ($gene === null) {
            return 0;
        }

        if (in_array('overlap', $violationTypes, true) && ! $this->isValid($chromosome, $gene, $geneIndex)) {
            $remaining++;
        }

        if (in_array('mandatory_block', $violationTypes, true) && $this->isGeneInMandatoryBlockViolation($chromosome, $geneIndex)) {
            $remaining++;
        }

        if ($this->activeData !== null) {
            foreach ($this->extensions as $extension) {
                $remaining += $extension->countTargetViolations(
                    $chromosome,
                    $geneIndex,
                    $violationTypes,
                    $this->activeData,
                );
            }
        }

        return $remaining;
    }

    /**
     * @param string[] $violationTypes
     */
    private function isRepairTargetResolved(Cromossomo $chromosome, int $geneIndex, array $violationTypes): bool
    {
        return $this->countRemainingTargetViolations($chromosome, $geneIndex, $violationTypes) === 0;
    }

    private function isGeneInMandatoryBlockViolation(Cromossomo $chromosome, int $geneIndex): bool
    {
        return isset($this->buildMandatoryBlockViolationMap($chromosome)[$geneIndex]);
    }

    /**
     * @param int[] $ignoredIndexes
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
     * @param int[] $ignoredIndexes
     */
    private function hasExternalOccupation(
        array $indexMap,
        int $entityId,
        int $dia,
        int $periodo,
        ?int $sourceGeneIndex,
        array $ignoredIndexes,
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
        $repairTargets = $this->prioritizeRepairTargets($chromosome);

        return [
            'passes' => [],
            'invalid_genes_before' => count($repairTargets),
            'invalid_genes_after' => count($repairTargets),
            'hard_penalty_before' => $fitness['hard_penalty'] ?? null,
            'hard_penalty_after' => $fitness['hard_penalty'] ?? null,
            'soft_penalty_before' => $fitness['soft_penalty'] ?? null,
            'soft_penalty_after' => $fitness['soft_penalty'] ?? null,
            'score_before' => $fitness['score'] ?? null,
            'score_after' => $fitness['score'] ?? null,
            'relocations' => 0,
            'swaps' => 0,
            'local_rebuilds' => 0,
            'repair_target_summary_before' => $this->summarizeRepairTargets($repairTargets),
            'repair_custom_constraint_effectiveness' => $this->buildCustomConstraintEffectiveness(
                $this->summarizeRepairTargets($repairTargets),
                $this->summarizeRepairTargets($repairTargets),
            ),
            'unrepairable_workload_classes' => $this->detectWorkloadExceededClasses($chromosome),
        ];
    }

    /**
     * @param array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}> $repairTargets
     * @return array<string, mixed>
     */
    private function startPassTelemetry(int $pass, Cromossomo $chromosome, array $repairTargets, ?callable $fitnessProbe): array
    {
        $fitness = $this->probeFitness($chromosome, $fitnessProbe);

        return [
            'pass' => $pass,
            'invalid_genes_before' => count($repairTargets),
            'invalid_genes_after' => count($repairTargets),
            'hard_penalty_before' => $fitness['hard_penalty'] ?? null,
            'hard_penalty_after' => $fitness['hard_penalty'] ?? null,
            'hard_penalty_delta' => 0.0,
            'relocations' => 0,
            'swaps' => 0,
            'local_rebuilds' => 0,
            'repair_target_summary_before' => $this->summarizeRepairTargets($repairTargets),
        ];
    }

    /**
     * @param array<string, mixed> $passTelemetry
     */
    private function finishPassTelemetry(array &$passTelemetry, Cromossomo $chromosome, ?callable $fitnessProbe): void
    {
        $fitness = $this->probeFitness($chromosome, $fitnessProbe);
        $passTelemetry['invalid_genes_after'] = count($this->prioritizeRepairTargets($chromosome));
        $passTelemetry['hard_penalty_after'] = $fitness['hard_penalty'] ?? null;
        $passTelemetry['repair_target_summary_after'] = $this->summarizeRepairTargets($this->prioritizeRepairTargets($chromosome));
        $passTelemetry['repair_custom_constraint_effectiveness'] = $this->buildCustomConstraintEffectiveness(
            $passTelemetry['repair_target_summary_before'] ?? [],
            $passTelemetry['repair_target_summary_after'] ?? [],
        );

        if (
            isset($passTelemetry['hard_penalty_before'], $passTelemetry['hard_penalty_after'])
            && $passTelemetry['hard_penalty_before'] !== null
            && $passTelemetry['hard_penalty_after'] !== null
        ) {
            $passTelemetry['hard_penalty_delta'] = round(
                (float) $passTelemetry['hard_penalty_before'] - (float) $passTelemetry['hard_penalty_after'],
                4,
            );
        }
    }

    private function finalizeTelemetry(Cromossomo $chromosome, ?callable $fitnessProbe): void
    {
        $fitness = $this->probeFitness($chromosome, $fitnessProbe);
        $repairTargets = $this->prioritizeRepairTargets($chromosome);

        $this->lastTelemetry['invalid_genes_after'] = count($repairTargets);
        $this->lastTelemetry['hard_penalty_after'] = $fitness['hard_penalty'] ?? null;
        $this->lastTelemetry['soft_penalty_after'] = $fitness['soft_penalty'] ?? null;
        $this->lastTelemetry['score_after'] = $fitness['score'] ?? null;
        $this->lastTelemetry['relocations'] = array_sum(array_column($this->lastTelemetry['passes'], 'relocations'));
        $this->lastTelemetry['swaps'] = array_sum(array_column($this->lastTelemetry['passes'], 'swaps'));
        $this->lastTelemetry['local_rebuilds'] = array_sum(array_column($this->lastTelemetry['passes'], 'local_rebuilds'));
        $this->lastTelemetry['aborted'] = (bool) ($this->lastTelemetry['aborted'] ?? false);
        $this->lastTelemetry['abort_reason'] = $this->lastTelemetry['abort_reason'] ?? null;
        $this->lastTelemetry['repair_target_summary_after'] = $this->summarizeRepairTargets($repairTargets);
        $this->lastTelemetry['repair_custom_constraint_effectiveness'] = $this->buildCustomConstraintEffectiveness(
            $this->lastTelemetry['repair_target_summary_before'] ?? [],
            $this->lastTelemetry['repair_target_summary_after'] ?? [],
        );
        $this->lastTelemetry['unrepairable_workload_classes'] = $this->detectWorkloadExceededClasses($chromosome);
    }

    /**
     * @param array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}> $repairTargets
     * @return array<string, int>
     */
    private function summarizeRepairTargets(array $repairTargets): array
    {
        $summary = [
            'overlap' => 0,
            'mandatory_block' => 0,
        ];

        foreach ($repairTargets as $target) {
            foreach ($target['violations'] ?? [] as $violation) {
                $summary[$violation] = ($summary[$violation] ?? 0) + 1;
            }
        }

        return $summary;
    }

    /**
     * @param array<string, int> $before
     * @param array<string, int> $after
     * @return array<string, mixed>
     */
    private function buildCustomConstraintEffectiveness(array $before, array $after): array
    {
        $types = [];

        foreach ([array_keys($before), array_keys($after)] as $keys) {
            foreach ($keys as $key) {
                if (str_starts_with((string) $key, 'custom_')) {
                    $types[] = (string) $key;
                }
            }
        }

        $types = array_values(array_unique($types));
        sort($types);

        $byType = [];
        $totals = ['before' => 0, 'after' => 0, 'resolved' => 0];

        foreach ($types as $type) {
            $beforeCount = max(0, (int) ($before[$type] ?? 0));
            $afterCount = max(0, (int) ($after[$type] ?? 0));
            $resolved = max(0, $beforeCount - $afterCount);

            $byType[$type] = [
                'before' => $beforeCount,
                'after' => $afterCount,
                'resolved' => $resolved,
                'resolution_rate' => $beforeCount > 0
                    ? round($resolved / $beforeCount, 4)
                    : null,
            ];

            $totals['before'] += $beforeCount;
            $totals['after'] += $afterCount;
            $totals['resolved'] += $resolved;
        }

        $totals['resolution_rate'] = $totals['before'] > 0
            ? round($totals['resolved'] / $totals['before'], 4)
            : null;

        return [
            'types' => $types,
            'by_type' => $byType,
            'totals' => $totals,
        ];
    }

    /**
     * @return int[]
     */
    private function detectWorkloadExceededClasses(Cromossomo $chromosome): array
    {
        if ($this->activeData === null) {
            return [];
        }

        $expectedLoad = $this->activeData->expectedLoadByLesson;
        $actualLoad = $chromosome->cargaTurma();
        $exceeded = [];

        foreach ($actualLoad as $classId => $load) {
            $expected = $expectedLoad[$classId] ?? null;

            if ($expected !== null && $load > $expected) {
                $exceeded[] = (int) $classId;
            }
        }

        return $exceeded;
    }

    /**
     * @param array<string, mixed> $passTelemetry
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
            'repair_target_summary_before' => $passTelemetry['repair_target_summary_before'] ?? null,
            'hard_penalty_before' => $passTelemetry['hard_penalty_before'] ?? null,
            'hard_penalty_after' => $passTelemetry['hard_penalty_after'] ?? null,
            'hard_penalty_delta' => $passTelemetry['hard_penalty_delta'] ?? null,
            'relocations' => $passTelemetry['relocations'] ?? 0,
            'swaps' => $passTelemetry['swaps'] ?? 0,
            'local_rebuilds' => $passTelemetry['local_rebuilds'] ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $passTelemetry
     */
    private function emitProgressHeartbeat(
        ?callable $progressHeartbeat,
        array $passTelemetry,
        int $processedInvalidGenes,
        int $totalInvalidGenes,
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
        int $passesWithoutProgress,
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

    private function abortForTimeBudget(
        ?callable $progressHeartbeat,
        int $pass,
        ?int $timeBudgetMs,
        int $passesWithoutProgress,
    ): void {
        $this->lastTelemetry['aborted'] = true;
        $this->lastTelemetry['abort_reason'] = 'time_budget_exhausted';
        $this->lastTelemetry['time_budget_ms'] = $timeBudgetMs;
        $this->emitAbortHeartbeat($progressHeartbeat, 'time_budget_exhausted', $pass, $timeBudgetMs, $passesWithoutProgress);
    }

    private function deadlineExceeded(): bool
    {
        if ($this->activeDeadlineAt === null) {
            return false;
        }

        if ($this->activeTimeBudgetExceeded) {
            return true;
        }

        if (microtime(true) >= $this->activeDeadlineAt) {
            $this->activeTimeBudgetExceeded = true;
        }

        return $this->activeTimeBudgetExceeded;
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
            $repairTargets = $this->prioritizeRepairTargets($chromosome);
            $hardPenalty = 0.0;

            foreach ($repairTargets as $target) {
                $hardPenalty += max(1, (int) ($target['count'] ?? 0));
            }

            return [
                'hard_penalty' => $hardPenalty,
                'soft_penalty' => 0.0,
                'score' => -1 * $hardPenalty,
            ];
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
            $data->timeSlots,
        ));
    }
}
