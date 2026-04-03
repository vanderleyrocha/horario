<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeResponseStrategy
{
    public function respond(LandscapeState $state): LandscapeResponse
    {

        return match ($state) {

            LandscapeState::Exploration => new LandscapeResponse(state: $state, mutationMultiplier: 0.8, activateALNS: false, selectionPressureMultiplier: 1.0, diversificationBoost: 0.0),

            LandscapeState::Exploitation => new LandscapeResponse(state: $state, mutationMultiplier: 1.0, activateALNS: false, selectionPressureMultiplier: 1.2, diversificationBoost: 0.0),

            LandscapeState::Plateau => new LandscapeResponse(state: $state, mutationMultiplier: 1.5, activateALNS: true, selectionPressureMultiplier: 0.9, diversificationBoost: 0.2),

            LandscapeState::PrematureConvergence => new LandscapeResponse(state: $state, mutationMultiplier: 2.0, activateALNS: true, selectionPressureMultiplier: 0.8, diversificationBoost: 0.4),

            LandscapeState::Chaotic => new LandscapeResponse(state: $state, mutationMultiplier: 0.5, activateALNS: false, selectionPressureMultiplier: 1.3, diversificationBoost: 0.0),
        };

    }
}
