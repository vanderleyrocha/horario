<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeObservation
{
    public function __construct(
        public readonly LandscapePhenomenon $phenomenon,
        public readonly float $confidence,
        public readonly float $bestDelta,
        public readonly float $fitnessGap,
        public readonly int $stagnation,
        public readonly int $plateauDuration,
        public readonly float $convergenceTrend,
        public readonly float $depthScore,
        public readonly float $bestDeltaWindow,
        public readonly float $avgDeltaWindow,
        public readonly float $improvementAcceptanceRate,
        public readonly float $worseningAcceptanceRate,
        public readonly float $populationTurnover,
        public readonly bool $bestSignatureChanged,
        public readonly float $eliteSimilarity,
        public readonly float $diversity,
        public readonly float $entropy,
        public readonly ?array $currentEpisode = null,
        public readonly ?array $previousEpisode = null,
        public readonly array $recentEpisodeHistory = [],
        public readonly ?array $episodeTrend = null,
        public readonly float $basinLockConfidence = 0.0,
        public readonly bool $basinLockDetected = false,
        public readonly ?array $searchResponseSimulation = null,
        public readonly ?array $searchResponseAudit = null,
        public readonly ?array $searchResponseOutcome = null,
        public readonly int $searchResponsePendingAudits = 0,
        public readonly ?array $searchResponseEffectivenessReport = null,
        public readonly ?array $searchResponseActivationGate = null,
        public readonly ?array $searchResponseReadinessDashboard = null
    ) {}

    public function withEpisodeContext(
        ?array $currentEpisode,
        ?array $previousEpisode,
        array $recentEpisodeHistory,
        ?array $episodeTrend,
        float $basinLockConfidence,
        bool $basinLockDetected
    ): self {
        return new self(
            phenomenon: $this->phenomenon,
            confidence: $this->confidence,
            bestDelta: $this->bestDelta,
            fitnessGap: $this->fitnessGap,
            stagnation: $this->stagnation,
            plateauDuration: $this->plateauDuration,
            convergenceTrend: $this->convergenceTrend,
            depthScore: $this->depthScore,
            bestDeltaWindow: $this->bestDeltaWindow,
            avgDeltaWindow: $this->avgDeltaWindow,
            improvementAcceptanceRate: $this->improvementAcceptanceRate,
            worseningAcceptanceRate: $this->worseningAcceptanceRate,
            populationTurnover: $this->populationTurnover,
            bestSignatureChanged: $this->bestSignatureChanged,
            eliteSimilarity: $this->eliteSimilarity,
            diversity: $this->diversity,
            entropy: $this->entropy,
            currentEpisode: $currentEpisode,
            previousEpisode: $previousEpisode,
            recentEpisodeHistory: $recentEpisodeHistory,
            episodeTrend: $episodeTrend,
            basinLockConfidence: $basinLockConfidence,
            basinLockDetected: $basinLockDetected,
            searchResponseSimulation: $this->searchResponseSimulation,
            searchResponseAudit: $this->searchResponseAudit,
            searchResponseOutcome: $this->searchResponseOutcome,
            searchResponsePendingAudits: $this->searchResponsePendingAudits,
            searchResponseEffectivenessReport: $this->searchResponseEffectivenessReport,
            searchResponseActivationGate: $this->searchResponseActivationGate,
            searchResponseReadinessDashboard: $this->searchResponseReadinessDashboard
        );
    }

    public function withSearchResponseSimulation(?array $searchResponseSimulation): self
    {
        return new self(
            phenomenon: $this->phenomenon,
            confidence: $this->confidence,
            bestDelta: $this->bestDelta,
            fitnessGap: $this->fitnessGap,
            stagnation: $this->stagnation,
            plateauDuration: $this->plateauDuration,
            convergenceTrend: $this->convergenceTrend,
            depthScore: $this->depthScore,
            bestDeltaWindow: $this->bestDeltaWindow,
            avgDeltaWindow: $this->avgDeltaWindow,
            improvementAcceptanceRate: $this->improvementAcceptanceRate,
            worseningAcceptanceRate: $this->worseningAcceptanceRate,
            populationTurnover: $this->populationTurnover,
            bestSignatureChanged: $this->bestSignatureChanged,
            eliteSimilarity: $this->eliteSimilarity,
            diversity: $this->diversity,
            entropy: $this->entropy,
            currentEpisode: $this->currentEpisode,
            previousEpisode: $this->previousEpisode,
            recentEpisodeHistory: $this->recentEpisodeHistory,
            episodeTrend: $this->episodeTrend,
            basinLockConfidence: $this->basinLockConfidence,
            basinLockDetected: $this->basinLockDetected,
            searchResponseSimulation: $searchResponseSimulation,
            searchResponseAudit: $this->searchResponseAudit,
            searchResponseOutcome: $this->searchResponseOutcome,
            searchResponsePendingAudits: $this->searchResponsePendingAudits,
            searchResponseEffectivenessReport: $this->searchResponseEffectivenessReport,
            searchResponseActivationGate: $this->searchResponseActivationGate,
            searchResponseReadinessDashboard: $this->searchResponseReadinessDashboard
        );
    }

    public function withSearchResponseAudit(?array $searchResponseAudit): self
    {
        return new self(
            phenomenon: $this->phenomenon,
            confidence: $this->confidence,
            bestDelta: $this->bestDelta,
            fitnessGap: $this->fitnessGap,
            stagnation: $this->stagnation,
            plateauDuration: $this->plateauDuration,
            convergenceTrend: $this->convergenceTrend,
            depthScore: $this->depthScore,
            bestDeltaWindow: $this->bestDeltaWindow,
            avgDeltaWindow: $this->avgDeltaWindow,
            improvementAcceptanceRate: $this->improvementAcceptanceRate,
            worseningAcceptanceRate: $this->worseningAcceptanceRate,
            populationTurnover: $this->populationTurnover,
            bestSignatureChanged: $this->bestSignatureChanged,
            eliteSimilarity: $this->eliteSimilarity,
            diversity: $this->diversity,
            entropy: $this->entropy,
            currentEpisode: $this->currentEpisode,
            previousEpisode: $this->previousEpisode,
            recentEpisodeHistory: $this->recentEpisodeHistory,
            episodeTrend: $this->episodeTrend,
            basinLockConfidence: $this->basinLockConfidence,
            basinLockDetected: $this->basinLockDetected,
            searchResponseSimulation: $this->searchResponseSimulation,
            searchResponseAudit: $searchResponseAudit,
            searchResponseOutcome: $this->searchResponseOutcome,
            searchResponsePendingAudits: $this->searchResponsePendingAudits,
            searchResponseEffectivenessReport: $this->searchResponseEffectivenessReport,
            searchResponseActivationGate: $this->searchResponseActivationGate,
            searchResponseReadinessDashboard: $this->searchResponseReadinessDashboard
        );
    }

    public function withSearchResponseOutcome(
        ?array $searchResponseOutcome,
        int $searchResponsePendingAudits,
        ?array $searchResponseEffectivenessReport = null,
        ?array $searchResponseActivationGate = null,
        ?array $searchResponseReadinessDashboard = null
    ): self {
        return new self(
            phenomenon: $this->phenomenon,
            confidence: $this->confidence,
            bestDelta: $this->bestDelta,
            fitnessGap: $this->fitnessGap,
            stagnation: $this->stagnation,
            plateauDuration: $this->plateauDuration,
            convergenceTrend: $this->convergenceTrend,
            depthScore: $this->depthScore,
            bestDeltaWindow: $this->bestDeltaWindow,
            avgDeltaWindow: $this->avgDeltaWindow,
            improvementAcceptanceRate: $this->improvementAcceptanceRate,
            worseningAcceptanceRate: $this->worseningAcceptanceRate,
            populationTurnover: $this->populationTurnover,
            bestSignatureChanged: $this->bestSignatureChanged,
            eliteSimilarity: $this->eliteSimilarity,
            diversity: $this->diversity,
            entropy: $this->entropy,
            currentEpisode: $this->currentEpisode,
            previousEpisode: $this->previousEpisode,
            recentEpisodeHistory: $this->recentEpisodeHistory,
            episodeTrend: $this->episodeTrend,
            basinLockConfidence: $this->basinLockConfidence,
            basinLockDetected: $this->basinLockDetected,
            searchResponseSimulation: $this->searchResponseSimulation,
            searchResponseAudit: $this->searchResponseAudit,
            searchResponseOutcome: $searchResponseOutcome,
            searchResponsePendingAudits: $searchResponsePendingAudits,
            searchResponseEffectivenessReport: $searchResponseEffectivenessReport ?? $this->searchResponseEffectivenessReport,
            searchResponseActivationGate: $searchResponseActivationGate ?? $this->searchResponseActivationGate,
            searchResponseReadinessDashboard: $searchResponseReadinessDashboard ?? $this->searchResponseReadinessDashboard
        );
    }

    public function toArray(): array
    {
        return [
            'phenomenon' => $this->phenomenon->value,
            'confidence' => $this->confidence,
            'best_delta' => $this->bestDelta,
            'fitness_gap' => $this->fitnessGap,
            'stagnation' => $this->stagnation,
            'plateau_duration' => $this->plateauDuration,
            'convergence_trend' => $this->convergenceTrend,
            'depth_score' => $this->depthScore,
            'best_delta_window' => $this->bestDeltaWindow,
            'avg_delta_window' => $this->avgDeltaWindow,
            'improvement_acceptance_rate' => $this->improvementAcceptanceRate,
            'worsening_acceptance_rate' => $this->worseningAcceptanceRate,
            'population_turnover' => $this->populationTurnover,
            'best_signature_changed' => $this->bestSignatureChanged,
            'elite_similarity' => $this->eliteSimilarity,
            'diversity' => $this->diversity,
            'entropy' => $this->entropy,
            'current_episode' => $this->currentEpisode,
            'previous_episode' => $this->previousEpisode,
            'recent_episode_history' => $this->recentEpisodeHistory,
            'episode_trend' => $this->episodeTrend,
            'basin_of_attraction_lock_confidence' => $this->basinLockConfidence,
            'basin_of_attraction_lock_detected' => $this->basinLockDetected,
            'search_response_simulation' => $this->searchResponseSimulation,
            'search_response_audit' => $this->searchResponseAudit,
            'search_response_outcome' => $this->searchResponseOutcome,
            'search_response_pending_audits' => $this->searchResponsePendingAudits,
            'search_response_effectiveness_report' => $this->searchResponseEffectivenessReport,
            'search_response_activation_gate' => $this->searchResponseActivationGate,
            'search_response_readiness_dashboard' => $this->searchResponseReadinessDashboard,
        ];
    }
}
