<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Landscape\LandscapeEpisode;
use App\Modules\AG\Domain\Landscape\LandscapeObservation;
use App\Modules\AG\Domain\Landscape\LandscapePhenomenon;
use App\Modules\AG\Domain\Landscape\LandscapeState;
use App\Modules\AG\Domain\Landscape\SearchResponseOutcomeTracker;
use App\Modules\AG\Domain\Landscape\SearchResponsePolicy;

it('resolves a shadow outcome after the audit horizon and measures target progress', function (): void {
    $policy = new SearchResponsePolicy;
    $tracker = new SearchResponseOutcomeTracker;

    $episode = LandscapeEpisode::start(
        phenomenon: LandscapePhenomenon::DeepValley,
        generation: 8,
        confidence: 0.86,
        depthScore: 0.89,
        populationTurnover: 0.10,
        eliteSimilarity: 0.91,
        bestSignatureChanged: false
    );

    $lockedObservation = new LandscapeObservation(
        phenomenon: LandscapePhenomenon::DeepValley,
        confidence: 0.86,
        bestDelta: 0.0,
        fitnessGap: 1.2,
        stagnation: 28,
        plateauDuration: 9,
        convergenceTrend: 0.72,
        depthScore: 0.89,
        bestDeltaWindow: -0.01,
        avgDeltaWindow: -0.008,
        improvementAcceptanceRate: 0.0,
        worseningAcceptanceRate: 0.70,
        populationTurnover: 0.10,
        bestSignatureChanged: false,
        eliteSimilarity: 0.91,
        diversity: 0.09,
        entropy: 0.12,
        basinLockConfidence: 0.82,
        basinLockDetected: true
    );

    $simulation = $policy->simulate($lockedObservation, $episode, LandscapeState::PrematureConvergence);
    $audit = $policy->audit($simulation, $lockedObservation, $episode, 8);
    $tracker->register($audit);

    expect($tracker->pendingCount())->toBe(1);

    $improvedObservation = new LandscapeObservation(
        phenomenon: LandscapePhenomenon::Neutral,
        confidence: 0.20,
        bestDelta: 0.11,
        fitnessGap: 0.7,
        stagnation: 4,
        plateauDuration: 0,
        convergenceTrend: 0.12,
        depthScore: 0.18,
        bestDeltaWindow: 0.032,
        avgDeltaWindow: 0.015,
        improvementAcceptanceRate: 0.48,
        worseningAcceptanceRate: 0.14,
        populationTurnover: 0.49,
        bestSignatureChanged: true,
        eliteSimilarity: 0.58,
        diversity: 0.51,
        entropy: 0.64,
        basinLockConfidence: 0.39,
        basinLockDetected: false
    );

    $outcomesBeforeHorizon = $tracker->resolveDue(13, $improvedObservation);
    $outcomesAtHorizon = $tracker->resolveDue(14, $improvedObservation);

    expect($outcomesBeforeHorizon)->toBeEmpty()
        ->and($outcomesAtHorizon)->toHaveCount(1)
        ->and($outcomesAtHorizon[0]->toArray())->toMatchArray([
            'policy' => 'basin_lock_escape',
            'audit_generation' => 8,
            'resolved_generation' => 14,
            'targets_satisfied' => true,
            'approached_targets' => true,
        ])
        ->and($outcomesAtHorizon[0]->toArray()['progress_score'] ?? 0.0)->toBeGreaterThan(0.9)
        ->and($tracker->pendingCount())->toBe(0);
});

it('aggregates effectiveness by policy across resolved shadow outcomes', function (): void {
    $policy = new SearchResponsePolicy;
    $tracker = new SearchResponseOutcomeTracker;

    $basinEpisode = LandscapeEpisode::start(
        phenomenon: LandscapePhenomenon::DeepValley,
        generation: 5,
        confidence: 0.85,
        depthScore: 0.88,
        populationTurnover: 0.10,
        eliteSimilarity: 0.90,
        bestSignatureChanged: false
    );

    $deepValleyEpisode = LandscapeEpisode::start(
        phenomenon: LandscapePhenomenon::DeepValley,
        generation: 9,
        confidence: 0.70,
        depthScore: 0.74,
        populationTurnover: 0.18,
        eliteSimilarity: 0.84,
        bestSignatureChanged: false
    );

    $lockedObservation = new LandscapeObservation(
        phenomenon: LandscapePhenomenon::DeepValley,
        confidence: 0.85,
        bestDelta: 0.0,
        fitnessGap: 1.0,
        stagnation: 24,
        plateauDuration: 8,
        convergenceTrend: 0.65,
        depthScore: 0.88,
        bestDeltaWindow: -0.01,
        avgDeltaWindow: -0.009,
        improvementAcceptanceRate: 0.0,
        worseningAcceptanceRate: 0.70,
        populationTurnover: 0.10,
        bestSignatureChanged: false,
        eliteSimilarity: 0.90,
        diversity: 0.10,
        entropy: 0.13,
        basinLockConfidence: 0.82,
        basinLockDetected: true
    );

    $recoveredObservation = new LandscapeObservation(
        phenomenon: LandscapePhenomenon::Neutral,
        confidence: 0.20,
        bestDelta: 0.09,
        fitnessGap: 0.5,
        stagnation: 3,
        plateauDuration: 0,
        convergenceTrend: 0.10,
        depthScore: 0.18,
        bestDeltaWindow: 0.04,
        avgDeltaWindow: 0.02,
        improvementAcceptanceRate: 0.42,
        worseningAcceptanceRate: 0.10,
        populationTurnover: 0.48,
        bestSignatureChanged: true,
        eliteSimilarity: 0.57,
        diversity: 0.52,
        entropy: 0.63,
        basinLockConfidence: 0.40,
        basinLockDetected: false
    );

    $basinSimulation = $policy->simulate($lockedObservation, $basinEpisode, LandscapeState::PrematureConvergence);
    $basinAudit = $policy->audit($basinSimulation, $lockedObservation, $basinEpisode, 5);
    $tracker->register($basinAudit);
    $tracker->resolveDue(11, $recoveredObservation);

    $deepValleyObservation = new LandscapeObservation(
        phenomenon: LandscapePhenomenon::DeepValley,
        confidence: 0.70,
        bestDelta: 0.0,
        fitnessGap: 0.9,
        stagnation: 18,
        plateauDuration: 6,
        convergenceTrend: 0.42,
        depthScore: 0.74,
        bestDeltaWindow: -0.006,
        avgDeltaWindow: -0.003,
        improvementAcceptanceRate: 0.06,
        worseningAcceptanceRate: 0.56,
        populationTurnover: 0.18,
        bestSignatureChanged: false,
        eliteSimilarity: 0.84,
        diversity: 0.18,
        entropy: 0.20,
        basinLockConfidence: 0.58,
        basinLockDetected: false
    );

    $partialRecoveryObservation = new LandscapeObservation(
        phenomenon: LandscapePhenomenon::Neutral,
        confidence: 0.35,
        bestDelta: 0.03,
        fitnessGap: 0.7,
        stagnation: 7,
        plateauDuration: 1,
        convergenceTrend: 0.18,
        depthScore: 0.28,
        bestDeltaWindow: 0.01,
        avgDeltaWindow: 0.006,
        improvementAcceptanceRate: 0.20,
        worseningAcceptanceRate: 0.22,
        populationTurnover: 0.29,
        bestSignatureChanged: true,
        eliteSimilarity: 0.73,
        diversity: 0.30,
        entropy: 0.38,
        basinLockConfidence: 0.54,
        basinLockDetected: false
    );

    $deepValleySimulation = $policy->simulate($deepValleyObservation, $deepValleyEpisode, LandscapeState::Exploitation);
    $deepValleyAudit = $policy->audit($deepValleySimulation, $deepValleyObservation, $deepValleyEpisode, 9);
    $tracker->register($deepValleyAudit);
    $tracker->resolveDue(13, $partialRecoveryObservation);

    $report = $tracker->effectivenessReport()->toArray();

    expect($report)->toMatchArray([
        'total_resolved_outcomes' => 2,
        'best_policy_by_success' => 'basin_lock_escape',
        'best_policy_by_progress' => 'basin_lock_escape',
    ])
        ->and($report['policies'])->toHaveCount(2)
        ->and($report['policies'][0]['policy'] ?? null)->toBe('basin_lock_escape')
        ->and($report['policies'][0]['success_rate'] ?? 0.0)->toBeGreaterThan(0.9)
        ->and($report['policies'][1]['policy'] ?? null)->toBe('deep_valley_probe');
});
