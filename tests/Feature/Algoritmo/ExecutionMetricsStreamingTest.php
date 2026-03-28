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
        ->and($cachedMetric['landscape_observation']['confidence'] ?? null)->toBe(0.81);
});
