<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeEpisode
{
    public function __construct(
        public readonly LandscapePhenomenon $phenomenon,
        public readonly int $startGeneration,
        public readonly int $lastGeneration,
        public readonly int $duration,
        public readonly float $peakConfidence,
        public readonly float $peakDepthScore,
        public readonly float $avgPopulationTurnover,
        public readonly float $avgEliteSimilarity,
        public readonly float $stableBestSignatureRate,
        public readonly bool $active,
        public readonly LandscapeEpisodeExitMode $exitMode = LandscapeEpisodeExitMode::Active
    ) {}

    public static function start(
        LandscapePhenomenon $phenomenon,
        int $generation,
        float $confidence,
        float $depthScore,
        float $populationTurnover,
        float $eliteSimilarity,
        bool $bestSignatureChanged
    ): self {
        return new self(
            phenomenon: $phenomenon,
            startGeneration: $generation,
            lastGeneration: $generation,
            duration: 1,
            peakConfidence: $confidence,
            peakDepthScore: $depthScore,
            avgPopulationTurnover: $populationTurnover,
            avgEliteSimilarity: $eliteSimilarity,
            stableBestSignatureRate: $bestSignatureChanged ? 0.0 : 1.0,
            active: true
        );
    }

    public function advance(
        int $generation,
        float $confidence,
        float $depthScore,
        float $populationTurnover,
        float $eliteSimilarity,
        bool $bestSignatureChanged
    ): self {
        $nextDuration = $this->duration + 1;

        return new self(
            phenomenon: $this->phenomenon,
            startGeneration: $this->startGeneration,
            lastGeneration: $generation,
            duration: $nextDuration,
            peakConfidence: max($this->peakConfidence, $confidence),
            peakDepthScore: max($this->peakDepthScore, $depthScore),
            avgPopulationTurnover: (($this->avgPopulationTurnover * $this->duration) + $populationTurnover) / $nextDuration,
            avgEliteSimilarity: (($this->avgEliteSimilarity * $this->duration) + $eliteSimilarity) / $nextDuration,
            stableBestSignatureRate: (($this->stableBestSignatureRate * $this->duration) + ($bestSignatureChanged ? 0.0 : 1.0)) / $nextDuration,
            active: true
        );
    }

    public function close(LandscapeEpisodeExitMode $exitMode): self
    {
        return new self(
            phenomenon: $this->phenomenon,
            startGeneration: $this->startGeneration,
            lastGeneration: $this->lastGeneration,
            duration: $this->duration,
            peakConfidence: $this->peakConfidence,
            peakDepthScore: $this->peakDepthScore,
            avgPopulationTurnover: $this->avgPopulationTurnover,
            avgEliteSimilarity: $this->avgEliteSimilarity,
            stableBestSignatureRate: $this->stableBestSignatureRate,
            active: false,
            exitMode: $exitMode
        );
    }

    public function toArray(): array
    {
        return [
            'phenomenon' => $this->phenomenon->value,
            'start_generation' => $this->startGeneration,
            'last_generation' => $this->lastGeneration,
            'duration' => $this->duration,
            'peak_confidence' => round($this->peakConfidence, 6),
            'peak_depth_score' => round($this->peakDepthScore, 6),
            'avg_population_turnover' => round($this->avgPopulationTurnover, 6),
            'avg_elite_similarity' => round($this->avgEliteSimilarity, 6),
            'stable_best_signature_rate' => round($this->stableBestSignatureRate, 6),
            'active' => $this->active,
            'exit_mode' => $this->exitMode->value,
        ];
    }
}
