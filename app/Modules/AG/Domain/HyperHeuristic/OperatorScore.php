<?php

namespace App\Modules\AG\Domain\HyperHeuristic;

class OperatorScore
{
    public string $operator;

    /**
     * Número de vezes que o operador foi utilizado
     */
    public int $uses = 0;

    /**
     * Soma total das recompensas
     */
    public float $totalReward = 0.0;

    /**
     * Última melhoria observada
     */
    public float $lastImprovement = 0.0;

    public function __construct(string $operator)
    {
        $this->operator = $operator;
    }

    public function registerUse(): void
    {
        $this->uses++;
    }

    public function addReward(float $reward): void
    {
        $this->totalReward += $reward;
        $this->lastImprovement = $reward;
    }

    public function averageReward(): float
    {
        if ($this->uses === 0) {
            return 0.0;
        }

        return $this->totalReward / $this->uses;
    }

    public function score(): float
    {
        return $this->averageReward();
    }

    public function toArray(): array
    {
        return [
            'reward' => $this->totalReward,
            'uses' => max(1, $this->uses),
            'average' => $this->averageReward(),
            'last' => $this->lastImprovement
        ];
    }
}
