<?php

use App\Models\Alocacao;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\ScheduleExecution;
use App\Models\Turma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('bloqueia alocacao quando execution_id pertence a outro horario', function () {
    $horarioA = Horario::factory()->create();
    $horarioB = Horario::factory()->create();

    $executionA = ScheduleExecution::query()->create([
        'horario_id' => $horarioA->id,
        'status' => 'finished',
        'start_time' => now()->subMinute(),
        'end_time' => now(),
    ]);

    $turma = Turma::factory()->create();
    $disciplina = Disciplina::factory()->create();
    $professor = Professor::factory()->create();

    expect(function () use ($horarioB, $executionA, $turma, $disciplina, $professor): void {
        Alocacao::query()->create([
            'horario_id' => $horarioB->id,
            'execution_id' => $executionA->id,
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
    })->toThrow(ValidationException::class);
});

test('comando de reparo corrige execution_id associado ao horario errado', function () {
    $horarioExec = Horario::factory()->create();
    $horarioAloc = Horario::factory()->create();

    $execution = ScheduleExecution::query()->create([
        'horario_id' => $horarioExec->id,
        'status' => 'finished',
        'start_time' => now()->subMinute(),
        'end_time' => now(),
    ]);

    $turma = Turma::factory()->create();
    $disciplina = Disciplina::factory()->create();
    $professor = Professor::factory()->create();

    DB::table('alocacoes')->insert([
        'horario_id' => $horarioAloc->id,
        'execution_id' => $execution->id,
        'aula_id' => null,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'dia_semana' => 'segunda',
        'tempo' => 1,
        'duracao_tempos' => 1,
        'eh_manual' => false,
        'bloqueada' => false,
        'horario_inicio' => '07:00:00',
        'horario_fim' => '08:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('horarios:repair-execution-integrity')
        ->assertExitCode(0);

    $alocacao = Alocacao::query()->firstOrFail();
    $alocacao->refresh();

    expect($alocacao->execution_id)->not()->toBe($execution->id);
    expect($alocacao->execution)->not->toBeNull();
    expect($alocacao->execution->horario_id)->toBe($horarioAloc->id);
});

