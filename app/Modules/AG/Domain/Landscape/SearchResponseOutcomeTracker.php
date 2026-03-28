<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseOutcomeTracker
{
    /**
     * @var SearchResponseAudit[]
     */
    private array $pendingAudits = [];

    /**
     * @var SearchResponseOutcome[]
     */
    private array $resolvedOutcomes = [];

    private int $maxResolvedOutcomes = 200;

    public function register(SearchResponseAudit $audit): void
    {
        if (! $audit->wouldTrigger) {
            return;
        }

        $this->pendingAudits[] = $audit;
    }

    /**
     * @return SearchResponseOutcome[]
     */
    public function resolveDue(int $generation, LandscapeObservation $observation): array
    {
        $resolved = [];
        $stillPending = [];

        foreach ($this->pendingAudits as $audit) {
            if ($generation < ($audit->auditGeneration + $audit->evaluationHorizonGenerations)) {
                $stillPending[] = $audit;

                continue;
            }

            $outcome = $this->evaluateOutcome($audit, $generation, $observation);
            $resolved[] = $outcome;
            $this->resolvedOutcomes[] = $outcome;

            if (count($this->resolvedOutcomes) > $this->maxResolvedOutcomes) {
                array_shift($this->resolvedOutcomes);
            }
        }

        $this->pendingAudits = $stillPending;

        return $resolved;
    }

    public function pendingCount(): int
    {
        return count($this->pendingAudits);
    }

    public function effectivenessReport(): SearchResponseEffectivenessReport
    {
        if ($this->resolvedOutcomes === []) {
            return new SearchResponseEffectivenessReport(
                totalResolvedOutcomes: 0,
                bestPolicyBySuccess: null,
                bestPolicyByProgress: null,
                policies: []
            );
        }

        $grouped = [];

        foreach ($this->resolvedOutcomes as $outcome) {
            $policy = $outcome->policy;

            if (! isset($grouped[$policy])) {
                $grouped[$policy] = [
                    'policy' => $policy,
                    'resolved_outcomes' => 0,
                    'targets_satisfied_count' => 0,
                    'approached_targets_count' => 0,
                    'avg_progress_score' => 0.0,
                    'success_rate' => 0.0,
                    'approach_rate' => 0.0,
                ];
            }

            $grouped[$policy]['resolved_outcomes']++;
            $grouped[$policy]['targets_satisfied_count'] += $outcome->targetsSatisfied ? 1 : 0;
            $grouped[$policy]['approached_targets_count'] += $outcome->approachedTargets ? 1 : 0;
            $grouped[$policy]['avg_progress_score'] += $outcome->progressScore;
        }

        foreach ($grouped as $policy => $stats) {
            $count = max(1, (int) $stats['resolved_outcomes']);
            $grouped[$policy]['avg_progress_score'] = round($stats['avg_progress_score'] / $count, 6);
            $grouped[$policy]['success_rate'] = round($stats['targets_satisfied_count'] / $count, 6);
            $grouped[$policy]['approach_rate'] = round($stats['approached_targets_count'] / $count, 6);
        }

        $policies = array_values($grouped);

        usort($policies, static function (array $left, array $right): int {
            return [$right['success_rate'], $right['avg_progress_score'], $right['resolved_outcomes']]
                <=> [$left['success_rate'], $left['avg_progress_score'], $left['resolved_outcomes']];
        });

        $bestPolicyBySuccess = $policies[0]['policy'] ?? null;

        $progressPolicies = $policies;

        usort($progressPolicies, static function (array $left, array $right): int {
            return [$right['avg_progress_score'], $right['approach_rate'], $right['resolved_outcomes']]
                <=> [$left['avg_progress_score'], $left['approach_rate'], $left['resolved_outcomes']];
        });

        return new SearchResponseEffectivenessReport(
            totalResolvedOutcomes: count($this->resolvedOutcomes),
            bestPolicyBySuccess: $bestPolicyBySuccess,
            bestPolicyByProgress: $progressPolicies[0]['policy'] ?? null,
            policies: $policies
        );
    }

    private function evaluateOutcome(
        SearchResponseAudit $audit,
        int $generation,
        LandscapeObservation $observation
    ): SearchResponseOutcome {
        $bestDeltaProgress = $this->increaseProgress(
            baseline: $audit->observedBestDeltaWindow,
            current: $observation->bestDeltaWindow,
            target: $audit->targetBestDeltaWindow
        );
        $turnoverProgress = $this->increaseProgress(
            baseline: $audit->observedPopulationTurnover,
            current: $observation->populationTurnover,
            target: $audit->targetPopulationTurnover
        );
        $eliteSimilarityProgress = $this->decreaseProgress(
            baseline: $audit->observedEliteSimilarity,
            current: $observation->eliteSimilarity,
            target: $audit->targetMaxEliteSimilarity
        );
        $basinLockProgress = $this->decreaseProgress(
            baseline: $audit->observedBasinLockConfidence,
            current: $observation->basinLockConfidence,
            target: $audit->targetMaxBasinLockConfidence
        );

        $progressValues = array_values(array_filter([
            $bestDeltaProgress,
            $turnoverProgress,
            $eliteSimilarityProgress,
            $basinLockProgress,
        ], static fn (?float $value): bool => $value !== null));

        $progressScore = $progressValues === []
            ? 0.0
            : array_sum($progressValues) / count($progressValues);

        $targetsSatisfied = $this->targetReached(
            current: $observation->bestDeltaWindow,
            target: $audit->targetBestDeltaWindow,
            direction: 'increase'
        ) && $this->targetReached(
            current: $observation->populationTurnover,
            target: $audit->targetPopulationTurnover,
            direction: 'increase'
        ) && $this->targetReached(
            current: $observation->eliteSimilarity,
            target: $audit->targetMaxEliteSimilarity,
            direction: 'decrease'
        ) && $this->targetReached(
            current: $observation->basinLockConfidence,
            target: $audit->targetMaxBasinLockConfidence,
            direction: 'decrease'
        );

        return new SearchResponseOutcome(
            policy: $audit->policy,
            auditGeneration: $audit->auditGeneration,
            resolvedGeneration: $generation,
            horizonGenerations: $audit->evaluationHorizonGenerations,
            shadowMode: $audit->shadowMode,
            wouldTrigger: $audit->wouldTrigger,
            targetsSatisfied: $targetsSatisfied,
            approachedTargets: $progressScore >= 0.5,
            progressScore: $progressScore,
            bestDeltaWindowProgress: $bestDeltaProgress,
            populationTurnoverProgress: $turnoverProgress,
            eliteSimilarityProgress: $eliteSimilarityProgress,
            basinLockConfidenceProgress: $basinLockProgress,
            observedBestDeltaWindow: $observation->bestDeltaWindow,
            observedPopulationTurnover: $observation->populationTurnover,
            observedEliteSimilarity: $observation->eliteSimilarity,
            observedBasinLockConfidence: $observation->basinLockConfidence,
            reason: $audit->reason
        );
    }

    private function increaseProgress(float $baseline, float $current, ?float $target): ?float
    {
        if ($target === null) {
            return null;
        }

        if ($target <= $baseline) {
            return $current >= $target ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, ($current - $baseline) / ($target - $baseline)));
    }

    private function decreaseProgress(float $baseline, float $current, ?float $target): ?float
    {
        if ($target === null) {
            return null;
        }

        if ($target >= $baseline) {
            return $current <= $target ? 1.0 : 0.0;
        }

        return max(0.0, min(1.0, ($baseline - $current) / ($baseline - $target)));
    }

    private function targetReached(float $current, ?float $target, string $direction): bool
    {
        if ($target === null) {
            return true;
        }

        return match ($direction) {
            'increase' => $current >= $target,
            'decrease' => $current <= $target,
            default => false,
        };
    }
}
