<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

final class OperatorCreditManager
{
    private array $credits = [];

    private array $history = [];

    private int $window = 30;

    private float $decay = 0.95;

    public function reward(string $operator, float $reward): void
    {
        if (!isset($this->history[$operator])) {
            $this->history[$operator] = [];
        }

        $this->history[$operator][] = $reward;

        if (count($this->history[$operator]) > $this->window) {
            array_shift($this->history[$operator]);
        }

        $this->credits[$operator] = $this->computeCredit($operator);
    }

    private function computeCredit(string $operator): float
    {
        $values = $this->history[$operator] ?? [];

        if (empty($values)) {
            return 0.0;
        }

        $credit = 0.0;
        $weight = 1.0;

        foreach (array_reverse($values) as $reward) {

            $credit += $reward * $weight;

            $weight *= $this->decay;
        }

        return $credit;
    }

    public function credit(string $operator): float
    {
        return $this->credits[$operator] ?? 0.0;
    }

    public function all(): array
    {
        return $this->credits;
    }
}
