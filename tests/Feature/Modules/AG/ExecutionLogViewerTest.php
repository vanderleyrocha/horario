<?php

use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Models\User;

it('exibe um extrato de logs filtrado para a execucao', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario com logs',
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

    file_put_contents(storage_path('logs/laravel.log'), sprintf("[2026-03-28 18:10:00] local.INFO: worker heartbeat {\"execution_id\":%d,\"horario_id\":%d}\n", $execution->id, $horario->id), FILE_APPEND);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('algoritmo.execution.logs', ['execution' => $execution->id]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee((string) $execution->id)
        ->assertSee((string) $horario->id)
        ->assertSee('worker heartbeat');
});
