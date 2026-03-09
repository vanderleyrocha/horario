<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

use App\Modules\AG\Domain\Operators\Mutation\MutationOperatorInterface;

class LearningHyperHeuristicController
{
    public function __construct(private OperatorPerformanceTracker $tracker)
    {
    }

    public function selectOperator(array $operators): MutationOperatorInterface
    {
        if (mt_rand(0, 100) < 20) {
            return $operators[array_rand($operators)];
        }

        $best = $this->tracker->bestOperator();

        foreach ($operators as $op) {
            if ($op::class === $best) {
                return $op;
            }
        }

        return $operators[array_rand($operators)];
    }

    public function record(MutationOperatorInterface $operator, float $improvement): void
    {

        $this->tracker->record($operator::class, $improvement);
    }
}
