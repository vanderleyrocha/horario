<?php

use App\Livewire\Algoritmo\ExecutionStatusPanel;
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
