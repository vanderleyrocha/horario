<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Landscape\LandscapeAnalyzer;
use App\Modules\AG\Domain\Landscape\LandscapeDetector;
use App\Modules\AG\Domain\Landscape\LandscapeEngine;
use App\Modules\AG\Domain\Landscape\LandscapeMemory;
use App\Modules\AG\Domain\Landscape\LandscapeMetrics;
use App\Modules\AG\Domain\Landscape\LandscapePhenomenon;
use App\Modules\AG\Domain\Landscape\LandscapeResponseStrategy;

it('observes a local minimum when progress stalls under low diversity and entropy', function (): void {
    $engine = new LandscapeEngine(
        new LandscapeAnalyzer,
        new LandscapeDetector,
        new LandscapeResponseStrategy,
        new LandscapeMemory
    );

    $engine->observe(new LandscapeMetrics(
        generation: 1,
        bestFitness: 80.0,
        avgFitness: 78.0,
        variance: 0.5,
        diversity: 0.22,
        entropy: 0.28,
        stagnation: 8,
        improvementAcceptanceRate: 0.10,
        worseningAcceptanceRate: 0.50,
        populationTurnover: 0.20,
        bestSignatureChanged: false,
        eliteSimilarity: 0.80,
        bestSignature: 'elite-A'
    ));

    $observation = $engine->observe(new LandscapeMetrics(
        generation: 2,
        bestFitness: 80.0,
        avgFitness: 78.4,
        variance: 0.4,
        diversity: 0.20,
        entropy: 0.25,
        stagnation: 10,
        improvementAcceptanceRate: 0.00,
        worseningAcceptanceRate: 0.60,
        populationTurnover: 0.10,
        bestSignatureChanged: false,
        eliteSimilarity: 0.80,
        bestSignature: 'elite-A'
    ));

    expect($observation->phenomenon)->toBe(LandscapePhenomenon::LocalMinimum)
        ->and($observation->toArray())->toMatchArray([
            'phenomenon' => 'local_minimum',
            'stagnation' => 10,
            'best_delta_window' => 0.0,
            'avg_delta_window' => 0.4,
            'improvement_acceptance_rate' => 0.0,
            'worsening_acceptance_rate' => 0.6,
            'population_turnover' => 0.1,
            'best_signature_changed' => false,
            'elite_similarity' => 0.8,
        ])
        ->and($observation->currentEpisode)->toBeArray()
        ->and($observation->currentEpisode['duration'] ?? null)->toBe(2)
        ->and($observation->basinLockDetected)->toBeFalse();
});

it('observes a deep valley after persistent plateau and convergence pressure', function (): void {
    $engine = new LandscapeEngine(
        new LandscapeAnalyzer,
        new LandscapeDetector,
        new LandscapeResponseStrategy,
        new LandscapeMemory
    );

    $observation = null;

    for ($generation = 1; $generation <= 8; $generation++) {
        $observation = $engine->observe(new LandscapeMetrics(
            generation: $generation,
            bestFitness: 91.0,
            avgFitness: 89.5,
            variance: 0.15,
            diversity: 0.10,
            entropy: 0.14,
            stagnation: 45,
            improvementAcceptanceRate: 0.0,
            worseningAcceptanceRate: 0.85,
            populationTurnover: 0.10,
            bestSignatureChanged: false,
            eliteSimilarity: 0.90,
            bestSignature: 'locked-basin'
        ));
    }

    expect($observation)->not->toBeNull()
        ->and($observation->phenomenon)->toBe(LandscapePhenomenon::DeepValley)
        ->and($observation->depthScore)->toBeGreaterThan(0.70)
        ->and($observation->plateauDuration)->toBeGreaterThanOrEqual(8)
        ->and($observation->toArray())->toMatchArray([
            'population_turnover' => 0.1,
            'elite_similarity' => 0.9,
            'best_signature_changed' => false,
            'basin_of_attraction_lock_detected' => true,
        ])
        ->and($observation->currentEpisode)->toBeArray()
        ->and($observation->currentEpisode['phenomenon'] ?? null)->toBe('deep_valley')
        ->and($observation->currentEpisode['duration'] ?? null)->toBe(1)
        ->and($observation->currentEpisode['peak_depth_score'] ?? null)->toBeGreaterThan(0.7)
        ->and($observation->previousEpisode)->toBeArray()
        ->and($observation->previousEpisode['phenomenon'] ?? null)->toBe('local_minimum')
        ->and($observation->previousEpisode['exit_mode'] ?? null)->toBe('phenomenon_shift')
        ->and($observation->basinLockConfidence)->toBeGreaterThanOrEqual(0.75)
        ->and($observation->searchResponseSimulation)->toBeArray()
        ->and($observation->searchResponseSimulation['policy'] ?? null)->toBe('basin_lock_escape')
        ->and($observation->searchResponseSimulation['would_escalate'] ?? null)->toBeTrue();
});

it('closes the previous episode with recovered exit mode when the landscape returns to neutral', function (): void {
    $engine = new LandscapeEngine(
        new LandscapeAnalyzer,
        new LandscapeDetector,
        new LandscapeResponseStrategy,
        new LandscapeMemory
    );

    $engine->observe(new LandscapeMetrics(
        generation: 1,
        bestFitness: 84.0,
        avgFitness: 82.0,
        variance: 0.3,
        diversity: 0.20,
        entropy: 0.24,
        stagnation: 12,
        improvementAcceptanceRate: 0.0,
        worseningAcceptanceRate: 0.5,
        populationTurnover: 0.12,
        bestSignatureChanged: false,
        eliteSimilarity: 0.82,
        bestSignature: 'locked-elite'
    ));

    $engine->observe(new LandscapeMetrics(
        generation: 2,
        bestFitness: 84.0,
        avgFitness: 82.1,
        variance: 0.25,
        diversity: 0.19,
        entropy: 0.23,
        stagnation: 13,
        improvementAcceptanceRate: 0.0,
        worseningAcceptanceRate: 0.55,
        populationTurnover: 0.10,
        bestSignatureChanged: false,
        eliteSimilarity: 0.85,
        bestSignature: 'locked-elite'
    ));

    $observation = $engine->observe(new LandscapeMetrics(
        generation: 3,
        bestFitness: 85.2,
        avgFitness: 84.6,
        variance: 0.9,
        diversity: 0.52,
        entropy: 0.66,
        stagnation: 1,
        improvementAcceptanceRate: 0.65,
        worseningAcceptanceRate: 0.10,
        populationTurnover: 0.72,
        bestSignatureChanged: true,
        eliteSimilarity: 0.22,
        bestSignature: 'escaped-elite'
    ));

    expect($observation->phenomenon)->toBe(LandscapePhenomenon::Neutral)
        ->and($observation->previousEpisode)->toBeArray()
        ->and($observation->previousEpisode['phenomenon'] ?? null)->toBe('local_minimum')
        ->and($observation->previousEpisode['exit_mode'] ?? null)->toBe('recovered')
        ->and($observation->currentEpisode['duration'] ?? null)->toBe(1)
        ->and($observation->searchResponseSimulation)->toBeArray()
        ->and($observation->searchResponseSimulation['policy'] ?? null)->toBe('stability_hold')
        ->and($observation->searchResponseSimulation['would_escalate'] ?? null)->toBeFalse();
});
