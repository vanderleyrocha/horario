<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeObservation
{
    public function __construct(
        public readonly LandscapePhenomenon $phenomenon,
        public readonly float $confidence,
        public readonly float $bestDelta,
        public readonly float $fitnessGap,
        public readonly int $stagnation,
        public readonly int $plateauDuration,
        public readonly float $convergenceTrend,
        public readonly float $depthScore,
        public readonly float $bestDeltaWindow,
        public readonly float $avgDeltaWindow,
        public readonly float $improvementAcceptanceRate,
        public readonly float $worseningAcceptanceRate,
        public readonly float $populationTurnover,
        public readonly bool $bestSignatureChanged,
        public readonly float $eliteSimilarity,
        public readonly float $diversity,
        public readonly float $entropy
    ) {}

    public function toArray(): array
    {
        return [
            'phenomenon' => $this->phenomenon->value,
            'confidence' => $this->confidence,
            'best_delta' => $this->bestDelta,
            'fitness_gap' => $this->fitnessGap,
            'stagnation' => $this->stagnation,
            'plateau_duration' => $this->plateauDuration,
            'convergence_trend' => $this->convergenceTrend,
            'depth_score' => $this->depthScore,
            'best_delta_window' => $this->bestDeltaWindow,
            'avg_delta_window' => $this->avgDeltaWindow,
            'improvement_acceptance_rate' => $this->improvementAcceptanceRate,
            'worsening_acceptance_rate' => $this->worseningAcceptanceRate,
            'population_turnover' => $this->populationTurnover,
            'best_signature_changed' => $this->bestSignatureChanged,
            'elite_similarity' => $this->eliteSimilarity,
            'diversity' => $this->diversity,
            'entropy' => $this->entropy,
        ];
    }
}
