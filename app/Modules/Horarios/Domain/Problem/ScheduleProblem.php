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
    private const MAX_BACKTRACK_STEPS = 1500;
    private const MAX_CANDIDATES_PER_STEP = 10;

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
            $backtrackSteps = 0;

            if ($this->assignOccurrence(
                0,
                $queue,
                $assignedGenes,
                $teacherBusy,
                $classBusy,
                $backtrackSteps
            )) {
                return new Cromossomo(array_values($assignedGenes));
            }

            Log::warning('schedule.initial_population.retry', [
                'attempt' => $attempt,
                'reason' => $this->lastBuildFailure,
                'backtrack_steps' => $backtrackSteps,
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

    private function assignOccurrence(
        int $index,
        array $queue,
        array &$assignedGenes,
        array &$teacherBusy,
        array &$classBusy,
        int &$backtrackSteps
    ): bool {
        if ($index >= count($queue)) {
            return true;
        }

        if ($backtrackSteps >= self::MAX_BACKTRACK_STEPS) {
            $this->lastBuildFailure = 'Limite de backtracking atingido ao montar individuo inicial.';

            return false;
        }

        /** @var LessonData $lesson */
        $lesson = $queue[$index]['lesson'];
        $occurrence = $queue[$index]['occurrence'];

        $candidateSlotIds = $this->selectSlotCandidates(
            $lesson,
            $queue,
            $index,
            $teacherBusy,
            $classBusy
        );

        if (empty($candidateSlotIds)) {
            $this->lastBuildFailure = "Nenhum slot disponivel para aula {$lesson->id} (ocorrencia {$occurrence}).";

            return false;
        }

        foreach (array_slice($candidateSlotIds, 0, self::MAX_CANDIDATES_PER_STEP) as $slotId) {
            $slot = $this->data->timeSlots[$slotId];
            $gene = new Gene(
                aulaId: $lesson->id,
                professorId: $lesson->professorId,
                turmaId: $lesson->classId,
                disciplinaId: $lesson->disciplinaId,
                diaSemana: $slot->day,
                periodoDia: $slot->lessonNumber,
                duracaoTempos: $lesson->requiredSlots
            );

            $this->occupySlot($lesson, $slot, $teacherBusy, $classBusy);
            $assignedGenes[$index] = $gene;

            if ($this->assignOccurrence(
                $index + 1,
                $queue,
                $assignedGenes,
                $teacherBusy,
                $classBusy,
                $backtrackSteps
            )) {
                return true;
            }

            unset($assignedGenes[$index]);
            $this->releaseSlot($lesson, $slot, $teacherBusy, $classBusy);
            $backtrackSteps++;
        }

        return false;
    }

    private function buildPlacementQueue(): array
    {
        $lessons = array_values($this->data->lessons);
        $difficulty = [];

        foreach ($lessons as $lesson) {
            $candidates = $this->getStaticCandidateSlotIds($lesson);
            $candidateCount = count($candidates);
            $demand = $lesson->weeklyOccurrences * $lesson->requiredSlots;
            $preferenceTightness = count($lesson->preferredDays) + count($lesson->preferredPeriods);

            $difficulty[$lesson->id] = [
                'candidate_count' => $candidateCount,
                'demand' => $demand,
                'duration' => $lesson->requiredSlots,
                'weekly_occurrences' => $lesson->weeklyOccurrences,
                'preference_tightness' => $preferenceTightness,
            ];
        }

        usort($lessons, function (LessonData $a, LessonData $b) use ($difficulty) {
            $scoreA = $difficulty[$a->id];
            $scoreB = $difficulty[$b->id];

            return
                [$scoreA['candidate_count'], -$scoreA['demand'], -$scoreA['duration'], -$scoreA['weekly_occurrences'], -$scoreA['preference_tightness']]
                <=>
                [$scoreB['candidate_count'], -$scoreB['demand'], -$scoreB['duration'], -$scoreB['weekly_occurrences'], -$scoreB['preference_tightness']];
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
            $preferredSlotIds = $this->filterPreferredSlotIds($lesson, $candidateSlotIds);

            $diagnostics[] = [
                'lesson_id' => $lesson->id,
                'candidate_slots' => count($candidateSlotIds),
                'preferred_slots' => count($preferredSlotIds),
                'weekly_occurrences' => $lesson->weeklyOccurrences,
                'required_slots' => $lesson->requiredSlots,
                'class_id' => $lesson->classId,
                'professor_id' => $lesson->professorId,
            ];
        }

        usort($diagnostics, fn (array $a, array $b) => $a['candidate_slots'] <=> $b['candidate_slots']);

        Log::info('schedule.initial_population.diagnosis', [
            'hardest_lessons' => array_slice($diagnostics, 0, 10),
        ]);

        $blocked = array_filter($diagnostics, static fn (array $item) => $item['candidate_slots'] < $item['weekly_occurrences']);

        if (!empty($blocked)) {
            $first = array_shift($blocked);
            $this->lastBuildFailure = "Diagnostico preventivo: aula {$first['lesson_id']} tem {$first['candidate_slots']} slots viaveis para {$first['weekly_occurrences']} ocorrencias.";

            throw new \RuntimeException($this->lastBuildFailure);
        }
    }

    private function selectSlotCandidates(
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

        return array_keys($candidateScores);
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

        if (!empty($lesson->preferredDays) && !in_array($slot->day, $lesson->preferredDays, true)) {
            $score += 15;
        }

        if (!empty($lesson->preferredPeriods) && !in_array($slot->lessonNumber, $lesson->preferredPeriods, true)) {
            $score += 10;
        }

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

    private function getStaticCandidateSlotIds(LessonData $lesson): array
    {
        $classSlots = $this->data->availableSlotsByClass[$lesson->classId] ?? [];
        $teacherSlots = $this->data->availableSlotsByProfessor[$lesson->professorId] ?? [];

        $intersection = array_values(array_intersect($classSlots, $teacherSlots));
        $candidateSlotIds = [];

        foreach ($intersection as $slotId) {
            $slot = $this->data->timeSlots[$slotId];

            if (!$this->slotSupportsDuration($lesson, $slot)) {
                continue;
            }

            $candidateSlotIds[] = $slotId;
        }

        return $candidateSlotIds;
    }

    private function filterPreferredSlotIds(LessonData $lesson, array $slotIds): array
    {
        return array_values(array_filter($slotIds, function (int $slotId) use ($lesson) {
            $slot = $this->data->timeSlots[$slotId];

            if (!empty($lesson->preferredDays) && !in_array($slot->day, $lesson->preferredDays, true)) {
                return false;
            }

            if (!empty($lesson->preferredPeriods) && !in_array($slot->lessonNumber, $lesson->preferredPeriods, true)) {
                return false;
            }

            return true;
        }));
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

    private function releaseSlot(LessonData $lesson, TimeSlot $slot, array &$teacherBusy, array &$classBusy): void
    {
        for ($offset = 0; $offset < $lesson->requiredSlots; $offset++) {
            $key = $slot->day . '-' . ($slot->lessonNumber + $offset);

            unset($teacherBusy[$lesson->professorId][$key], $classBusy[$lesson->classId][$key]);
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
