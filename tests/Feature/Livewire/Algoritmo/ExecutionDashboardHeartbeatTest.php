<?php

use App\Livewire\Algoritmo\ExecutionDashboard;
use App\Models\Horario;
use App\Models\ScheduleExecution;
use Livewire\Livewire;

it('exibe os cards operacionais de heartbeat no dashboard principal', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario monitorado',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(3),
        'generations' => 20,
    ]);

    Livewire::test(ExecutionDashboard::class, ['execution' => $execution])
        ->assertSee('Ultimo heartbeat')
        ->assertSee('Atraso atual')
        ->assertSee('Abrir logs desta execucao')
        ->assertSee('Sem heartbeat ainda')
        ->assertSee('Aguardando primeiro sinal');
});
