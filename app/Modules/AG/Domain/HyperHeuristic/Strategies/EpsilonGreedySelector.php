<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\HyperHeuristic\Strategies;

use App\Modules\AG\Domain\HyperHeuristic\OperatorSelectionStrategy;

final class EpsilonGreedySelector implements OperatorSelectionStrategy
{
    private float $epsilon;

    public function __construct(float $epsilon = 0.1)
    {
        $this->epsilon = $epsilon;
    }

    public function select(array $operatorStats): string
    {
        if (empty($operatorStats)) {
            throw new \RuntimeException('EpsilonGreedySelector recebeu estatísticas vazias.');
        }

        /*
        |-------------------------------------------------------------
        | Exploração (epsilon)
        |-------------------------------------------------------------
        */

        if (mt_rand() / mt_getrandmax() < $this->epsilon) {

            $keys = array_keys($operatorStats);

            return $keys[array_rand($keys)];
        }

        /*
        |-------------------------------------------------------------
        | Exploração greedy
        |-------------------------------------------------------------
        */

        $bestOperator = null;
        $bestScore = -INF;

        foreach ($operatorStats as $name => $stats) {

            $score = $stats['score'] ?? 0;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestOperator = $name;
            }
        }

        if ($bestOperator === null) {

            $keys = array_keys($operatorStats);

            return $keys[array_rand($keys)];
        }

        return $bestOperator;
    }
}
