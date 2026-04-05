<?php

use App\Modules\AG\Infrastructure\Health\ExecutionHealthBuilder;

it('returns critical health when fail fast is detected', function (): void {
    $builder = new ExecutionHealthBuilder();

    $dto = $builder->build(
        progress: [
            'phase' => 'initial_population',
            'attempt_limit' => 10,
        ],
        operationalCounters: [
            'quality_gate_fail_fast' => 1,
            'quality_gate_rejections' => 2,
            'repair_passes' => 0,
            'alns_activations' => 1,
            'generations_recorded' => 8,
        ],
        bottlenecks: [],
    );

    expect($dto->toArray())
        ->toMatchArray([
            'health' => 'critical',
            'dominant_phase' => 'initial_population',
        ])
        ->and($dto->recommendations)->not->toBeEmpty();
});

it('returns warn health when repair pressure is high', function (): void {
    $builder = new ExecutionHealthBuilder();

    $dto = $builder->build(
        progress: [
            'phase' => 'repair',
            'attempt_limit' => 10,
        ],
        operationalCounters: [
            'quality_gate_fail_fast' => 0,
            'quality_gate_rejections' => 2,
            'repair_passes' => 4,
            'alns_activations' => 1,
            'generations_recorded' => 22,
        ],
        bottlenecks: [],
    );

    expect($dto->toArray())
        ->toMatchArray([
            'health' => 'warn',
            'dominant_phase' => 'repair',
        ])
        ->and($dto->recommendations)->not->toBeEmpty();
});

it('returns ok health when operational counters are stable', function (): void {
    $builder = new ExecutionHealthBuilder();

    $dto = $builder->build(
        progress: [
            'phase' => 'evolution',
            'attempt_limit' => 10,
        ],
        operationalCounters: [
            'quality_gate_fail_fast' => 0,
            'quality_gate_rejections' => 1,
            'repair_passes' => 1,
            'alns_activations' => 2,
            'generations_recorded' => 18,
        ],
        bottlenecks: [],
    );

    expect($dto->toArray())
        ->toMatchArray([
            'health' => 'ok',
            'dominant_phase' => 'evolution',
        ])
        ->and($dto->recommendations)->not->toBeEmpty();
});
