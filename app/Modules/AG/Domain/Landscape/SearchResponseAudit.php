<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseAudit
{
    public function __construct(
        public readonly string $policy,
        public readonly int $auditGeneration,
        public readonly bool $shadowMode,
        public readonly bool $wouldTrigger,
        public readonly int $evaluationHorizonGenerations,
        public readonly ?float $targetBestDeltaWindow,
        public readonly ?float $targetPopulationTurnover,
        public readonly ?float $targetMaxEliteSimilarity,
        public readonly ?float $targetMaxBasinLockConfidence,
        public readonly float $observedBestDeltaWindow,
        public readonly float $observedPopulationTurnover,
        public readonly float $observedEliteSimilarity,
        public readonly float $observedBasinLockConfidence,
        public readonly ?float $bestDeltaWindowGap,
        public readonly ?float $populationTurnoverGap,
        public readonly ?float $eliteSimilarityReductionNeeded,
        public readonly ?float $basinLockConfidenceReductionNeeded,
        public readonly string $reason
    ) {}

    public function toArray(): array
    {
        return [
            'policy' => $this->policy,
            'audit_generation' => $this->auditGeneration,
            'shadow_mode' => $this->shadowMode,
            'would_trigger' => $this->wouldTrigger,
            'evaluation_horizon_generations' => $this->evaluationHorizonGenerations,
            'target_best_delta_window' => $this->roundNullable($this->targetBestDeltaWindow),
            'target_population_turnover' => $this->roundNullable($this->targetPopulationTurnover),
            'target_max_elite_similarity' => $this->roundNullable($this->targetMaxEliteSimilarity),
            'target_max_basin_lock_confidence' => $this->roundNullable($this->targetMaxBasinLockConfidence),
            'observed_best_delta_window' => round($this->observedBestDeltaWindow, 6),
            'observed_population_turnover' => round($this->observedPopulationTurnover, 6),
            'observed_elite_similarity' => round($this->observedEliteSimilarity, 6),
            'observed_basin_lock_confidence' => round($this->observedBasinLockConfidence, 6),
            'best_delta_window_gap' => $this->roundNullable($this->bestDeltaWindowGap),
            'population_turnover_gap' => $this->roundNullable($this->populationTurnoverGap),
            'elite_similarity_reduction_needed' => $this->roundNullable($this->eliteSimilarityReductionNeeded),
            'basin_lock_confidence_reduction_needed' => $this->roundNullable($this->basinLockConfidenceReductionNeeded),
            'reason' => $this->reason,
        ];
    }

    private function roundNullable(?float $value): ?float
    {
        return $value === null ? null : round($value, 6);
    }
}
