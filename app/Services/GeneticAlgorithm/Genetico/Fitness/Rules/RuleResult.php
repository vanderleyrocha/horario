<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules;

final class RuleResult
{
    public function __construct(
        private float $penalty,
        private array $conflicts = []
    ) {}

    public function getPenalty(): float
    {
        return $this->penalty;
    }

    public function getConflicts(): array
    {
        return $this->conflicts;
    }
}