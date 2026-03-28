<?php

use App\Models\Horario;
use App\Modules\AG\Infrastructure\Logging\GATelemetryLogger;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Infrastructure\Progress\CacheAndDbProgressReporter;
use App\Modules\AG\Infrastructure\Progress\CacheProgressReporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('flushes generation metrics immediately and publishes the current metric to cache', function () {
    $horario = Horario::factory()->create();

    $recorder = new ExecutionMetricsRecorder;
    $executionId = $recorder->startExecution(
        horarioId: $horario->id,
        populationSize: 120,
        generations: 500,
        parameters: ['populacao' => 120, 'geracoes' => 500]
    );

    $reporter = new CacheAndDbProgressReporter(
        new CacheProgressReporter($horario->id),
        $recorder,
        app(GATelemetryLogger::class),
        $horario->id
    );

    $reporter->report([
        'phase' => 'evolving',
        'generation' => 5,
        'best_fitness' => 91.25,
        'avg_fitness' => 84.10,
        'variance' => 1.4,
        'diversity' => 0.66,
        'entropy' => 0.72,
        'mutation_rate' => 0.14,
        'stagnation' => 2,
        'landscape_state' => 'equilibrado',
        'landscape_phenomenon' => 'local_minimum',
        'landscape_observation' => [
            'phenomenon' => 'local_minimum',
            'confidence' => 0.81,
            'depth_score' => 0.74,
            'best_delta' => 0.0,
            'best_delta_window' => -0.015,
            'avg_delta_window' => -0.008,
            'improvement_acceptance_rate' => 0.12,
            'worsening_acceptance_rate' => 0.58,
            'population_turnover' => 0.18,
            'best_signature_changed' => false,
            'elite_similarity' => 0.82,
            'current_episode' => [
                'phenomenon' => 'local_minimum',
                'start_generation' => 3,
                'last_generation' => 5,
                'duration' => 3,
                'peak_confidence' => 0.83,
                'peak_depth_score' => 0.76,
                'avg_population_turnover' => 0.17,
                'avg_elite_similarity' => 0.80,
                'stable_best_signature_rate' => 1.0,
                'active' => true,
                'exit_mode' => 'active',
            ],
            'previous_episode' => [
                'phenomenon' => 'plateau',
                'start_generation' => 1,
                'last_generation' => 2,
                'duration' => 2,
                'peak_confidence' => 0.54,
                'peak_depth_score' => 0.50,
                'avg_population_turnover' => 0.34,
                'avg_elite_similarity' => 0.61,
                'stable_best_signature_rate' => 0.5,
                'active' => false,
                'exit_mode' => 'phenomenon_shift',
            ],
            'basin_of_attraction_lock_confidence' => 0.77,
            'basin_of_attraction_lock_detected' => true,
            'search_response_simulation' => [
                'policy' => 'basin_lock_escape',
                'simulated_only' => true,
                'would_escalate' => true,
                'target_state' => 'exploration',
                'mutation_multiplier' => 3.0,
                'activate_alns' => true,
                'selection_pressure_multiplier' => 0.65,
                'diversification_boost' => 0.85,
                'reason' => 'Persistent basin-of-attraction lock with stable elite signature and low turnover.',
            ],
        ],
        'operator_used' => 'StructuredSwapMutation',
        'operator_reward' => 0.18,
        'alns_destroy_operator' => 'ConflictDestroy',
        'alns_repair_operator' => 'RegretInsertion',
        'alns_improvement' => 0.42,
    ]);

    $storedMetric = DB::table('schedule_generation_metrics')
        ->where('execution_id', $executionId)
        ->where('generation', 5)
        ->first();

    $cachedMetric = Cache::get("ga_execution_metrics_{$executionId}");

    expect($storedMetric)->not->toBeNull()
        ->and((int) $storedMetric->generation)->toBe(5)
        ->and((float) $storedMetric->best_fitness)->toBe(91.25)
        ->and($storedMetric->landscape_phenomenon)->toBe('local_minimum')
        ->and($storedMetric->alns_destroy_operator)->toBe('ConflictDestroy')
        ->and($storedMetric->alns_repair_operator)->toBe('RegretInsertion')
        ->and((float) $storedMetric->alns_improvement)->toBe(0.42)
        ->and($cachedMetric)->toMatchArray([
            'execution_id' => $executionId,
            'generation' => 5,
            'best_fitness' => 91.25,
            'avg_fitness' => 84.10,
            'operator_used' => 'StructuredSwapMutation',
            'landscape_state' => 'equilibrado',
            'landscape_phenomenon' => 'local_minimum',
            'alns_destroy_operator' => 'ConflictDestroy',
            'alns_repair_operator' => 'RegretInsertion',
        ]);

    expect($cachedMetric['landscape_observation'] ?? null)->toBeArray()
        ->and($cachedMetric['landscape_observation']['confidence'] ?? null)->toBe(0.81)
        ->and($cachedMetric['landscape_observation']['population_turnover'] ?? null)->toBe(0.18)
        ->and($cachedMetric['landscape_observation']['elite_similarity'] ?? null)->toBe(0.82)
        ->and($cachedMetric['landscape_observation']['best_signature_changed'] ?? null)->toBeFalse()
        ->and($cachedMetric['landscape_observation']['basin_of_attraction_lock_detected'] ?? null)->toBeTrue()
        ->and($cachedMetric['landscape_observation']['current_episode']['duration'] ?? null)->toBe(3)
        ->and($cachedMetric['landscape_observation']['search_response_simulation']['policy'] ?? null)->toBe('basin_lock_escape');

    $storedObservation = json_decode((string) $storedMetric->landscape_observation, true);

    expect($storedObservation)->toBeArray()
        ->and($storedObservation['best_delta_window'] ?? null)->toBe(-0.015)
        ->and($storedObservation['avg_delta_window'] ?? null)->toBe(-0.008)
        ->and($storedObservation['previous_episode']['exit_mode'] ?? null)->toBe('phenomenon_shift')
        ->and($storedObservation['basin_of_attraction_lock_confidence'] ?? null)->toBe(0.77)
        ->and($storedObservation['search_response_simulation']['activate_alns'] ?? null)->toBeTrue();
});
