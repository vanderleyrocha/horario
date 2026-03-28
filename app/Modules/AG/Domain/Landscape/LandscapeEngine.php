<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeEngine
{
    private LandscapeHeatmapBuilder $heatmapBuilder;

    private ?LandscapeObservation $lastObservation = null;

    private ?LandscapeState $lastState = null;

    public function __construct(
        private LandscapeAnalyzer $analyzer,
        private LandscapeDetector $detector,
        private LandscapeResponseStrategy $strategy,
        private LandscapeMemory $memory,
        private ?SearchResponsePolicy $searchResponsePolicy = null,
        private ?SearchResponseOutcomeTracker $searchResponseOutcomeTracker = null
    ) {
        $this->heatmapBuilder = new LandscapeHeatmapBuilder;
        $this->searchResponsePolicy ??= new SearchResponsePolicy;
        $this->searchResponseOutcomeTracker ??= new SearchResponseOutcomeTracker;
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
                bestDeltaWindow: 0.0,
                avgDeltaWindow: 0.0,
                improvementAcceptanceRate: 0.0,
                worseningAcceptanceRate: 0.0,
                populationTurnover: 0.0,
                bestSignatureChanged: false,
                eliteSimilarity: 0.0,
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
        $this->memory->recordTrajectory(new LandscapeTrajectorySnapshot(
            generation: $metrics->generation,
            bestFitness: $metrics->bestFitness,
            avgFitness: $metrics->avgFitness,
            bestSignature: $metrics->bestSignature,
            improvementAcceptanceRate: $metrics->improvementAcceptanceRate,
            worseningAcceptanceRate: $metrics->worseningAcceptanceRate,
            populationTurnover: $metrics->populationTurnover,
            bestSignatureChanged: $metrics->bestSignatureChanged,
            eliteSimilarity: $metrics->eliteSimilarity
        ));

        $this->lastState = $state;
        $this->lastObservation = $this->analyzer->analyze($metrics, $this->memory, $state);

        $currentEpisode = $this->memory->recordEpisode(
            phenomenon: $this->lastObservation->phenomenon,
            generation: $metrics->generation,
            confidence: $this->lastObservation->confidence,
            depthScore: $this->lastObservation->depthScore,
            populationTurnover: $metrics->populationTurnover,
            eliteSimilarity: $metrics->eliteSimilarity,
            bestSignatureChanged: $metrics->bestSignatureChanged
        );

        $this->lastObservation = $this->lastObservation->withEpisodeContext(
            currentEpisode: $currentEpisode->toArray(),
            previousEpisode: $this->memory->lastCompletedEpisode()?->toArray(),
            basinLockConfidence: $this->lastObservation->basinLockConfidence,
            basinLockDetected: $this->lastObservation->basinLockDetected
        );

        $simulation = $this->searchResponsePolicy?->simulate(
            observation: $this->lastObservation,
            episode: $currentEpisode,
            currentState: $state
        );

        $audit = $simulation !== null
            ? $this->searchResponsePolicy?->audit(
                simulation: $simulation,
                observation: $this->lastObservation,
                episode: $currentEpisode,
                generation: $metrics->generation
            )
            : null;

        $resolvedOutcomes = $this->searchResponseOutcomeTracker?->resolveDue(
            generation: $metrics->generation,
            observation: $this->lastObservation
        ) ?? [];

        if ($audit !== null) {
            $this->searchResponseOutcomeTracker?->register($audit);
        }

        $this->lastObservation = $this->lastObservation->withSearchResponseSimulation(
            $simulation?->toArray()
        );

        $this->lastObservation = $this->lastObservation->withSearchResponseAudit(
            $audit?->toArray()
        );

        $latestOutcome = $resolvedOutcomes === []
            ? null
            : $resolvedOutcomes[array_key_last($resolvedOutcomes)]->toArray();
        $effectivenessReport = $this->searchResponseOutcomeTracker?->effectivenessReport()?->toArray();

        $this->lastObservation = $this->lastObservation->withSearchResponseOutcome(
            searchResponseOutcome: $latestOutcome,
            searchResponsePendingAudits: $this->searchResponseOutcomeTracker?->pendingCount() ?? 0,
            searchResponseEffectivenessReport: $effectivenessReport
        );

        return $state;
    }
}
