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
        private ?SearchResponseOutcomeTracker $searchResponseOutcomeTracker = null,
        private ?SearchResponseActivationGate $searchResponseActivationGate = null,
        private ?SearchResponseReadinessDashboardBuilder $searchResponseReadinessDashboardBuilder = null
    ) {
        $this->heatmapBuilder = new LandscapeHeatmapBuilder;
        $this->searchResponsePolicy ??= new SearchResponsePolicy;
        $this->searchResponseOutcomeTracker ??= new SearchResponseOutcomeTracker;
        $this->searchResponseActivationGate ??= new SearchResponseActivationGate;
        $this->searchResponseReadinessDashboardBuilder ??= new SearchResponseReadinessDashboardBuilder;
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
            recentEpisodeHistory: $this->memory->recentCompletedEpisodes(),
            episodeTrend: $this->resolveEpisodeTrend(
                recentEpisodeHistory: $this->memory->recentCompletedEpisodes(),
                currentEpisode: $currentEpisode->toArray(),
                previousEpisode: $this->memory->lastCompletedEpisode()?->toArray(),
            ),
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
        $effectivenessReportObject = $this->searchResponseOutcomeTracker?->effectivenessReport();
        $effectivenessReport = $effectivenessReportObject?->toArray();
        $activationGate = $effectivenessReportObject !== null
            ? $this->searchResponseActivationGate?->evaluate($effectivenessReportObject)?->toArray()
            : null;
        $readinessDashboard = $this->searchResponseReadinessDashboardBuilder?->build(
            effectivenessReport: $effectivenessReport,
            activationGate: $activationGate,
            latestOutcome: $latestOutcome,
            pendingAudits: $this->searchResponseOutcomeTracker?->pendingCount() ?? 0
        )?->toArray();

        $this->lastObservation = $this->lastObservation->withSearchResponseOutcome(
            searchResponseOutcome: $latestOutcome,
            searchResponsePendingAudits: $this->searchResponseOutcomeTracker?->pendingCount() ?? 0,
            searchResponseEffectivenessReport: $effectivenessReport,
            searchResponseActivationGate: $activationGate,
            searchResponseReadinessDashboard: $readinessDashboard
        );

        return $state;
    }

    /**
     * @param  array<int, array<string, mixed>>  $recentEpisodeHistory
     * @param  array<string, mixed>|null  $currentEpisode
     * @param  array<string, mixed>|null  $previousEpisode
     * @return array<string, mixed>
     */
    private function resolveEpisodeTrend(array $recentEpisodeHistory, ?array $currentEpisode, ?array $previousEpisode): array
    {
        $completedHistory = array_values(array_filter(
            $recentEpisodeHistory,
            static fn (mixed $episode): bool => is_array($episode)
        ));

        $trendSequence = array_map(
            static fn (array $episode): string => (string) ($episode['phenomenon'] ?? 'neutral'),
            $completedHistory
        );

        $currentPhenomenon = (string) ($currentEpisode['phenomenon'] ?? '');
        $previousPhenomenon = (string) ($previousEpisode['phenomenon'] ?? '');
        $previousExitMode = (string) ($previousEpisode['exit_mode'] ?? '');

        if ($currentPhenomenon !== '' && $currentPhenomenon !== LandscapePhenomenon::Neutral->value) {
            $trendSequence[] = $currentPhenomenon;
        }

        $normalizedSequence = array_slice($trendSequence, -3);
        $severitySequence = array_map(
            fn (string $phenomenon): int => $this->episodeTrendSeverity($phenomenon),
            $normalizedSequence
        );

        if (
            count($normalizedSequence) >= 3 &&
            $severitySequence[0] < $severitySequence[1] &&
            $severitySequence[1] < $severitySequence[2]
        ) {
            return [
                'direction' => 'worsening',
                'strength' => 'strong',
                'headline' => 'Tendencia de piora',
                'detail' => 'Sequencia recente em agravamento do landscape.',
                'sequence' => $normalizedSequence,
            ];
        }

        if (
            count($completedHistory) >= 2 &&
            $previousPhenomenon !== '' &&
            $previousExitMode === LandscapeEpisodeExitMode::Recovered->value
        ) {
            $recoveringSequence = [
                (string) ($completedHistory[count($completedHistory) - 2]['phenomenon'] ?? ''),
                $previousPhenomenon,
                'recovered',
            ];

            return [
                'direction' => 'improving',
                'strength' => 'strong',
                'headline' => 'Tendencia de melhora',
                'detail' => 'Sequencia recente em recuperacao do landscape.',
                'sequence' => array_values(array_filter($recoveringSequence, static fn (string $item): bool => $item !== '')),
            ];
        }

        $lastSeverity = $severitySequence === [] ? null : $severitySequence[array_key_last($severitySequence)];
        $previousSeverity = count($severitySequence) < 2 ? null : $severitySequence[count($severitySequence) - 2];

        if ($lastSeverity !== null && $previousSeverity !== null && $lastSeverity > $previousSeverity) {
            return [
                'direction' => 'worsening',
                'strength' => 'moderate',
                'headline' => 'Sinal de piora',
                'detail' => 'A sequencia recente aumentou a intensidade do landscape.',
                'sequence' => $normalizedSequence,
            ];
        }

        if ($lastSeverity !== null && $previousSeverity !== null && $lastSeverity < $previousSeverity) {
            return [
                'direction' => 'improving',
                'strength' => 'moderate',
                'headline' => 'Sinal de melhora',
                'detail' => 'A sequencia recente reduziu a intensidade do landscape.',
                'sequence' => $normalizedSequence,
            ];
        }

        return [
            'direction' => 'indeterminate',
            'strength' => 'none',
            'headline' => 'Tendencia indefinida',
            'detail' => 'Ainda nao ha sequencia suficiente para inferir direcao entre episodios.',
            'sequence' => $normalizedSequence,
        ];
    }

    private function episodeTrendSeverity(string $phenomenon): int
    {
        return match ($phenomenon) {
            LandscapePhenomenon::Plateau->value => 1,
            LandscapePhenomenon::LocalMinimum->value => 2,
            LandscapePhenomenon::DeepValley->value => 3,
            default => 0,
        };
    }
}
