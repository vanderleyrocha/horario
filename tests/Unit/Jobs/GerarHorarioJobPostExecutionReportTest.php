<?php

use App\Jobs\GerarHorarioJob;
use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds a stable consolidated post execution report in status context', function (): void {
    Carbon::setTestNow('2026-04-03 10:00:00');

    $horario = Horario::factory()->create();

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(5),
        'population_size' => 100,
        'generations' => 60,
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $execution->id,
        'generation' => 5,
        'best_fitness' => 70.0,
        'avg_fitness' => 60.0,
        'diversity' => 0.42,
        'entropy' => 0.51,
        'mutation_rate' => 0.10,
        'stagnation' => 1,
        'alns_destroy_operator' => 'conflict',
        'alns_repair_operator' => 'regret',
        'alns_improvement' => 0.8,
        'created_at' => now()->subMinutes(3),
        'updated_at' => now()->subMinutes(3),
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $execution->id,
        'generation' => 15,
        'best_fitness' => 80.0,
        'avg_fitness' => 65.0,
        'diversity' => 0.40,
        'entropy' => 0.50,
        'mutation_rate' => 0.09,
        'stagnation' => 2,
        'created_at' => now()->subMinutes(1),
        'updated_at' => now()->subMinutes(1),
    ]);

    Cache::put("ga_execution_progress_{$execution->id}", [
        'phase' => 'initial_population',
        'stage' => 'quality_gate_rejected',
        'timestamp' => now()->subSeconds(12)->toIso8601String(),
        'attempt' => 8,
        'queue_size' => 120,
        'repair_summary' => [
            'pass_count' => 2,
        ],
        'initial_population_bottlenecks' => [
            'quality_gate_rejections' => 4,
            'fail_fast_count' => 2,
            'attempts_recorded' => 8,
            'avg_attempt_ms' => 900,
        ],
    ], now()->addMinutes(5));

    $job = new GerarHorarioJob($horario, $execution->id);
    $method = new ReflectionMethod(GerarHorarioJob::class, 'buildExecutionStatusContext');
    $method->setAccessible(true);

    $statusContext = $method->invoke(
        $job,
        'failed',
        $execution->id,
        ['populacao' => 100, 'geracoes' => 60],
        null,
        null,
    );

    expect($statusContext)
        ->toHaveKey('post_execution_report')
        ->toHaveKey('phase_timings_ms')
        ->toHaveKey('dominant_bottleneck')
        ->toHaveKey('operational_counters');

    $report = $statusContext['post_execution_report'];

    expect($report['schema_version'])->toBe(1)
        ->and($report['timings_ms']['initial_population'])->toBe(7200)
        ->and($report['operational_counters']['quality_gate_rejections'])->toBe(4)
        ->and($report['operational_counters']['quality_gate_fail_fast'])->toBe(2)
        ->and($report['operational_counters']['repair_passes'])->toBe(2)
        ->and($report['operational_counters']['alns_activations'])->toBe(1)
        ->and($report['operational_counters']['migration_rounds'])->toBe(3)
        ->and($report['dominant_bottleneck']['key'])->toBe('initial_population_fail_fast_pressure');

    expect($statusContext['phase_timings_ms'])->toBe($report['timings_ms'])
        ->and($statusContext['dominant_bottleneck'])->toBe($report['dominant_bottleneck'])
        ->and($statusContext['operational_counters'])->toBe($report['operational_counters']);

    Carbon::setTestNow();
});
