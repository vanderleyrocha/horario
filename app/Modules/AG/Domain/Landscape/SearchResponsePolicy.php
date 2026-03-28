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
}
