<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponsePolicy
{
    public function simulate(LandscapeObservation $observation, ?LandscapeEpisode $episode, LandscapeState $currentState): SearchResponseSimulation
    {
        if (
            $observation->basinLockDetected &&
            $episode !== null &&
            $episode->duration >= 1
        ) {
            return new SearchResponseSimulation(policy: 'basin_lock_escape', simulatedOnly: true, wouldEscalate: true, targetState: LandscapeState::Exploration, mutationMultiplier: 3.0, activateALNS: true, selectionPressureMultiplier: 0.65, diversificationBoost: 0.85, reason: 'Persistent basin-of-attraction lock with stable elite signature and low turnover.');
        }

        if (
            $observation->phenomenon === LandscapePhenomenon::DeepValley &&
            $episode !== null &&
            $episode->duration >= 1
        ) {
            return new SearchResponseSimulation(policy: 'deep_valley_probe', simulatedOnly: true, wouldEscalate: true, targetState: LandscapeState::Exploration, mutationMultiplier: 2.4, activateALNS: true, selectionPressureMultiplier: 0.75, diversificationBoost: 0.55, reason: 'Deep-valley episode suggests broader exploration, but still below basin-lock certainty.');
        }

        return new SearchResponseSimulation(policy: 'stability_hold', simulatedOnly: true, wouldEscalate: false, targetState: $currentState, mutationMultiplier: 1.0, activateALNS: false, selectionPressureMultiplier: 1.0, diversificationBoost: 0.0, reason: 'Observation remains informative only; no simulated escape response is recommended yet.');
    }

    public function audit(
        SearchResponseSimulation $simulation,
        LandscapeObservation $observation,
        ?LandscapeEpisode $episode,
        int $generation
    ): SearchResponseAudit {
        [$targetBestDeltaWindow, $targetPopulationTurnover, $targetMaxEliteSimilarity, $targetMaxBasinLockConfidence, $horizon] = match ($simulation->policy) {
            'basin_lock_escape' => [
                0.03,
                max(0.45, $observation->populationTurnover + 0.27),
                min(0.65, max(0.35, $observation->eliteSimilarity - 0.20)),
                0.45,
                6,
            ],
            'deep_valley_probe' => [
                0.015,
                max(0.35, $observation->populationTurnover + 0.18),
                min(0.75, max(0.45, $observation->eliteSimilarity - 0.10)),
                0.60,
                4,
            ],
            default => [null, null, null, null, 3],
        };

        return new SearchResponseAudit(
            policy: $simulation->policy,
            auditGeneration: $generation,
            shadowMode: true,
            wouldTrigger: $simulation->wouldEscalate,
            evaluationHorizonGenerations: $horizon,
            targetBestDeltaWindow: $targetBestDeltaWindow,
            targetPopulationTurnover: $targetPopulationTurnover,
            targetMaxEliteSimilarity: $targetMaxEliteSimilarity,
            targetMaxBasinLockConfidence: $targetMaxBasinLockConfidence,
            observedBestDeltaWindow: $observation->bestDeltaWindow,
            observedPopulationTurnover: $observation->populationTurnover,
            observedEliteSimilarity: $observation->eliteSimilarity,
            observedBasinLockConfidence: $observation->basinLockConfidence,
            bestDeltaWindowGap: $targetBestDeltaWindow !== null
                ? $targetBestDeltaWindow - $observation->bestDeltaWindow
                : null,
            populationTurnoverGap: $targetPopulationTurnover !== null
                ? $targetPopulationTurnover - $observation->populationTurnover
                : null,
            eliteSimilarityReductionNeeded: $targetMaxEliteSimilarity !== null
                ? max(0.0, $observation->eliteSimilarity - $targetMaxEliteSimilarity)
                : null,
            basinLockConfidenceReductionNeeded: $targetMaxBasinLockConfidence !== null
                ? max(0.0, $observation->basinLockConfidence - $targetMaxBasinLockConfidence)
                : null,
            reason: $episode !== null
                ? $simulation->reason.' Episode duration='.$episode->duration
                : $simulation->reason
        );
    }
}
