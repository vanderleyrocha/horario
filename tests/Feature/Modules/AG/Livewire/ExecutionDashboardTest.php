<?php

use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Modules\AG\UI\Livewire\ExecutionDashboard;
use App\Modules\AG\UI\Livewire\ExecutionMetricsStream;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

it('exibe o dashboard em portugues e permite trocar de execucao', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario de Teste',
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
        ->assertSee('Trocar execucao')
        ->assertSee('População inicial')
        ->assertSee('Resumo operacional')
        ->assertSee('Episódio atual')
        ->assertSee('Último episódio encerrado')
        ->assertSee('Transição relevante')
        ->assertSee('Tendência recente')
        ->assertSee('Histórico recente de transições')
        ->assertSee('Freio adaptativo do ALNS')
        ->assertSee('Limite atual de tentativas')
        ->assertSee('Ajuste adaptativo')
        ->assertSee('Limite adaptativo de tentativas')
        ->assertSeeHtml('data-initial-hard-badge')
        ->assertSeeHtml('data-initial-invalid-badge')
        ->assertSeeHtml('data-initial-penalty-badge')
        ->assertSeeHtml('data-landscape-current-episode-title')
        ->assertSeeHtml('data-landscape-current-episode-detail')
        ->assertSeeHtml('data-landscape-previous-episode-title')
        ->assertSeeHtml('data-landscape-previous-episode-detail')
        ->assertSeeHtml('data-landscape-transition-badge')
        ->assertSeeHtml('data-landscape-transition-detail')
        ->assertSeeHtml('data-landscape-trend-badge')
        ->assertSeeHtml('data-landscape-trend-detail')
        ->assertSeeHtml('data-landscape-transition-history')
        ->assertSeeHtml('data-landscape-alns-brake-badge')
        ->assertSeeHtml('data-landscape-alns-brake-detail')
        ->assertSeeHtml('data-ignored-metric-panel')
        ->assertSeeHtml('data-ignored-metric-detail')
        ->assertSeeHtml('data-ignored-metric-count')
        ->set('selectedExecutionId', $olderExecution->id)
        ->assertRedirect(route('algoritmo.execution', ['execution' => $olderExecution->id]));
});

it('usa o cache de initial_population quando ainda nao existem metricas de evolucao', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario em construcao',
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

it('exibe o quadro de gargalos e o resumo terminal quando a execucao falha', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario interrompido',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'failed',
        'start_time' => now()->subHour(),
        'end_time' => now()->subMinutes(5),
        'generations' => 20,
        'status_context_json' => [
            'title' => 'Execucao interrompida por falha',
            'reason' => 'A populacao inicial acumulou conflitos hard demais antes de formar um candidato confiavel.',
            'user_message' => 'A execucao foi interrompida durante a populacao inicial.',
            'execution_status' => 'failed',
            'phase' => 'initial_population',
            'stage' => 'quality_gate_fail_fast',
            'attempt' => 6,
            'queue_size' => 385,
            'hard_conflict_allocations' => 10,
            'hard_penalty' => 126.0,
            'suggestions' => [
                'Introduzir construcao por arrependimento para as aulas mais restritas.',
            ],
            'initial_population_bottlenecks' => [
                'headline' => 'Conflitos hard estao estourando o fail-fast logo nas tentativas iniciais.',
                'fail_fast_count' => 6,
                'quality_gate_rejections' => 0,
                'slowest_attempt_ms' => 140000,
                'peak_hard_conflict_allocations' => 10,
                'likely_bottlenecks' => [
                    'Conflitos hard estao estourando o fail-fast logo nas tentativas iniciais.',
                ],
                'optimization_suggestions' => [
                    'Adicionar memoria de nogoods para evitar recombinar os mesmos conflitos hard.',
                ],
            ],
        ],
    ]);

    Livewire::test(ExecutionDashboard::class, ['execution' => $execution])
        ->assertSee('Encerramento da execucao')
        ->assertSee('Gargalos da população')
        ->assertSee('Diagnóstico da construção inicial');
});

it('emite um payload terminal a partir do banco quando nao existe cache recente', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario sem cache terminal',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'failed',
        'start_time' => now()->subMinutes(20),
        'end_time' => now()->subMinutes(1),
        'status_context_json' => [
            'title' => 'Execucao interrompida por falha',
            'reason' => 'Falha apos repetidos fail-fast na populacao inicial.',
            'phase' => 'initial_population',
            'stage' => 'quality_gate_fail_fast',
        ],
    ]);

    Livewire::test(ExecutionMetricsStream::class, [
        'executionId' => $execution->id,
        'horarioId' => $horario->id,
    ])
        ->call('pollMetrics')
        ->assertDispatched('metrics-update', function (string $event, array $payload): bool {
            return ($payload['metric']['phase'] ?? null) === 'terminal'
                && ($payload['metric']['execution_status'] ?? null) === 'failed'
                && (($payload['metric']['terminal_summary']['stage'] ?? null) === 'quality_gate_fail_fast');
        });
});

it('emite um payload de monitoramento quando a execucao ainda esta running sem cache recente', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario sem progresso em cache',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subHour(),
        'updated_at' => now()->subMinutes(30),
    ]);

    Livewire::test(ExecutionMetricsStream::class, [
        'executionId' => $execution->id,
        'horarioId' => $horario->id,
    ])
        ->call('pollMetrics')
        ->assertDispatched('metrics-update', function (string $event, array $payload): bool {
            return ($payload['metric']['phase'] ?? null) === 'monitoring'
                && ($payload['metric']['execution_status'] ?? null) === 'running'
                && (($payload['metric']['stage'] ?? null) === 'no_cached_progress');
        });
});

it('nao quebra o stream quando o cache da execucao contem payload invalido', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario com cache invalido',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(2),
    ]);

    Cache::put("ga_execution_progress_{$execution->id}", 'payload-invalido', now()->addMinutes(5));

    Livewire::test(ExecutionMetricsStream::class, [
        'executionId' => $execution->id,
        'horarioId' => $horario->id,
    ])
        ->call('pollMetrics')
        ->assertDispatched('metrics-update', function (string $event, array $payload): bool {
            return ($payload['metric']['phase'] ?? null) === 'monitoring'
                && ($payload['metric']['execution_status'] ?? null) === 'running'
                && (($payload['metric']['stage'] ?? null) === 'no_cached_progress');
        });
});
