<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Problem;

use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
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

    private string $lastBuildFailure = 'Falha ao montar individuo inicial.';

    public function __construct(
        private readonly ScheduleData $data,
        private readonly EvaluationContextBuilder $contextBuilder,
        private readonly FitnessEvaluator $fitnessEvaluator,
        private readonly GreedyRepairOperator $repairOperator
    ) {
    }

    public function createIndividual(): Cromossomo
    {
        $queue = $this->buildPlacementQueue();

        $this->runPreventiveDiagnosis($queue);

        for ($attempt = 1; $attempt <= self::MAX_BUILD_ATTEMPTS; $attempt++) {
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

            if ($this->constructWithGrasp($queue, $alpha, $assignedGenes, $teacherBusy, $classBusy, $telemetry)) {
                Log::info('schedule.initial_population.grasp.completed', [
                    'attempt' => $attempt,
                    'alpha' => round($alpha, 4),
                    'allocations' => $telemetry['allocations'],
                    'forced_allocations' => $telemetry['forced_allocations'],
                    'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
                    'rcl_avg' => $this->averageRclSize($telemetry['rcl_sizes']),
                    'rcl_min' => empty($telemetry['rcl_sizes']) ? 0 : min($telemetry['rcl_sizes']),
                    'rcl_max' => empty($telemetry['rcl_sizes']) ? 0 : max($telemetry['rcl_sizes']),
                ]);

                return new Cromossomo($assignedGenes);
            }

            Log::warning('schedule.initial_population.retry', [
                'attempt' => $attempt,
                'reason' => $this->lastBuildFailure,
                'alpha' => round($alpha, 4),
                'allocations' => $telemetry['allocations'],
                'forced_allocations' => $telemetry['forced_allocations'],
                'hard_conflict_allocations' => $telemetry['hard_conflict_allocations'],
            ]);
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
        return $this->repairOperator->repair($individual, $this->data);
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

    private function constructWithGrasp(
        array $queue,
        float $alpha,
        array &$assignedGenes,
        array &$teacherBusy,
        array &$classBusy,
        array &$telemetry
    ): bool {
        foreach ($queue as $index => $task) {
            /** @var LessonData $lesson */
            $lesson = $task['lesson'];
            $occurrence = $task['occurrence'];

            $scoredCandidates = $this->scoreFeasibleCandidates(
                $lesson,
                $queue,
                $index,
                $teacherBusy,
                $classBusy
            );

            if (!empty($scoredCandidates)) {
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

                Log::warning('schedule.initial_population.grasp.fallback', [
                    'lesson_id' => $lesson->id,
                    'occurrence' => $occurrence,
                    'class_id' => $lesson->classId,
                    'professor_id' => $lesson->professorId,
                    'day' => $slot->day,
                    'period' => $slot->lessonNumber,
                ]);
            }

            if (!$this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                $telemetry['hard_conflict_allocations']++;
            }

            $assignedGenes[] = new Gene(
                aulaId: $lesson->id,
                professorId: $lesson->professorId,
                turmaId: $lesson->classId,
                disciplinaId: $lesson->disciplinaId,
                diaSemana: $slot->day,
                periodoDia: $slot->lessonNumber,
                duracaoTempos: $lesson->requiredSlots
            );

            $this->occupySlot($lesson, $slot, $teacherBusy, $classBusy);

            $telemetry['allocations']++;

            if (
                $telemetry['allocations'] % self::TELEMETRY_EVERY_ALLOCATIONS === 0
                || $telemetry['allocations'] === $telemetry['queue_size']
            ) {
                Log::info('schedule.initial_population.grasp.progress', [
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

        return $queue;
    }

    private function runPreventiveDiagnosis(array $queue): void
    {
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

        $blocked = array_filter($diagnostics, static fn (array $item) => $item['candidate_slots'] < $item['weekly_occurrences']);

        if (!empty($blocked)) {
            $first = array_shift($blocked);
            $this->lastBuildFailure = "Diagnostico preventivo: aula {$first['lesson_id']} tem {$first['candidate_slots']} slots viaveis para {$first['weekly_occurrences']} ocorrencias.";

            throw new \RuntimeException($this->lastBuildFailure);
        }
    }

    private function scoreFeasibleCandidates(
        LessonData $lesson,
        array $queue,
        int $currentIndex,
        array $teacherBusy,
        array $classBusy
    ): array {
        $candidateScores = [];

        foreach ($this->getStaticCandidateSlotIds($lesson) as $slotId) {
            $slot = $this->data->timeSlots[$slotId];

            if (!$this->canUseSlot($lesson, $slot, $teacherBusy, $classBusy)) {
                continue;
            }

            $candidateScores[$slotId] = $this->scoreCandidateSlot(
                $lesson,
                $slot,
                $queue,
                $currentIndex,
                $teacherBusy,
                $classBusy
            );
        }

        asort($candidateScores);

        return $candidateScores;
    }

    private function scoreCandidateSlot(
        LessonData $lesson,
        TimeSlot $slot,
        array $queue,
        int $currentIndex,
        array $teacherBusy,
        array $classBusy
    ): float {
        $score = 0.0;

        $score += $this->sameDayLoadPenalty($lesson, $slot, $teacherBusy, $classBusy);
        $score -= $this->futureFlexibilityScore($slot, $queue, $currentIndex, $lesson, $teacherBusy, $classBusy);
        $score += mt_rand(0, 100) / 1000;

        return $score;
    }

    private function futureFlexibilityScore(
        TimeSlot $slot,
        array $queue,
        int $currentIndex,
        LessonData $currentLesson,
        array $teacherBusy,
        array $classBusy
    ): float {
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

    private function sameDayLoadPenalty(
        LessonData $lesson,
        TimeSlot $slot,
        array $teacherBusy,
        array $classBusy
    ): float {
        $teacherDayLoad = 0;
        $classDayLoad = 0;

        foreach ($teacherBusy[$lesson->professorId] ?? [] as $key => $occupied) {
            if (str_starts_with($key, $slot->day . '-')) {
                $teacherDayLoad++;
            }
        }

        foreach ($classBusy[$lesson->classId] ?? [] as $key => $occupied) {
            if (str_starts_with($key, $slot->day . '-')) {
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
        $slotIds = $isProfessor
            ? ($this->data->availableSlotsByProfessor[$entityId] ?? [])
            : ($this->data->availableSlotsByClass[$entityId] ?? []);

        if (empty($slotIds)) {
            return 0;
        }

        $days = [];

        foreach ($slotIds as $slotId) {
            $slot = $this->data->timeSlots[$slotId] ?? null;

            if ($slot === null) {
                continue;
            }

            $days[$slot->day] = true;
        }

        return count($days);
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
        $candidateSlotIds = [];

        foreach ($this->data->timeSlots as $slotId => $slot) {

            if (!$this->slotSupportsDuration($lesson, $slot)) {
                continue;
            }

            $candidateSlotIds[] = $slotId;
        }

        return $candidateSlotIds;
    }

    private function canUseSlot(LessonData $lesson, TimeSlot $slot, array $teacherBusy, array $classBusy): bool
    {
        if (!$this->slotSupportsDuration($lesson, $slot)) {
            return false;
        }

        for ($offset = 0; $offset < $lesson->requiredSlots; $offset++) {
            $key = $slot->day . '-' . ($slot->lessonNumber + $offset);

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
            $key = $slot->day . '-' . ($slot->lessonNumber + $offset);

            $teacherBusy[$lesson->professorId][$key] = true;
            $classBusy[$lesson->classId][$key] = true;
        }
    }

    private function maxLessonNumber(): int
    {
        return max(array_map(
            static fn (TimeSlot $slot) => $slot->lessonNumber,
            $this->data->timeSlots
        ));
    }
}
