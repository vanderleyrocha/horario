<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeTrajectorySnapshot
{
    public function __construct(
        public readonly int $generation,
        public readonly float $bestFitness,
        public readonly float $avgFitness,
        public readonly string $bestSignature,
        public readonly float $improvementAcceptanceRate,
        public readonly float $worseningAcceptanceRate,
        public readonly float $populationTurnover,
        public readonly bool $bestSignatureChanged,
        public readonly float $eliteSimilarity
    ) {}
}
