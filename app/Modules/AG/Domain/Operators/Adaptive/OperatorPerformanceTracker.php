<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Adaptive;

final class OperatorPerformanceTracker {
    private array $scores = [];
    private array $usage = [];

    public function register(string $operator): void {
        $this->scores[$operator] ??= 1.0;
        $this->usage[$operator] ??= 0;
    }

    public function record(string $operator, float $improvement): void {
        $this->usage[$operator]++;

        $this->scores[$operator] = ($this->scores[$operator] * 0.9) + ($improvement * 0.1);
    }

    public function score(string $operator): float {
        return $this->scores[$operator] ?? 1.0;
    }

    public function allScores(): array {
        return $this->scores;
    }
}
