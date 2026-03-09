<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

class OperatorScore
{
    public function __construct(public string $operator, public int $executions = 0, public float $totalImprovement = 0)
    {
    }

    public function score(): float
    {
        if ($this->executions === 0) {
            return 0;
        }

        return $this->totalImprovement / $this->executions;
    }
}
