<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeEngine
{
    private LandscapeHeatmapBuilder $heatmapBuilder;

    private ?LandscapeObservation $lastObservation = null;

    private ?LandscapeState $lastState = null;

    public function __construct(private LandscapeAnalyzer $analyzer, private LandscapeDetector $detector, private LandscapeResponseStrategy $strategy, private LandscapeMemory $memory)
    {
        $this->heatmapBuilder = new LandscapeHeatmapBuilder;
    }

    public function evaluate(LandscapeMetrics $metrics): LandscapeResponse
    {
        $state = $this->captureObservation($metrics);

        /*
        ----------------------------------------------------
        Detectar plateau persistente
        ----------------------------------------------------
        */

        if ($this->memory->plateauDuration() > 20) {

            return new LandscapeResponse(state: LandscapeState::Plateau, mutationMultiplier: 2.5, activateALNS: true, selectionPressureMultiplier: 0.7, diversificationBoost: 0.5);
        }

        /*
        ----------------------------------------------------
        Detectar convergência prematura
        ----------------------------------------------------
        */

        if ($this->memory->convergenceTrend() > 0.6) {

            return new LandscapeResponse(state: LandscapeState::PrematureConvergence, mutationMultiplier: 2.2, activateALNS: true, selectionPressureMultiplier: 0.7, diversificationBoost: 0.6);
        }

        return $this->strategy->respond($state);
    }

    public function observe(LandscapeMetrics $metrics): LandscapeObservation
    {
        $this->captureObservation($metrics);

        return $this->lastObservation
            ?? new LandscapeObservation(
                phenomenon: LandscapePhenomenon::Neutral,
                confidence: 0.0,
                bestDelta: 0.0,
                fitnessGap: 0.0,
                stagnation: $metrics->stagnation,
                plateauDuration: 0,
                convergenceTrend: 0.0,
                depthScore: 0.0,
                diversity: $metrics->diversity,
                entropy: $metrics->entropy
            );
    }

    public function observation(): ?LandscapeObservation
    {
        return $this->lastObservation;
    }

    public function state(): ?LandscapeState
    {
        return $this->lastState;
    }

    /*
    ----------------------------------------------------
    Heatmap para dashboard
    ----------------------------------------------------
    */

    public function heatmap(): array
    {
        return $this->heatmapBuilder->build($this->memory->points());
    }

    private function captureObservation(LandscapeMetrics $metrics): LandscapeState
    {
        $state = $this->detector->detect($metrics);

        $this->memory->recordPoint(new LandscapePoint(
            generation: $metrics->generation,
            fitness: $metrics->bestFitness,
            diversity: $metrics->diversity,
            entropy: $metrics->entropy
        ));

        $this->memory->record($state);

        $this->lastState = $state;
        $this->lastObservation = $this->analyzer->analyze($metrics, $this->memory, $state);

        return $state;
    }
}
