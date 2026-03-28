<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Problem;

use App\Models\ScheduleExecution;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\AG\Support\Exceptions\ExecutionCancelledException;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;
use Illuminate\Support\Facades\Log;

final class ScheduleProblem implements GeneticProblem
{
    private const MAX_BUILD_ATTEMPTS = 12;

    private const RCL_MIN_SIZE = 3;

    private const RCL_ALPHA_MIN = 0.15;

    private const RCL_ALPHA_MAX = 0.45;

    private const TELEMETRY_EVERY_ALLOCATIONS = 25;

    private const INITIAL_QUALITY_GATE_BASE_HARD_PENALTY = 12.0;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_START = 0.005;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_GROWTH = 0.0025;

    private const INITIAL_QUALITY_GATE_CONFLICT_RATIO_MAX = 0.03;

    private const REPAIR_TELEMETRY_SAMPLE_EVERY = 25;

    private string $lastBuildFailure = 'Falha ao montar individuo inicial.';

    private ?array $cachedPlacementQueue = null;

    private ?array $cachedDiagnostics = null;

    private array $candidateSlotIdsByLesson = [];

    private array $availableDaysCountCache = [];

    private int $repairTelemetryCounter = 0;

    private array $lastRepairTelemetry = [];

    public function __construct(private readonly ScheduleData $data, private readonly EvaluationContextBuilder $contextBuilder, private readonly FitnessEvaluator $fitnessEvaluator, private readonly GreedyRepairOperator $repairOperator, private readonly ?ProgressReporterInterface $progress = null, private readonly ?int $executionId = null) {}

    public function createIndividual(): Cromossomo
    {
        $this->assertNotCancelled();
        $queue = $this->buildPlacementQueue();
        $bestRejectedAttempt = null;

        $this->runPreventiveDiagnosis($queue);

        for ($attempt = 1; $attempt <= self::MAX_BUILD_ATTEMPTS; $attempt++) {
            $this->assertNotCancelled();
            $teacherBusy = [];
            $classBusy = [];
            $assignedGenes = [];
            $alpha = $this->randomAlpha();
            $telemetry = [
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'queue_size' => count($queue),
                'allocations' => 0,
                'forced_allocations' => 0,
                'hard_conflict_allocations' => 0,
                'rcl_sizes' => [],
            ];

            Log::info('schedule.initial_population.grasp.start', $telemetry);
            $this->reportInitialPopulationProgress([
                'stage' => 'grasp_start',
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'queue_size' => count($queue),
                'allocations' => 0,
                'forced_allocations' => 0,
                'hard_conflict_allocations' => 0,
                'fill_ratio' => 0,
            ]);

            if ($this->constructWithGrasp($queue, $alpha, $assignedGenes, $teacherBusy, $classBusy, $telemetry)) {
                $candidate = $this->repairWithTelemetry(
                    new Cromossomo($assignedGenes),
                    reportProgress: true,
                    source: 'initial_population_quality_gate'
                );
                $qualityGate = $this->evaluateInitialPopulationQualityGate(
                    candidate: $candidate,
                    attempt: $attempt,
                    queueSize: count($queue),
                    telemetry: $telemetry
                );

                if ($qualityGate['passes']) {
                    $this->reportInitialPopulationProgress([
                        'stage' => 'grasp_completed',
                        'attempt' => $attempt,
                        'alpha' => round($alpha, 4),
                        'queue_size' => count($queue),
                        'allocations' => $telemetry['allocations'],
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                        'fill_ratio' => 1,
                        'hard_penalty' => $qualityGate['hard_penalty'],
                        'soft_penalty' => $qualityGate['soft_penalty'],
                        'fitness_score' => $qualityGate['score'],
                    ]);

                    $this->reportInitialPopulationProgress([
                        'stage' => 'quality_gate_passed',
                        'attempt' => $attempt,
                        'queue_size' => count($queue),
                        'hard_penalty' => $qualityGate['hard_penalty'],
                        'soft_penalty' => $qualityGate['soft_penalty'],
                        'fitness_score' => $qualityGate['score'],
                        'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                        'max_hard_conflict_allocations' => $qualityGate['max_hard_conflict_allocations'],
                        'forced_allocations' => $telemetry['forced_allocations'],
                        'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    ]);

                    return $candidate;
                }

                if (
                    $bestRejectedAttempt === null ||
                    $qualityGate['hard_penalty'] < $bestRejectedAttempt['hard_penalty'] ||
                    (
                        $qualityGate['hard_penalty'] === $bestRejectedAttempt['hard_penalty'] &&
                        $qualityGate['soft_penalty'] < $bestRejectedAttempt['soft_penalty']
                    )
                ) {
                    $bestRejectedAttempt = $qualityGate;
                }

                $this->lastBuildFailure = sprintf(
                    'Quality gate rejeitou tentativa %d: hard_penalty=%.4f (limite=%.4f), hard_conflicts=%d (limite=%d).',
                    $attempt,
                    $qualityGate['hard_penalty'],
                    $qualityGate['max_hard_penalty'],
                    $telemetry['hard_conflict_allocations'],
                    $qualityGate['max_hard_conflict_allocations']
                );

                $this->reportInitialPopulationProgress([
                    'stage' => 'quality_gate_rejected',
                    'attempt' => $attempt,
                    'queue_size' => count($queue),
                    'hard_penalty' => $qualityGate['hard_penalty'],
                    'soft_penalty' => $qualityGate['soft_penalty'],
                    'fitness_score' => $qualityGate['score'],
                    'max_hard_penalty' => $qualityGate['max_hard_penalty'],
                    'max_hard_conflict_allocations' => $qualityGate['max_hard_conflict_allocations'],
                    'forced_allocations' => $telemetry['forced_allocations'],
                    'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    'message' => $this->lastBuildFailure,
                ]);
            }

            // Log::warning('schedule.initial_population.retry', [
            //     'attempt' => $attempt,
            //     'reason' => $this->lastBuildFailure,
            //     'alpha' => round($alpha, 4),
            //     'allocations' => $telemetry['allocations'],
            //     'forced_allocations' => $telemetry['forced_allocations'],
            //     'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
            // ]);

            $this->reportInitialPopulationProgress([
                'stage' => 'grasp_retry',
                'attempt' => $attempt,
                'alpha' => round($alpha, 4),
                'queue_size' => count($queue),
                'allocations' => $telemetry['allocations'],
                'forced_allocations' => $telemetry['forced_allocations'],
                'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                'fill_ratio' => round($telemetry['allocations'] / max(1, count($queue)), 4),
                'message' => $this->lastBuildFailure,
            ]);
        }

        if ($bestRejectedAttempt !== null) {
            $this->lastBuildFailure = sprintf(
                '%s Melhor tentativa rejeitada: hard_penalty=%.4f, soft_penalty=%.4f, score=%.4f.',
                $this->lastBuildFailure,
                $bestRejectedAttempt['hard_penalty'],
                $bestRejectedAttempt['soft_penalty'],
                $bestRejectedAttempt['score']
            );
        }

        throw new \RuntimeException($this->lastBuildFailure);
    }

    public function evaluate(Cromossomo $individual): FitnessResult
    {
        $context = $this->contextBuilder->build($individual, $this->data);

        return $this->fitnessEvaluator->evaluate($individual, $context);
    }

    public function evaluateDelta(Cromossomo $individual, AffectedRegion $region, FitnessResult $previous): FitnessResult
    {
        $context = $this->contextBuilder->build($individual, $this->data);

        return $this->fitnessEvaluator->evaluateDelta($individual, $context, $region, $previous);
    }

    public function repair(Cromossomo $individual): Cromossomo
    {
        return $this->repairWithTelemetry($individual);
    }

    public function repairWithTelemetry(Cromossomo $individual, bool $reportProgress = false, string $source = 'evolution'): Cromossomo
    {
        $probe = null;

        if ($reportProgress) {
            $probe = function (Cromossomo $candidate): array {
                $result = $this->evaluate($candidate);

                return [
                    'hard_penalty' => $result->hardPenalty(),
                    'soft_penalty' => $result->softPenalty(),
                    'score' => $result->score(),
                ];
            };
        }

        $repaired = $this->repairOperator->repair($individual, $this->data, $probe);
        $this->lastRepairTelemetry = $this->repairOperator->lastTelemetry();

        if ($reportProgress && $this->shouldPublishRepairTelemetry($this->lastRepairTelemetry)) {
            $this->reportRepairProgress($source, $this->lastRepairTelemetry);
        }

        return $repaired;
    }

    public function lastRepairTelemetry(): array
    {
        return $this->lastRepairTelemetry;
    }

    public function isFeasible(Cromossomo $individual): bool
    {
        $result = $this->evaluate($individual);

        return $result->hardPenalty() === 0.0;
    }

    public function clearFitnessCache(): void
    {
        $this->fitnessEvaluator->clearCache();
    }

    private function constructWithGrasp(array $queue, float $alpha, array &$assignedGenes, array &$teacherBusy, array &$classBusy, array &$telemetry): bool
    {
        foreach ($queue as $index => $task) {
            $this->assertNotCancelled();
            /** @var LessonData $lesson */
            $lesson = $task['lesson'];
            $occurrence = $task['occurrence'];

            $scoredCandidates = $this->scoreFeasibleCandidates($lesson, $queue, $index, $teacherBusy, $classBusy);

            if (! empty($scoredCandidates)) {
                $rcl = $this->buildRestrictedCandidateList($scoredCandidates, $alpha);
                $slotId = $this->selectFromRcl($rcl);
                $slot = $this->data->timeSlots[$slotId];
                $telemetry['rcl_sizes'][] = count($rcl);
            } else {
                $slot = $this->selectFallbackSlot($lesson);

                if ($slot === null) {
                    $this->lastBuildFailure = "Nenhum slot estrutural para aula {$lesson->id} (ocorrencia {$occurrence}).";

                    return false;
                }

                $telemetry['forced_allocations']++;

                //     Log::warning('schedule.initial_population.grasp.fallback', [
                //         'lesson_id' => $lesson->id,
                //         'occurrence' => $occurrence,
                //         'class_id' => $lesson->classId,
                //         'professor_id' => $lesson->professorId,
                //         'day' => $slot->day,
                //         'period' => $slot->lessonNumber,
                //     ]);
            }

            if (! $this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                $telemetry['hard_conflict_allocations']++;
            }

            $assignedGenes[] = new Gene(aulaId: $lesson->id, professorId: $lesson->professorId, turmaId: $lesson->classId, disciplinaId: $lesson->disciplinaId, diaSemana: $slot->day, periodoDia: $slot->lessonNumber, duracaoTempos: $lesson->requiredSlots);

            $this->occupySlot($lesson, $slot, $teacherBusy, $classBusy);

            $telemetry['allocations']++;

            if ($telemetry['allocations'] % self::TELEMETRY_EVERY_ALLOCATIONS === 0 || $telemetry['allocations'] === $telemetry['queue_size']) {
                // Log::info('schedule.initial_population.grasp.progress', [
                //     'attempt' => $telemetry['attempt'],
                //     'alpha' => $telemetry['alpha'],
                //     'allocations' => $telemetry['allocations'],
                //     'queue_size' => $telemetry['queue_size'],
                //     'forced_allocations' => $telemetry['forced_allocations'],
                //     'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                //     'fill_ratio' => round($telemetry['allocations'] / max(1, $telemetry['queue_size']), 4),
                // ]);
                $this->reportInitialPopulationProgress([
                    'stage' => 'grasp_progress',
                    'attempt' => $telemetry['attempt'],
                    'alpha' => $telemetry['alpha'],
                    'allocations' => $telemetry['allocations'],
                    'queue_size' => $telemetry['queue_size'],
                    'forced_allocations' => $telemetry['forced_allocations'],
                    'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    'fill_ratio' => round($telemetry['allocations'] / max(1, $telemetry['queue_size']), 4),
                ]);
            }
        }

        return count($assignedGenes) === count($queue);
    }

    private function buildPlacementQueue(): array
    {
        if ($this->cachedPlacementQueue !== null) {
            return $this->cachedPlacementQueue;
        }

        $lessons = array_values($this->data->lessons);
        $difficulty = [];

        foreach ($lessons as $lesson) {
            $candidates = $this->getStaticCandidateSlotIds($lesson);
            $candidateCount = count($candidates);
            $demand = $lesson->weeklyOccurrences * $lesson->requiredSlots;
            $professorDays = $this->availableDaysCount($lesson->professorId, true);
            $classDays = $this->availableDaysCount($lesson->classId, false);

            $difficulty[$lesson->id] = [
                'candidate_count' => $candidateCount,
                'demand' => $demand,
                'duration' => $lesson->requiredSlots,
                'weekly_occurrences' => $lesson->weeklyOccurrences,
                'professor_days' => $professorDays,
                'class_days' => $classDays,
                'tie_breaker' => mt_rand(1, 1000),
            ];
        }

        usort($lessons, function (LessonData $a, LessonData $b) use ($difficulty) {
            $scoreA = $difficulty[$a->id];
            $scoreB = $difficulty[$b->id];

            return
                [
                    $scoreA['candidate_count'],
                    $scoreA['professor_days'],
                    $scoreA['class_days'],
                    -$scoreA['demand'],
                    -$scoreA['duration'],
                    -$scoreA['weekly_occurrences'],
                    $scoreA['tie_breaker'],
                ]
                <=>
                [
                    $scoreB['candidate_count'],
                    $scoreB['professor_days'],
                    $scoreB['class_days'],
                    -$scoreB['demand'],
                    -$scoreB['duration'],
                    -$scoreB['weekly_occurrences'],
                    $scoreB['tie_breaker'],
                ];
        });

        $queue = [];

        foreach ($lessons as $lesson) {
            for ($occurrence = 1; $occurrence <= $lesson->weeklyOccurrences; $occurrence++) {
                $queue[] = [
                    'lesson' => $lesson,
                    'occurrence' => $occurrence,
                    'candidate_count' => $difficulty[$lesson->id]['candidate_count'],
                ];
            }
        }

        return $this->cachedPlacementQueue = $queue;
    }

    private function runPreventiveDiagnosis(array $queue): void
    {
        if ($this->cachedDiagnostics !== null) {
            if (! empty($this->cachedDiagnostics['blocked'])) {
                $first = $this->cachedDiagnostics['blocked'][0];
                $this->lastBuildFailure = "Diagnostico preventivo: aula {$first['lesson_id']} tem {$first['candidate_slots']} slots viaveis para {$first['weekly_occurrences']} ocorrencias.";
                throw new \RuntimeException($this->lastBuildFailure);
            }

            return;
        }

        $diagnostics = [];

        foreach ($this->data->lessons as $lesson) {
            $candidateSlotIds = $this->getStaticCandidateSlotIds($lesson);

            $diagnostics[] = [
                'lesson_id' => $lesson->id,
                'candidate_slots' => count($candidateSlotIds),
                'weekly_occurrences' => $lesson->weeklyOccurrences,
                'required_slots' => $lesson->requiredSlots,
                'class_id' => $lesson->classId,
                'professor_id' => $lesson->professorId,
                'professor_available_days' => $this->availableDaysCount($lesson->professorId, true),
                'class_available_days' => $this->availableDaysCount($lesson->classId, false),
            ];
        }

        usort($diagnostics, fn (array $a, array $b) => $a['candidate_slots'] <=> $b['candidate_slots']);

        Log::info('schedule.initial_population.diagnosis', [
            'queue_size' => count($queue),
            'hardest_lessons' => array_slice($diagnostics, 0, 10),
        ]);
        $this->reportInitialPopulationProgress([
            'stage' => 'diagnosis',
            'queue_size' => count($queue),
            'hardest_lessons' => array_slice($diagnostics, 0, 5),
        ]);

        $blocked = array_filter($diagnostics, static fn (array $item) => $item['candidate_slots'] < $item['weekly_occurrences']);
        $blocked = array_values($blocked);
        $this->cachedDiagnostics = [
            'diagnostics' => $diagnostics,
            'blocked' => $blocked,
        ];

        if (! empty($blocked)) {
            $first = $blocked[0];
            $this->lastBuildFailure = "Diagnostico preventivo: aula {$first['lesson_id']} tem {$first['candidate_slots']} slots viaveis para {$first['weekly_occurrences']} ocorrencias.";

            throw new \RuntimeException($this->lastBuildFailure);
        }
    }

    private function scoreFeasibleCandidates(LessonData $lesson, array $queue, int $currentIndex, array $teacherBusy, array $classBusy): array
    {
        $candidateScores = [];

        foreach ($this->getStaticCandidateSlotIds($lesson) as $slotId) {
            $slot = $this->data->timeSlots[$slotId];

            if (! $this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                continue;
            }

            $candidateScores[$slotId] = $this->scoreCandidateSlot($lesson, $slot, $queue, $currentIndex, $teacherBusy, $classBusy);
        }

        asort($candidateScores);

        return $candidateScores;
    }

    private function scoreCandidateSlot(LessonData $lesson, TimeSlot $slot, array $queue, int $currentIndex, array $teacherBusy, array $classBusy): float
    {
        $score = 0.0;

        $score += $this->sameDayLoadPenalty($lesson, $slot, $teacherBusy, $classBusy);
        $score -= $this->futureFlexibilityScore($slot, $queue, $currentIndex, $lesson, $teacherBusy, $classBusy);
        $score += mt_rand(0, 100) / 1000;

        return $score;
    }

    private function futureFlexibilityScore(TimeSlot $slot, array $queue, int $currentIndex, LessonData $currentLesson, array $teacherBusy, array $classBusy): float
    {
        $teacherBusySimulated = $teacherBusy;
        $classBusySimulated = $classBusy;

        $this->occupySlot($currentLesson, $slot, $teacherBusySimulated, $classBusySimulated);

        $score = 0.0;

        foreach (array_slice($queue, $currentIndex + 1, 8) as $task) {
            /** @var LessonData $lesson */
            $lesson = $task['lesson'];

            if ($lesson->professorId !== $currentLesson->professorId && $lesson->classId !== $currentLesson->classId) {
                continue;
            }

            $options = 0;

            foreach ($this->getStaticCandidateSlotIds($lesson) as $slotId) {
                $candidate = $this->data->timeSlots[$slotId];

                if ($this->canUseSlot($lesson, $candidate, $teacherBusySimulated, $classBusySimulated)) {
                    $options++;
                }
            }

            $score += min($options, 6);
        }

        return $score;
    }

    private function sameDayLoadPenalty(LessonData $lesson, TimeSlot $slot, array $teacherBusy, array $classBusy): float
    {
        $teacherDayLoad = 0;
        $classDayLoad = 0;

        foreach ($teacherBusy[$lesson->professorId] ?? [] as $key => $occupied) {
            if (str_starts_with($key, $slot->day.'-')) {
                $teacherDayLoad++;
            }
        }

        foreach ($classBusy[$lesson->classId] ?? [] as $key => $occupied) {
            if (str_starts_with($key, $slot->day.'-')) {
                $classDayLoad++;
            }
        }

        return ($teacherDayLoad * 0.2) + ($classDayLoad * 0.3);
    }

    private function buildRestrictedCandidateList(array $candidateScores, float $alpha): array
    {
        if (empty($candidateScores)) {
            return [];
        }

        $values = array_values($candidateScores);
        $minScore = min($values);
        $maxScore = max($values);
        $threshold = $minScore + ($alpha * ($maxScore - $minScore));

        $rcl = [];

        foreach ($candidateScores as $slotId => $score) {
            if ($score <= $threshold) {
                $rcl[] = $slotId;
            }
        }

        if (count($rcl) < self::RCL_MIN_SIZE) {
            $rcl = array_slice(array_keys($candidateScores), 0, min(self::RCL_MIN_SIZE, count($candidateScores)));
        }

        return $rcl;
    }

    private function selectFromRcl(array $rcl): int
    {
        $index = random_int(0, count($rcl) - 1);

        return $rcl[$index];
    }

    private function selectFallbackSlot(LessonData $lesson): ?TimeSlot
    {
        $candidateSlotIds = $this->getStaticCandidateSlotIds($lesson);

        if (empty($candidateSlotIds)) {
            return null;
        }

        $slotId = $candidateSlotIds[random_int(0, count($candidateSlotIds) - 1)];

        return $this->data->timeSlots[$slotId] ?? null;
    }

    private function availableDaysCount(int $entityId, bool $isProfessor): int
    {
        $cacheKey = ($isProfessor ? 'professor:' : 'class:').$entityId;

        if (array_key_exists($cacheKey, $this->availableDaysCountCache)) {
            return $this->availableDaysCountCache[$cacheKey];
        }

        $slotIds = $isProfessor
            ? ($this->data->availableSlotsByProfessor[$entityId] ?? [])
            : ($this->data->availableSlotsByClass[$entityId] ?? []);

        if (empty($slotIds)) {
            return $this->availableDaysCountCache[$cacheKey] = 0;
        }

        $days = [];

        foreach ($slotIds as $slotId) {
            $slot = $this->data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            $days[$slot->day] = true;
        }

        return $this->availableDaysCountCache[$cacheKey] = count($days);
    }

    private function randomAlpha(): float
    {
        $rand = mt_rand() / mt_getrandmax();

        return self::RCL_ALPHA_MIN + ($rand * (self::RCL_ALPHA_MAX - self::RCL_ALPHA_MIN));
    }

    private function averageRclSize(array $sizes): float
    {
        if (empty($sizes)) {
            return 0.0;
        }

        return round(array_sum($sizes) / count($sizes), 2);
    }

    private function getStaticCandidateSlotIds(LessonData $lesson): array
    {
        if (isset($this->candidateSlotIdsByLesson[$lesson->id])) {
            return $this->candidateSlotIdsByLesson[$lesson->id];
        }

        $candidateSlotIds = [];

        foreach ($this->data->timeSlots as $slotId => $slot) {

            if (! $this->slotSupportsDuration($lesson, $slot)) {
                continue;
            }

            $candidateSlotIds[] = $slotId;
        }

        return $this->candidateSlotIdsByLesson[$lesson->id] = $candidateSlotIds;
    }

    private function reportInitialPopulationProgress(array $payload): void
    {
        if ($this->progress === null) {
            return;
        }

        $this->progress->report(array_merge([
            'phase' => 'initial_population',
            'execution_id' => $this->executionId,
        ], $payload));
    }

    private function reportRepairProgress(string $source, array $telemetry): void
    {
        if ($this->progress === null) {
            return;
        }

        $this->progress->report([
            'phase' => 'repair',
            'stage' => 'repair_summary',
            'source' => $source,
            'execution_id' => $this->executionId,
            'hard_penalty_before' => $telemetry['hard_penalty_before'] ?? null,
            'hard_penalty_after' => $telemetry['hard_penalty_after'] ?? null,
            'soft_penalty_after' => $telemetry['soft_penalty_after'] ?? null,
            'score_after' => $telemetry['score_after'] ?? null,
            'invalid_genes_before' => $telemetry['invalid_genes_before'] ?? null,
            'invalid_genes_after' => $telemetry['invalid_genes_after'] ?? null,
            'relocations' => $telemetry['relocations'] ?? 0,
            'swaps' => $telemetry['swaps'] ?? 0,
            'local_rebuilds' => $telemetry['local_rebuilds'] ?? 0,
            'passes' => array_map(
                static fn (array $pass): array => [
                    'pass' => $pass['pass'],
                    'hard_penalty_before' => $pass['hard_penalty_before'],
                    'hard_penalty_after' => $pass['hard_penalty_after'],
                    'hard_penalty_delta' => $pass['hard_penalty_delta'],
                    'invalid_genes_before' => $pass['invalid_genes_before'],
                    'invalid_genes_after' => $pass['invalid_genes_after'],
                    'relocations' => $pass['relocations'],
                    'swaps' => $pass['swaps'],
                    'local_rebuilds' => $pass['local_rebuilds'],
                ],
                $telemetry['passes'] ?? []
            ),
        ]);
    }

    private function shouldPublishRepairTelemetry(array $telemetry): bool
    {
        $this->repairTelemetryCounter++;

        if (($telemetry['hard_penalty_before'] ?? null) === null) {
            return false;
        }

        if (($telemetry['hard_penalty_after'] ?? INF) <= 0.0) {
            return true;
        }

        if (($telemetry['relocations'] ?? 0) > 0 || ($telemetry['swaps'] ?? 0) > 0 || ($telemetry['local_rebuilds'] ?? 0) > 0) {
            return $this->repairTelemetryCounter % self::REPAIR_TELEMETRY_SAMPLE_EVERY === 0;
        }

        return false;
    }

    private function evaluateInitialPopulationQualityGate(
        Cromossomo $candidate,
        int $attempt,
        int $queueSize,
        array $telemetry
    ): array {
        $result = $this->evaluate($candidate);
        $thresholds = $this->initialQualityGateThresholds($attempt, $queueSize);

        return [
            'passes' => $result->hardPenalty() <= $thresholds['max_hard_penalty']
                && $telemetry['hard_conflict_allocations'] <= $thresholds['max_hard_conflict_allocations'],
            'hard_penalty' => $result->hardPenalty(),
            'soft_penalty' => $result->softPenalty(),
            'score' => $result->score(),
            'max_hard_penalty' => $thresholds['max_hard_penalty'],
            'max_hard_conflict_allocations' => $thresholds['max_hard_conflict_allocations'],
        ];
    }

    private function initialQualityGateThresholds(int $attempt, int $queueSize): array
    {
        $conflictRatio = min(
            self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_MAX,
            self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_START + (($attempt - 1) * self::INITIAL_QUALITY_GATE_CONFLICT_RATIO_GROWTH)
        );
        $maxHardConflictAllocations = max(1, (int) ceil($queueSize * $conflictRatio));

        return [
            'max_hard_conflict_allocations' => $maxHardConflictAllocations,
            'max_hard_penalty' => max(
                self::INITIAL_QUALITY_GATE_BASE_HARD_PENALTY,
                $maxHardConflictAllocations * 6.0
            ),
        ];
    }

    private function assertNotCancelled(): void
    {
        if ($this->executionId === null) {
            return;
        }

        $status = ScheduleExecution::query()
            ->whereKey($this->executionId)
            ->value('status');

        if (in_array($status, ['cancel_requested', 'cancelled'], true)) {
            throw ExecutionCancelledException::forExecution($this->executionId);
        }
    }

    private function canUseSlot(LessonData $lesson, TimeSlot $slot, array $teacherBusy, array $classBusy): bool
    {
        if (! $this->slotSupportsDuration($lesson, $slot)) {
            return false;
        }

        for ($offset = 0; $offset < $lesson->requiredSlots; $offset++) {
            $key = $slot->day.'-'.($slot->lessonNumber + $offset);

            if (isset($teacherBusy[$lesson->professorId][$key]) || isset($classBusy[$lesson->classId][$key])) {
                return false;
            }
        }

        return true;
    }

    private function slotSupportsDuration(LessonData $lesson, TimeSlot $slot): bool
    {
        return ($slot->lessonNumber + $lesson->requiredSlots - 1) <= $this->maxLessonNumber();
    }

    private function occupySlot(LessonData $lesson, TimeSlot $slot, array &$teacherBusy, array &$classBusy): void
    {
        for ($offset = 0; $offset < $lesson->requiredSlots; $offset++) {
            $key = $slot->day.'-'.($slot->lessonNumber + $offset);

            $teacherBusy[$lesson->professorId][$key] = true;
            $classBusy[$lesson->classId][$key] = true;
        }
    }

    private function maxLessonNumber(): int
    {
        return max(array_map(static fn (TimeSlot $slot) => $slot->lessonNumber, $this->data->timeSlots));
    }
}
