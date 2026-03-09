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

final class ScheduleProblem implements GeneticProblem {
    public function __construct(
        private readonly ScheduleData $data,
        private readonly EvaluationContextBuilder $contextBuilder,
        private readonly FitnessEvaluator $fitnessEvaluator,
        private readonly GreedyRepairOperator $repairOperator
    ) {
    }

    public function createIndividual(): Cromossomo {
        $genes = [];

        foreach ($this->data->lessons as $lesson) {

            $slots =
                $this->data->availableSlotsByClass[$lesson->classId]
                ?? $this->data->timeSlots;

            $slot = $slots[array_rand($slots)];

            $genes[] = new Gene(
                aulaId: $lesson->id,
                professorId: $lesson->professorId,
                turmaId: $lesson->classId,
                disciplinaId: $lesson->disciplinaId,
                diaSemana: $slot['day'],
                periodoDia: $slot['period'],
                duracaoTempos: $lesson->requiredSlots
            );
        }

        return new Cromossomo($genes);
    }

    public function evaluate(Cromossomo $individual): FitnessResult {
        $context =
            $this->contextBuilder->build($individual, $this->data);

        return $this->fitnessEvaluator->evaluate(
            $individual,
            $context
        );
    }

    public function evaluateDelta(
        Cromossomo $individual,
        AffectedRegion $region,
        FitnessResult $previous
    ): FitnessResult {

        $context =
            $this->contextBuilder->build($individual, $this->data);

        return $this->fitnessEvaluator->evaluateDelta(
            $individual,
            $context,
            $region,
            $previous
        );
    }

    public function repair(Cromossomo $individual): Cromossomo {
        return $this->repairOperator->repair(
            $individual,
            $this->data
        );
    }

    public function isFeasible(Cromossomo $individual): bool {
        $result = $this->evaluate($individual);

        return $result->hardPenalty() === 0.0;
    }

    public function clearFitnessCache(): void {
        $this->fitnessEvaluator->clearCache();
    }
}
