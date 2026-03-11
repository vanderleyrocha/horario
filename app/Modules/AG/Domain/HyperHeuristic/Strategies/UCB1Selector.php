<?php

namespace App\Modules\AG\Domain\HyperHeuristic\Strategies;

use App\Modules\AG\Domain\HyperHeuristic\OperatorSelectionStrategy;

class UCB1Selector implements OperatorSelectionStrategy
{
    private float $exploration;

    public function __construct(float $exploration = 2.0)
    {
        $this->exploration = $exploration;
    }

    public function select(array $operators): string
    {
        $totalUses = array_sum(array_column($operators, 'uses'));

        $bestOperator = null;
        $bestScore = -INF;

        foreach ($operators as $name => $data) {

            if ($data['uses'] === 0) {
                return $name;
            }

            $score = ($data['reward'] / $data['uses']) + $this->exploration * sqrt(log($totalUses) / $data['uses']);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestOperator = $name;
            }
        }

        return $bestOperator;
    }
}
