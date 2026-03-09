<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeEngine
{
    public function __construct(private LandscapeAnalyzer $analyzer, private LandscapeDetector $detector, private LandscapeResponseStrategy $strategy)
    {
    }

    public function evaluate(LandscapeMetrics $metrics): LandscapeResponse
    {

        $analysis = $this->analyzer->analyze($metrics);

        $state = $this->detector->detect($metrics);

        return $this->strategy->respond($state);
    }
}
