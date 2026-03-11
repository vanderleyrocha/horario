<?php

namespace App\Modules\AG\Domain\HyperHeuristic\Strategies;

use App\Modules\AG\Domain\HyperHeuristic\OperatorSelectionStrategy;

class EpsilonGreedySelector implements OperatorSelectionStrategy
{
    private float $epsilon;

    public function __construct(float $epsilon = 0.1)
    {
        $this->epsilon = $epsilon;
    }

    public function select(array $operators): string
    {
        $rand = mt_rand() / mt_getrandmax();

        if ($rand < $this->epsilon) {
            return array_rand($operators);
        }

        $best = null;
        $bestScore = -INF;

        foreach ($operators as $name => $data) {

            if ($data['reward'] > $bestScore) {
                $bestScore = $data['reward'];
                $best = $name;
            }
        }

        return $best;
    }
}
