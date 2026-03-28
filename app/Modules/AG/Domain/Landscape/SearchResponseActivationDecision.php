<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseActivationDecision
{
    public function __construct(
        public readonly bool $eligibleAsCandidate,
        public readonly ?string $candidatePolicy,
        public readonly string $mode,
        public readonly int $minimumResolvedOutcomes,
        public readonly float $minimumSuccessRate,
        public readonly float $minimumApproachRate,
        public readonly float $minimumAvgProgressScore,
        public readonly ?array $supportingStats,
        public readonly string $reason
    ) {}

    public function toArray(): array
    {
        return [
            'eligible_as_candidate' => $this->eligibleAsCandidate,
            'candidate_policy' => $this->candidatePolicy,
            'mode' => $this->mode,
            'minimum_resolved_outcomes' => $this->minimumResolvedOutcomes,
            'minimum_success_rate' => round($this->minimumSuccessRate, 6),
            'minimum_approach_rate' => round($this->minimumApproachRate, 6),
            'minimum_avg_progress_score' => round($this->minimumAvgProgressScore, 6),
            'supporting_stats' => $this->supportingStats,
            'reason' => $this->reason,
        ];
    }
}
