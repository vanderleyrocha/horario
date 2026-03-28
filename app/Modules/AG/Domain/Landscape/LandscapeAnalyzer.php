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
        $bestDeltaWindow = $memory->bestDeltaWindow(6);
        $avgDeltaWindow = $memory->avgDeltaWindow(6);
        $plateauDuration = $memory->plateauDuration();
        $convergenceTrend = $memory->convergenceTrend();
        $stagnationScore = $this->normalize($metrics->stagnation, 20.0);
        $plateauScore = $this->normalize($plateauDuration, 12.0);
        $lowDiversityScore = 1.0 - min(1.0, $metrics->diversity);
        $lowEntropyScore = 1.0 - min(1.0, $metrics->entropy);
        $flatSlopeScore = 1.0 - min(1.0, abs($memory->recentFitnessSlope(6)));
        $lowAcceptanceScore = 1.0 - min(1.0, $metrics->improvementAcceptanceRate);
        $turnoverStallScore = 1.0 - min(1.0, $metrics->populationTurnover);
        $depthScore = min(1.0, (
            ($stagnationScore * 0.22) +
            ($plateauScore * 0.16) +
            ($lowDiversityScore * 0.14) +
            ($lowEntropyScore * 0.14) +
            ($convergenceTrend * 0.08) +
            ($flatSlopeScore * 0.06) +
            ($lowAcceptanceScore * 0.10) +
            ($turnoverStallScore * 0.06) +
            ((1.0 - min(1.0, abs($bestDeltaWindow))) * 0.04)
        ));

        $phenomenon = LandscapePhenomenon::Neutral;
        $confidence = max(0.05, $depthScore * 0.5);

        if (
            $metrics->stagnation >= 14 &&
            $plateauDuration >= 8 &&
            $metrics->diversity <= 0.18 &&
            $metrics->entropy <= 0.22 &&
            $metrics->populationTurnover <= 0.25 &&
            $metrics->eliteSimilarity >= 0.75
        ) {
            $phenomenon = LandscapePhenomenon::DeepValley;
            $confidence = max(0.70, $depthScore);
        } elseif (
            $metrics->stagnation >= 8 &&
            $metrics->diversity <= 0.25 &&
            $metrics->entropy <= 0.30 &&
            abs($bestDeltaWindow) <= 0.01 &&
            $metrics->bestSignatureChanged === false
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
            bestDeltaWindow: round($bestDeltaWindow, 6),
            avgDeltaWindow: round($avgDeltaWindow, 6),
            improvementAcceptanceRate: round($metrics->improvementAcceptanceRate, 6),
            worseningAcceptanceRate: round($metrics->worseningAcceptanceRate, 6),
            populationTurnover: round($metrics->populationTurnover, 6),
            bestSignatureChanged: $metrics->bestSignatureChanged,
            eliteSimilarity: round($metrics->eliteSimilarity, 6),
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
