<?php

use App\Models\Alocacao;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\ScheduleExecution;
use App\Models\Turma;
use App\Modules\Horarios\UI\Livewire\Show;
use Livewire\Livewire;

it('exibe aulas nao alocadas com base na execucao atual', function () {
    $horario = Horario::factory()->create();
    $turma = Turma::factory()->create();
    $professor = Professor::factory()->create();
    $disciplina = Disciplina::factory()->create([
        'codigo' => 'MAT',
        'nome' => 'Matematica',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'finished',
        'start_time' => now()->subMinute(),
        'end_time' => now(),
    ]);

    $aula = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'professor_id' => $professor->id,
        'disciplina_id' => $disciplina->id,
        'aulas_semana' => 3,
        'ativa' => true,
    ]);

    Alocacao::query()->create([
        'horario_id' => $horario->id,
        'execution_id' => $execution->id,
        'aula_id' => $aula->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'dia_semana' => 'segunda',
        'tempo' => 1,
        'duracao_tempos' => 1,
        'horario_inicio' => '07:00:00',
        'horario_fim' => '08:00:00',
        'eh_manual' => false,
        'bloqueada' => false,
    ]);

    Livewire::test(Show::class, ['horario' => $horario])
        ->assertSee('Aulas não alocadas')
        ->assertSee('MAT')
        ->assertSee('Faltando 2 de 3');
});

it('move alocacao para nao alocadas ao soltar no quadro', function () {
    $horario = Horario::factory()->create();
    $turma = Turma::factory()->create();
    $professor = Professor::factory()->create();
    $disciplina = Disciplina::factory()->create([
        'codigo' => 'FIS',
        'nome' => 'Fisica',
    ]);

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'finished',
        'start_time' => now()->subMinute(),
        'end_time' => now(),
    ]);

    $aula = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'professor_id' => $professor->id,
        'disciplina_id' => $disciplina->id,
        'aulas_semana' => 1,
        'ativa' => true,
    ]);

    $alocacao = Alocacao::query()->create([
        'horario_id' => $horario->id,
        'execution_id' => $execution->id,
        'aula_id' => $aula->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'dia_semana' => 'terca',
        'tempo' => 2,
        'duracao_tempos' => 1,
        'horario_inicio' => '08:00:00',
        'horario_fim' => '09:00:00',
        'eh_manual' => false,
        'bloqueada' => false,
    ]);

    Livewire::test(Show::class, ['horario' => $horario])
        ->call('moveToUnallocated', $alocacao->id)
        ->assertSee('Faltando 1 de 1');

    $this->assertDatabaseMissing('alocacoes', [
        'id' => $alocacao->id,
    ]);
});

