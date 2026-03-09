<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeDetector
{
    public function detect(LandscapeMetrics $metrics): LandscapeState
    {

        if ($metrics->stagnation > 40) {
            return LandscapeState::Plateau;
        }

        if ($metrics->diversity < 0.15) {
            return LandscapeState::PrematureConvergence;
        }

        if ($metrics->entropy < 0.2) {
            return LandscapeState::PrematureConvergence;
        }

        if ($metrics->variance > 100) {
            return LandscapeState::Chaotic;
        }

        if ($metrics->diversity > 0.5) {
            return LandscapeState::Exploration;
        }

        return LandscapeState::Exploitation;
    }
}
