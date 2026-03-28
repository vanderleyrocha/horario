<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseActivationGate
{
    public function __construct(
        private readonly int $minimumResolvedOutcomes = 3,
        private readonly float $minimumSuccessRate = 0.60,
        private readonly float $minimumApproachRate = 0.75,
        private readonly float $minimumAvgProgressScore = 0.65
    ) {}

    public function evaluate(SearchResponseEffectivenessReport $report): SearchResponseActivationDecision
    {
        if ($report->totalResolvedOutcomes === 0 || $report->policies === []) {
            return new SearchResponseActivationDecision(
                eligibleAsCandidate: false,
                candidatePolicy: null,
                mode: 'diagnostic_only',
                minimumResolvedOutcomes: $this->minimumResolvedOutcomes,
                minimumSuccessRate: $this->minimumSuccessRate,
                minimumApproachRate: $this->minimumApproachRate,
                minimumAvgProgressScore: $this->minimumAvgProgressScore,
                supportingStats: null,
                blockingReasons: ['No resolved shadow outcomes available yet.'],
                reason: 'No resolved shadow outcomes available yet.'
            );
        }

        $bestPolicy = $report->policies[0] ?? null;

        $eligiblePolicies = array_values(array_filter(
            $report->policies,
            fn (array $policy): bool => ($policy['resolved_outcomes'] ?? 0) >= $this->minimumResolvedOutcomes
                && ($policy['success_rate'] ?? 0.0) >= $this->minimumSuccessRate
                && ($policy['approach_rate'] ?? 0.0) >= $this->minimumApproachRate
                && ($policy['avg_progress_score'] ?? 0.0) >= $this->minimumAvgProgressScore
        ));

        $blockingReasons = $bestPolicy === null
            ? ['No policy statistics available.']
            : $this->blockingReasons($bestPolicy);

        if ($eligiblePolicies === []) {
            return new SearchResponseActivationDecision(
                eligibleAsCandidate: false,
                candidatePolicy: null,
                mode: 'diagnostic_only',
                minimumResolvedOutcomes: $this->minimumResolvedOutcomes,
                minimumSuccessRate: $this->minimumSuccessRate,
                minimumApproachRate: $this->minimumApproachRate,
                minimumAvgProgressScore: $this->minimumAvgProgressScore,
                supportingStats: $bestPolicy,
                blockingReasons: $blockingReasons,
                reason: 'Policies still lack enough resolved evidence to become real activation candidates.'
            );
        }

        usort($eligiblePolicies, static function (array $left, array $right): int {
            return [$right['success_rate'], $right['avg_progress_score'], $right['resolved_outcomes']]
                <=> [$left['success_rate'], $left['avg_progress_score'], $left['resolved_outcomes']];
        });

        $candidate = $eligiblePolicies[0];

        return new SearchResponseActivationDecision(
            eligibleAsCandidate: true,
            candidatePolicy: $candidate['policy'] ?? null,
            mode: 'diagnostic_only',
            minimumResolvedOutcomes: $this->minimumResolvedOutcomes,
            minimumSuccessRate: $this->minimumSuccessRate,
            minimumApproachRate: $this->minimumApproachRate,
            minimumAvgProgressScore: $this->minimumAvgProgressScore,
            supportingStats: $candidate,
            blockingReasons: [],
            reason: 'Policy crossed the evidence thresholds and is now a diagnostic candidate for future real activation.'
        );
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return string[]
     */
    private function blockingReasons(array $policy): array
    {
        $reasons = [];

        if (($policy['resolved_outcomes'] ?? 0) < $this->minimumResolvedOutcomes) {
            $reasons[] = 'Need more resolved shadow outcomes.';
        }

        if (($policy['success_rate'] ?? 0.0) < $this->minimumSuccessRate) {
            $reasons[] = 'Success rate is still below the activation threshold.';
        }

        if (($policy['approach_rate'] ?? 0.0) < $this->minimumApproachRate) {
            $reasons[] = 'Approximation rate is still below the activation threshold.';
        }

        if (($policy['avg_progress_score'] ?? 0.0) < $this->minimumAvgProgressScore) {
            $reasons[] = 'Average progress score is still below the activation threshold.';
        }

        return $reasons;
    }
}
