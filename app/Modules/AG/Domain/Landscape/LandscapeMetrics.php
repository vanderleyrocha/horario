<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeMetrics
{
    public function __construct(
        public readonly int $generation, public readonly float $bestFitness, public readonly float $avgFitness,
        public readonly float $variance, public readonly float $diversity, public readonly float $entropy,
        public readonly int $stagnation,
        public readonly float $improvementAcceptanceRate = 0.0,
        public readonly float $worseningAcceptanceRate = 0.0,
        public readonly float $populationTurnover = 0.0,
        public readonly bool $bestSignatureChanged = false,
        public readonly float $eliteSimilarity = 0.0,
        public readonly string $bestSignature = ''
    ) {}
}
