<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Problem;

use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class ScheduleProblem implements GeneticProblem
{
    public function __construct(private readonly ScheduleData $data, private readonly EvaluationContextBuilder $contextBuilder, private readonly FitnessEvaluator $fitnessEvaluator, private readonly GreedyRepairOperator $repairOperator)
    {
    }

    public function createIndividual(): Cromossomo
    {
        $genes = [];

        $teacherBusy = [];
        $classBusy = [];

        $lessons = $this->data->lessons;

        usort($lessons, function ($a, $b) {

            $slotsA = $this->data->availableSlotsByClass[$a->classId] ?? [];

            $slotsB = $this->data->availableSlotsByClass[$b->classId] ?? [];

            return count($slotsA) <=> count($slotsB);
        });

        foreach ($this->data->lessons as $lesson) {

            $slots = $this->data->availableSlotsByClass[$lesson->classId] ?? array_keys($this->data->timeSlots);

            if (empty($slots)) {
                throw new \RuntimeException("Nenhum slot disponível para turma {$lesson->classId}");
            }

            $validSlots = [];

            foreach ($slots as $slotId) {

                $slot = $this->data->timeSlots[$slotId];

                $key = $slot->day . '-' . $slot->lessonNumber;

                if (!isset($teacherBusy[$lesson->professorId][$key]) && !isset($classBusy[$lesson->classId][$key])) {
                    $validSlots[] = $slotId;
                }
            }

            if (!empty($validSlots)) {
                $slotId = $validSlots[array_rand($validSlots)];
            } else {
                $slotId = $slots[array_rand($slots)];
            }

            $slot = $this->data->timeSlots[$slotId];

            $key = $slot->day . '-' . $slot->lessonNumber;

            $teacherBusy[$lesson->professorId][$key] = true;
            $classBusy[$lesson->classId][$key] = true;

            $genes[] = new Gene(aulaId: $lesson->id, professorId: $lesson->professorId, turmaId: $lesson->classId, disciplinaId: $lesson->disciplinaId, diaSemana: $slot->day, periodoDia: $slot->lessonNumber, duracaoTempos: $lesson->requiredSlots);
        }

        return new Cromossomo($genes);
    }

    public function evaluate(Cromossomo $individual): FitnessResult
    {
        $context =
            $this->contextBuilder->build($individual, $this->data);

        return $this->fitnessEvaluator->evaluate($individual, $context);
    }

    public function evaluateDelta(Cromossomo $individual, AffectedRegion $region, FitnessResult $previous): FitnessResult
    {

        $context =
            $this->contextBuilder->build($individual, $this->data);

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
}
