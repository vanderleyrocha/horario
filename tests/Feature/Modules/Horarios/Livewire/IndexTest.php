<?php

use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Models\User;
use App\Modules\Horarios\UI\Livewire\Index;
use Livewire\Livewire;

it('exibe a quantidade de execucoes por horario na listagem', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario com execucoes',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'finished',
        'start_time' => now()->subDays(2),
        'end_time' => now()->subDays(2)->addMinutes(3),
        'generations' => 20,
    ]);

    ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(5),
        'generations' => 20,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Execucoes')
        ->assertSee('Horario com execucoes')
        ->assertSee('2')
        ->assertSee('Ultima:')
        ->assertSee('em execucao')
        ->assertSee(route('algoritmo.center', $horario), false);
});
