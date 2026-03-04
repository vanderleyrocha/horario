<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Problem;

use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Core\Entities\Gene;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class ScheduleProblem implements GeneticProblem {
    public function __construct(
        private readonly ScheduleData $data,
        private readonly EvaluationContextBuilder $contextBuilder,
        private readonly FitnessEvaluator $fitnessEvaluator
    ) {
    }

    public function createIndividual(): Cromossomo {
        $genes = [];

        foreach ($this->data->lessons as $lesson) {

            $slot = $this->data->timeSlots[array_rand($this->data->timeSlots)];

            $genes[] = new Gene(
                aulaId: $lesson->id,
                professorId: $lesson->professorId,
                turmaId: $lesson->classId,
                disciplinaId: $lesson->disciplinaId,
                diaSemana: $slot->day,
                periodoDia: $slot->lessonNumber,
                duracaoTempos: $lesson->requiredSlots
            );
        }

        return new Cromossomo($genes);
    }

    public function evaluate(Cromossomo $individual): FitnessResult {
        $context = $this->contextBuilder->build($individual);

        return $this->fitnessEvaluator->evaluate($individual, $context);
    }

    public function repair(Cromossomo $individual): Cromossomo {
        return $individual; // пока neutro, podemos evoluir depois
    }

    public function isFeasible(Cromossomo $individual): bool {
        $result = $this->evaluate($individual);

        return $result->hardPenalty() === 0.0;
    }
}
