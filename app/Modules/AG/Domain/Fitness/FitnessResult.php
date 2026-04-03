<?php

namespace App\Modules\AG\Domain\Fitness;

final class FitnessResult
{
    public function __construct(
        private readonly float $score,
        private readonly float $totalPenalty,
        private readonly float $hardPenalty,
        private readonly float $softPenalty
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->score < 0) {
            throw new \InvalidArgumentException('Fitness score não pode ser negativo.');
        }

        if ($this->totalPenalty < 0) {
            throw new \InvalidArgumentException('Total penalty não pode ser negativo.');
        }

        if ($this->hardPenalty < 0) {
            throw new \InvalidArgumentException('Hard penalty não pode ser negativo.');
        }

        if ($this->softPenalty < 0) {
            throw new \InvalidArgumentException('Soft penalty não pode ser negativo.');
        }

        if (abs(($this->hardPenalty + $this->softPenalty) - $this->totalPenalty) > 0.0001) {
            throw new \InvalidArgumentException(
                'Inconsistência: totalPenalty deve ser igual a hardPenalty + softPenalty.'
            );
        }
    }

    /* ============================================================
     |  GETTERS
     ============================================================ */

    public function score(): float
    {
        return $this->score;
    }

    public function totalPenalty(): float
    {
        return $this->totalPenalty;
    }

    public function hardPenalty(): float
    {
        return $this->hardPenalty;
    }

    public function softPenalty(): float
    {
        return $this->softPenalty;
    }

    /* ============================================================
     |  UTILITÁRIOS
     ============================================================ */

    public function isPerfect(): bool
    {
        return $this->totalPenalty === 0.0;
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'total_penalty' => $this->totalPenalty,
            'hard_penalty' => $this->hardPenalty,
            'soft_penalty' => $this->softPenalty,
        ];
    }
}
