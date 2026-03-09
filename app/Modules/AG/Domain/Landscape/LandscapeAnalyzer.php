<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeAnalyzer
{
    public function analyze(LandscapeMetrics $metrics): array
    {
        $fitnessGap =
            $metrics->bestFitness - $metrics->avgFitness;

        $diversityLevel = $metrics->diversity;

        $entropyLevel = $metrics->entropy;

        return [
            'fitness_gap' => $fitnessGap,
            'diversity' => $diversityLevel,
            'entropy' => $entropyLevel,
            'variance' => $metrics->variance,
            'stagnation' => $metrics->stagnation
        ];
    }
}
