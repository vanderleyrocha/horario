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
