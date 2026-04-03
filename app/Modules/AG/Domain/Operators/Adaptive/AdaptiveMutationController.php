<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Adaptive;

final class AdaptiveMutationController
{
    private float $baseRate;

    private float $amplification;

    private float $maxRate;

    private float $alpha;

    public function __construct(float $baseRate = 0.02, float $amplification = 0.25, float $maxRate = 0.35, float $alpha = 2.0)
    {
        $this->baseRate = $baseRate;
        $this->amplification = $amplification;
        $this->maxRate = $maxRate;
        $this->alpha = $alpha;
    }

    public function computeRate(float $normalizedEntropy, float $diversity = 1.0): float
    {
        $pressure = pow(1 - $normalizedEntropy, $this->alpha);

        $rate = $this->baseRate + $pressure * $this->amplification;
        $rate = min($rate, $this->maxRate);

        // Quando a diversidade colapsa, impõe piso elevado para forçar exploração.
        if ($diversity < 0.15) {
            $rate = max($rate, $this->maxRate * 0.5);
        }

        return $rate;
    }
}
