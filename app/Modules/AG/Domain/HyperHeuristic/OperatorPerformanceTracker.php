<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

class OperatorPerformanceTracker
{
    private array $scores = [];

    public function record(string $operator, float $improvement): void
    {
        if (!isset($this->scores[$operator])) {
            $this->scores[$operator] = new OperatorScore($operator);
        }

        $score = $this->scores[$operator];

        $score->executions++;
        $score->totalImprovement += $improvement;
    }

    public function bestOperator(): string
    {
        uasort($this->scores, fn ($a, $b) => $b->score() <=> $a->score());

        return array_key_first($this->scores);
    }

    public function scores(): array
    {
        return $this->scores;
    }
}
