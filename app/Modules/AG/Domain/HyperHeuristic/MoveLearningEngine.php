<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

use App\Modules\AG\Domain\Landscape\LandscapeState;

final class MoveLearningEngine
{
    private array $table = [];

    private int $window = 40;

    public function record(LandscapeState $state, string $operator, float $reward): void
    {

        $key = $state->value;

        if (! isset($this->table[$key])) {
            $this->table[$key] = [];
        }

        if (! isset($this->table[$key][$operator])) {
            $this->table[$key][$operator] = [];
        }

        $this->table[$key][$operator][] = $reward;

        if (count($this->table[$key][$operator]) > $this->window) {
            array_shift($this->table[$key][$operator]);
        }
    }

    public function operatorScore(LandscapeState $state, string $operator): float
    {

        $key = $state->value;

        if (! isset($this->table[$key][$operator])) {
            return 0;
        }

        $values = $this->table[$key][$operator];

        return array_sum($values) / count($values);
    }

    public function bestOperator(LandscapeState $state): ?string
    {

        $key = $state->value;

        if (! isset($this->table[$key])) {
            return null;
        }

        $best = null;
        $bestScore = -INF;

        foreach ($this->table[$key] as $operator => $values) {

            $score = array_sum($values) / count($values);

            if ($score > $bestScore) {

                $bestScore = $score;
                $best = $operator;
            }
        }

        return $best;
    }
}
