<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseOutcomeTracker
{
    /**
     * @var SearchResponseAudit[]
     */
    private array $pendingAudits = [];

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

            $resolved[] = $this->evaluateOutcome($audit, $generation, $observation);
        }

        $this->pendingAudits = $stillPending;

        return $resolved;
    }

    public function pendingCount(): int
    {
        return count($this->pendingAudits);
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
