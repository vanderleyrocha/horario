<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseOutcome
{
    public function __construct(
        public readonly string $policy,
        public readonly int $auditGeneration,
        public readonly int $resolvedGeneration,
        public readonly int $horizonGenerations,
        public readonly bool $shadowMode,
        public readonly bool $wouldTrigger,
        public readonly bool $targetsSatisfied,
        public readonly bool $approachedTargets,
        public readonly float $progressScore,
        public readonly ?float $bestDeltaWindowProgress,
        public readonly ?float $populationTurnoverProgress,
        public readonly ?float $eliteSimilarityProgress,
        public readonly ?float $basinLockConfidenceProgress,
        public readonly float $observedBestDeltaWindow,
        public readonly float $observedPopulationTurnover,
        public readonly float $observedEliteSimilarity,
        public readonly float $observedBasinLockConfidence,
        public readonly string $reason
    ) {}

    public function toArray(): array
    {
        return [
            'policy' => $this->policy,
            'audit_generation' => $this->auditGeneration,
            'resolved_generation' => $this->resolvedGeneration,
            'horizon_generations' => $this->horizonGenerations,
            'shadow_mode' => $this->shadowMode,
            'would_trigger' => $this->wouldTrigger,
            'targets_satisfied' => $this->targetsSatisfied,
            'approached_targets' => $this->approachedTargets,
            'progress_score' => round($this->progressScore, 6),
            'best_delta_window_progress' => $this->roundNullable($this->bestDeltaWindowProgress),
            'population_turnover_progress' => $this->roundNullable($this->populationTurnoverProgress),
            'elite_similarity_progress' => $this->roundNullable($this->eliteSimilarityProgress),
            'basin_lock_confidence_progress' => $this->roundNullable($this->basinLockConfidenceProgress),
            'observed_best_delta_window' => round($this->observedBestDeltaWindow, 6),
            'observed_population_turnover' => round($this->observedPopulationTurnover, 6),
            'observed_elite_similarity' => round($this->observedEliteSimilarity, 6),
            'observed_basin_lock_confidence' => round($this->observedBasinLockConfidence, 6),
            'reason' => $this->reason,
        ];
    }

    private function roundNullable(?float $value): ?float
    {
        return $value === null ? null : round($value, 6);
    }
}
