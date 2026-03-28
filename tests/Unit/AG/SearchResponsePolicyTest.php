<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Landscape\LandscapeEpisode;
use App\Modules\AG\Domain\Landscape\LandscapeObservation;
use App\Modules\AG\Domain\Landscape\LandscapePhenomenon;
use App\Modules\AG\Domain\Landscape\LandscapeState;
use App\Modules\AG\Domain\Landscape\SearchResponsePolicy;

it('simulates an escape response when basin-of-attraction lock is detected', function (): void {
    $policy = new SearchResponsePolicy;

    $episode = LandscapeEpisode::start(
        phenomenon: LandscapePhenomenon::DeepValley,
        generation: 12,
        confidence: 0.88,
        depthScore: 0.91,
        populationTurnover: 0.08,
        eliteSimilarity: 0.94,
        bestSignatureChanged: false
    )->advance(
        generation: 13,
        confidence: 0.90,
        depthScore: 0.93,
        populationTurnover: 0.05,
        eliteSimilarity: 0.95,
        bestSignatureChanged: false
    );

    $observation = new LandscapeObservation(
        phenomenon: LandscapePhenomenon::DeepValley,
        confidence: 0.90,
        bestDelta: 0.0,
        fitnessGap: 1.4,
        stagnation: 33,
        plateauDuration: 11,
        convergenceTrend: 0.82,
        depthScore: 0.93,
        bestDeltaWindow: 0.0,
        avgDeltaWindow: -0.01,
        improvementAcceptanceRate: 0.0,
        worseningAcceptanceRate: 0.74,
        populationTurnover: 0.06,
        bestSignatureChanged: false,
        eliteSimilarity: 0.95,
        diversity: 0.08,
        entropy: 0.11,
        basinLockConfidence: 0.84,
        basinLockDetected: true
    );

    $simulation = $policy->simulate($observation, $episode, LandscapeState::PrematureConvergence);
    $audit = $policy->audit($simulation, $observation, $episode, 13);

    expect($simulation->toArray())->toMatchArray([
        'policy' => 'basin_lock_escape',
        'simulated_only' => true,
        'would_escalate' => true,
        'target_state' => 'exploration',
        'activate_alns' => true,
    ])
        ->and($audit->toArray())->toMatchArray([
            'policy' => 'basin_lock_escape',
            'audit_generation' => 13,
            'shadow_mode' => true,
            'would_trigger' => true,
            'evaluation_horizon_generations' => 6,
            'target_best_delta_window' => 0.03,
            'target_max_basin_lock_confidence' => 0.45,
            'observed_population_turnover' => 0.06,
        ]);
});

it('keeps the simulation in observe-only mode when no escape response is recommended', function (): void {
    $policy = new SearchResponsePolicy;

    $episode = LandscapeEpisode::start(
        phenomenon: LandscapePhenomenon::Neutral,
        generation: 4,
        confidence: 0.12,
        depthScore: 0.08,
        populationTurnover: 0.61,
        eliteSimilarity: 0.24,
        bestSignatureChanged: true
    );

    $observation = new LandscapeObservation(
        phenomenon: LandscapePhenomenon::Neutral,
        confidence: 0.12,
        bestDelta: 0.42,
        fitnessGap: 0.6,
        stagnation: 1,
        plateauDuration: 0,
        convergenceTrend: 0.05,
        depthScore: 0.08,
        bestDeltaWindow: 0.18,
        avgDeltaWindow: 0.11,
        improvementAcceptanceRate: 0.52,
        worseningAcceptanceRate: 0.12,
        populationTurnover: 0.61,
        bestSignatureChanged: true,
        eliteSimilarity: 0.24,
        diversity: 0.57,
        entropy: 0.68
    );

    $simulation = $policy->simulate($observation, $episode, LandscapeState::Exploration);
    $audit = $policy->audit($simulation, $observation, $episode, 4);

    expect($simulation->toArray())->toMatchArray([
        'policy' => 'stability_hold',
        'simulated_only' => true,
        'would_escalate' => false,
        'target_state' => 'exploration',
        'activate_alns' => false,
    ])
        ->and($audit->toArray())->toMatchArray([
            'policy' => 'stability_hold',
            'audit_generation' => 4,
            'shadow_mode' => true,
            'would_trigger' => false,
            'evaluation_horizon_generations' => 3,
        ])
        ->and($audit->toArray()['target_best_delta_window'] ?? null)->toBeNull();
});
