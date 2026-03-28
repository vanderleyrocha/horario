<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeAnalyzer
{
    public function analyze(
        LandscapeMetrics $metrics,
        LandscapeMemory $memory,
        LandscapeState $state
    ): LandscapeObservation {
        $fitnessGap = max(0.0, $metrics->bestFitness - $metrics->avgFitness);
        $bestDelta = $memory->lastFitnessDelta();
        $plateauDuration = $memory->plateauDuration();
        $convergenceTrend = $memory->convergenceTrend();
        $stagnationScore = $this->normalize($metrics->stagnation, 20.0);
        $plateauScore = $this->normalize($plateauDuration, 12.0);
        $lowDiversityScore = 1.0 - min(1.0, $metrics->diversity);
        $lowEntropyScore = 1.0 - min(1.0, $metrics->entropy);
        $flatSlopeScore = 1.0 - min(1.0, abs($memory->recentFitnessSlope(6)));
        $depthScore = min(1.0, (
            ($stagnationScore * 0.25) +
            ($plateauScore * 0.20) +
            ($lowDiversityScore * 0.20) +
            ($lowEntropyScore * 0.20) +
            ($convergenceTrend * 0.10) +
            ($flatSlopeScore * 0.05)
        ));

        $phenomenon = LandscapePhenomenon::Neutral;
        $confidence = max(0.05, $depthScore * 0.5);

        if (
            $metrics->stagnation >= 14 &&
            $plateauDuration >= 8 &&
            $metrics->diversity <= 0.18 &&
            $metrics->entropy <= 0.22
        ) {
            $phenomenon = LandscapePhenomenon::DeepValley;
            $confidence = max(0.70, $depthScore);
        } elseif (
            $metrics->stagnation >= 8 &&
            $metrics->diversity <= 0.25 &&
            $metrics->entropy <= 0.30 &&
            abs($bestDelta) <= 0.001
        ) {
            $phenomenon = LandscapePhenomenon::LocalMinimum;
            $confidence = max(0.55, $depthScore * 0.85);
        } elseif ($state === LandscapeState::Plateau || $plateauDuration >= 5) {
            $phenomenon = LandscapePhenomenon::Plateau;
            $confidence = max(0.45, $plateauScore);
        }

        return new LandscapeObservation(
            phenomenon: $phenomenon,
            confidence: round($confidence, 4),
            bestDelta: round($bestDelta, 6),
            fitnessGap: round($fitnessGap, 6),
            stagnation: $metrics->stagnation,
            plateauDuration: $plateauDuration,
            convergenceTrend: round($convergenceTrend, 6),
            depthScore: round($depthScore, 6),
            diversity: round($metrics->diversity, 6),
            entropy: round($metrics->entropy, 6)
        );
    }

    private function normalize(float $value, float $pivot): float
    {
        if ($pivot <= 0.0) {
            return 0.0;
        }

        return min(1.0, max(0.0, $value / $pivot));
    }
}
