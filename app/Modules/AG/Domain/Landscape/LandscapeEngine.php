<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeEngine
{
    private LandscapeHeatmapBuilder $heatmapBuilder;

    public function __construct(private LandscapeAnalyzer $analyzer, private LandscapeDetector $detector, private LandscapeResponseStrategy $strategy, private LandscapeMemory $memory)
    {
        $this->heatmapBuilder = new LandscapeHeatmapBuilder();
    }

    public function evaluate(LandscapeMetrics $metrics): LandscapeResponse
    {
        $analysis = $this->analyzer->analyze($metrics);

        $state = $this->detector->detect($metrics);

        /*
        ----------------------------------------------------
        Registrar ponto no landscape
        ----------------------------------------------------
        */

        $this->memory->recordPoint(new LandscapePoint(generation: $metrics->generation, fitness: $metrics->bestFitness, diversity: $metrics->diversity, entropy: $metrics->entropy));

        /*
        ----------------------------------------------------
        Registrar estado
        ----------------------------------------------------
        */

        $this->memory->record($state);

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

    /*
    ----------------------------------------------------
    Heatmap para dashboard
    ----------------------------------------------------
    */

    public function heatmap(): array
    {
        return $this->heatmapBuilder->build($this->memory->points());
    }
}
