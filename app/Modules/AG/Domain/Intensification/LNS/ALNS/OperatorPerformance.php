<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

final class OperatorPerformance
{
    private int $uses = 0;

    private float $totalReward = 0.0;

    private float $weight = 1.0;

    public function registerUse(): void
    {
        $this->uses++;
        $this->refreshWeight();
    }

    public function reward(float $value): void
    {
        $this->totalReward += $value;
        $this->refreshWeight();
    }

    public function uses(): int
    {
        return $this->uses;
    }

    public function rewardTotal(): float
    {
        return $this->totalReward;
    }

    public function meanReward(): float
    {
        if ($this->uses === 0) {
            return 0.0;
        }

        return $this->totalReward / $this->uses;
    }

    public function weight(): float
    {
        return $this->weight;
    }

    public function toArray(): array
    {
        return [
            'uses' => $this->uses,
            'reward' => $this->rewardTotal(),
            'mean_reward' => $this->meanReward(),
            'weight' => $this->weight(),
        ];
    }

    private function refreshWeight(): void
    {
        $this->weight = 1.0 + $this->meanReward();
    }
}
