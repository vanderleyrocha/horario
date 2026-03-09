<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Adaptive;

final class AdaptiveOperatorSelector {
    public function __construct(private readonly OperatorPerformanceTracker $tracker) {
    }

    public function select(array $operators): object {
        $scores = [];

        foreach ($operators as $operator) {

            $class = $operator::class;

            $this->tracker->register($class);

            $scores[$class] = $this->tracker->score($class);
        }

        $sum = array_sum($scores);

        $rand = mt_rand() / mt_getrandmax() * $sum;

        $acc = 0;

        foreach ($operators as $operator) {

            $class = $operator::class;

            $acc += $scores[$class];

            if ($rand <= $acc) {
                return $operator;
            }
        }

        return $operators[array_rand($operators)];
    }
}
