<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

class OperatorScore
{
    private string $operator;

    private int $uses = 0;

    private float $totalReward = 0;

    /*
    ---------------------------------------------------------
    CACHE DO SCORE (reduz custo do UCB1)
    ---------------------------------------------------------
    */

    private float $cachedScore = 0;

    private bool $dirty = true;

    public function __construct(string $operator)
    {
        $this->operator = $operator;
    }

    /*
    ---------------------------------------------------------
    Registrar uso
    ---------------------------------------------------------
    */

    public function registerUse(): void
    {
        $this->uses++;

        $this->dirty = true;
    }

    /*
    ---------------------------------------------------------
    Registrar reward
    ---------------------------------------------------------
    */

    public function addReward(float $reward): void
    {
        $this->totalReward += $reward;

        $this->dirty = true;
    }

    /*
    ---------------------------------------------------------
    Média de reward
    ---------------------------------------------------------
    */

    public function averageReward(): float
    {
        if ($this->uses === 0) {
            return 0;
        }

        return $this->totalReward / $this->uses;
    }

    /*
    ---------------------------------------------------------
    Score usado pelo Hyper-Heuristic
    ---------------------------------------------------------
    */

    public function score(): float
    {
        if (! $this->dirty) {
            return $this->cachedScore;
        }

        $avg = $this->averageReward();

        $exploration = sqrt(2 * log($this->uses + 1));

        $this->cachedScore = $avg + $exploration;

        $this->dirty = false;

        return $this->cachedScore;
    }

    public function uses(): int
    {
        return $this->uses;
    }

    public function name(): string
    {
        return $this->operator;
    }

    public function toArray(): array
    {
        return [
            'reward' => $this->totalReward,
            'uses' => max(1, $this->uses),
            'average' => $this->averageReward(),
            'dirty' => $this->dirty,
            'name' => $this->name(),
            'score' => $this->score(),
        ];
    }
}
