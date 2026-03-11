<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

use App\Modules\AG\Domain\Operators\Mutation\MutationOperatorInterface;

class LearningHyperHeuristicController
{
    private array $operatorMap = [];

    public function __construct(private OperatorPerformanceTracker $tracker, private OperatorSelectionStrategy $selectionStrategy, private OperatorRewardCalculator $rewardCalculator)
    {
    }

    public function selectOperator(array $operators): MutationOperatorInterface
    {
        $this->operatorMap = [];

        foreach ($operators as $operator) {
            $this->operatorMap[$operator::class] = $operator;
        }

        $stats = $this->tracker->getOperatorStatistics();

        $selected = $this->selectionStrategy->select($stats);

        if (isset($this->operatorMap[$selected])) {

            $this->tracker->registerUse($selected);

            return $this->operatorMap[$selected];
        }

        return $operators[array_rand($operators)];
    }

    public function record(MutationOperatorInterface $operator, float $beforeFitness, float $afterFitness): void
    {

        $reward = $this->rewardCalculator->calculate($beforeFitness, $afterFitness);

        $this->tracker->record($operator::class, $reward);
    }

    public function getOperatorStatistics(): array
    {
        return $this->tracker->getOperatorStatistics();
    }
}
