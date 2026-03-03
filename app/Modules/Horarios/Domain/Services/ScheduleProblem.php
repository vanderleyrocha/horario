<?php

namespace App\Modules\Horarios\Domain\Services;

use App\Modules\AG\Domain\Contracts\GeneticProblem;

class ScheduleProblem implements GeneticProblem {
    public function createIndividual(): mixed {
        return ScheduleChromosomeFactory::random();
    }

    public function fitness(mixed $individual): float {
        return FitnessCalculator::calculate($individual);
    }

    public function crossover(mixed $parentA, mixed $parentB): mixed {
        return CrossoverOperator::apply($parentA, $parentB);
    }

    public function mutate(mixed $individual): mixed {
        return MutationOperator::apply($individual);
    }

    public function isFeasible(mixed $individual): bool {
        return ConstraintValidator::validate($individual);
    }
}
