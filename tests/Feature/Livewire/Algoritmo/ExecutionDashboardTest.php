<?php

use App\Livewire\Algoritmo\ExecutionDashboard;
use App\Livewire\Algoritmo\ExecutionMetricsStream;
use App\Models\Horario;
use App\Models\ScheduleExecution;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

it('exibe o dashboard em portugues e permite trocar de execucao', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horário de Teste',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $olderExecution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'failed',
        'start_time' => now()->subMinutes(30),
        'generations' => 20,
    ]);

    $currentExecution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(5),
        'generations' => 20,
    ]);

    Livewire::test(ExecutionDashboard::class, ['execution' => $currentExecution])
        ->assertSee('Execução do Solver')
        ->assertSee('Trocar execução')
        ->assertSee('População inicial')
        ->set('selectedExecutionId', $olderExecution->id)
        ->assertRedirect(route('algoritmo.execution', ['execution' => $olderExecution->id]));
});

it('usa o cache de initial_population quando ainda nao existem metricas de evolucao', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horário em construção',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(2),
        'generations' => 20,
    ]);

    Cache::put("horario_geracao_{$horario->id}", [
        'phase' => 'initial_population',
        'execution_id' => $execution->id,
        'stage' => 'quality_gate_repairing',
        'attempt' => 3,
        'fill_ratio' => 1.0,
        'queue_size' => 385,
        'allocations' => 385,
        'forced_allocations' => 9,
        'hard_conflict_allocations' => 8,
    ], now()->addMinutes(5));

    Livewire::test(ExecutionMetricsStream::class, [
        'executionId' => $execution->id,
        'horarioId' => $horario->id,
    ])
        ->call('pollMetrics')
        ->assertDispatched('metrics-update', function (string $event, array $payload): bool {
            return ($payload['metric']['phase'] ?? null) === 'initial_population'
                && ($payload['metric']['stage'] ?? null) === 'quality_gate_repairing'
                && ($payload['metric']['attempt'] ?? null) === 3;
        });
});
