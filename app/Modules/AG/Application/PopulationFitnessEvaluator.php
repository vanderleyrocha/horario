<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Modules\AG\Domain\Contracts\FitnessEvaluatorInterface;
use App\Modules\AG\Domain\Contracts\GeneticProblem;

final class PopulationFitnessEvaluator implements FitnessEvaluatorInterface
{
    public function __construct(
        private readonly GeneticProblem $problem,
        private readonly int $concurrency = 8
    ) {}

    public function evaluate(array $population): void
    {
        foreach ($population as $individual) {
            $this->problem->evaluate($individual);
        }
    }
}
