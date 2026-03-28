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
        public readonly float $entropy,
        public readonly ?array $currentEpisode = null,
        public readonly ?array $previousEpisode = null,
        public readonly float $basinLockConfidence = 0.0,
        public readonly bool $basinLockDetected = false
    ) {}

    public function withEpisodeContext(
        ?array $currentEpisode,
        ?array $previousEpisode,
        float $basinLockConfidence,
        bool $basinLockDetected
    ): self {
        return new self(
            phenomenon: $this->phenomenon,
            confidence: $this->confidence,
            bestDelta: $this->bestDelta,
            fitnessGap: $this->fitnessGap,
            stagnation: $this->stagnation,
            plateauDuration: $this->plateauDuration,
            convergenceTrend: $this->convergenceTrend,
            depthScore: $this->depthScore,
            bestDeltaWindow: $this->bestDeltaWindow,
            avgDeltaWindow: $this->avgDeltaWindow,
            improvementAcceptanceRate: $this->improvementAcceptanceRate,
            worseningAcceptanceRate: $this->worseningAcceptanceRate,
            populationTurnover: $this->populationTurnover,
            bestSignatureChanged: $this->bestSignatureChanged,
            eliteSimilarity: $this->eliteSimilarity,
            diversity: $this->diversity,
            entropy: $this->entropy,
            currentEpisode: $currentEpisode,
            previousEpisode: $previousEpisode,
            basinLockConfidence: $basinLockConfidence,
            basinLockDetected: $basinLockDetected
        );
    }

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
            'current_episode' => $this->currentEpisode,
            'previous_episode' => $this->previousEpisode,
            'basin_of_attraction_lock_confidence' => $this->basinLockConfidence,
            'basin_of_attraction_lock_detected' => $this->basinLockDetected,
        ];
    }
}
