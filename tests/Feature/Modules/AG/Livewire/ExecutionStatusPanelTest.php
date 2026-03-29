<?php

use App\Modules\AG\UI\Livewire\ExecutionStatusPanel;
use App\Models\Horario;
use App\Models\ScheduleExecution;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

it('exibe o tempo sem heartbeat sem alarme quando o progresso ainda esta recente', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario monitorado',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(4),
        'generations' => 20,
    ]);

    Cache::put("horario_geracao_{$horario->id}", [
        'phase' => 'initial_population',
        'stage' => 'quality_gate_repairing',
        'timestamp' => now()->subSeconds(12)->toIso8601String(),
        'execution_id' => $execution->id,
        'attempt' => 3,
    ], now()->addMinutes(5));

    Livewire::test(ExecutionStatusPanel::class, ['execution' => $execution])
        ->assertSee('Tempo sem heartbeat')
        ->assertSee('12s')
        ->assertSee('saudavel')
        ->assertDontSee('Aviso de heartbeat');
});

it('sinaliza possivel estagnacao operacional quando o heartbeat fica velho demais', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario com reparo demorado',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(15),
        'generations' => 20,
    ]);

    Cache::put("horario_geracao_{$horario->id}", [
        'phase' => 'initial_population',
        'stage' => 'quality_gate_repairing',
        'timestamp' => now()->subMinutes(8)->toIso8601String(),
        'execution_id' => $execution->id,
        'attempt' => 6,
    ], now()->addMinutes(5));

    Livewire::test(ExecutionStatusPanel::class, ['execution' => $execution])
        ->assertSee('Tempo sem heartbeat')
        ->assertSee('possivel estagnacao operacional')
        ->assertSee('Aviso de heartbeat')
        ->assertSee('O solver esta ha bastante tempo reparando o candidato inicial sem novo heartbeat.');
});

it('mostra o resumo estruturado quando a execucao falha com contexto persistido', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario com falha registrada',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'failed',
        'start_time' => now()->subMinutes(30),
        'end_time' => now()->subMinutes(2),
        'status_context_json' => [
            'title' => 'Execucao interrompida por falha',
            'reason' => 'A populacao inicial acumulou conflitos hard demais.',
            'user_message' => 'A execucao foi interrompida durante a populacao inicial.',
            'phase' => 'initial_population',
            'stage' => 'quality_gate_fail_fast',
            'attempt' => 6,
            'queue_size' => 385,
            'hard_conflict_allocations' => 10,
            'hard_penalty' => 126.0,
            'suggestions' => [
                'Verifique os logs e ajuste a heuristica de construcao inicial.',
            ],
        ],
    ]);

    Livewire::test(ExecutionStatusPanel::class, ['execution' => $execution])
        ->assertSee('Resumo da interrupcao')
        ->assertSee('Execucao interrompida por falha')
        ->assertSee('A populacao inicial acumulou conflitos hard demais.')
        ->assertSee('Verifique os logs e ajuste a heuristica de construcao inicial.');
});

it('usa o updated_at da execucao como heartbeat de ultimo recurso quando nao ha cache', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario sem heartbeat em cache',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subHours(2),
        'generations' => 20,
    ]);

    $execution->forceFill([
        'updated_at' => now()->subMinutes(20),
    ])->saveQuietly();

    Livewire::test(ExecutionStatusPanel::class, ['execution' => $execution])
        ->assertSee('Tempo sem heartbeat')
        ->assertSee('possivel estagnacao operacional')
        ->assertSee('Resumo da interrupcao')
        ->assertSee('Possivel interrupcao operacional');
});
