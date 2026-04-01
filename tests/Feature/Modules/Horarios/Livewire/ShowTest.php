<?php

use App\Models\Alocacao;
use App\Models\Aula;
use App\Models\ConfiguracaoHorario;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\ScheduleExecution;
use App\Models\Turma;
use App\Modules\Horarios\UI\Livewire\Show;
use Livewire\Livewire;

function configurarHorarioVisualizacao(Horario $horario): void
{
    ConfiguracaoHorario::query()->create([
        'horario_id' => $horario->id,
        'nome_escola' => 'Escola Teste',
        'aulas_por_dia' => 5,
        'dias_semana' => 5,
        'horario_inicio' => '07:00',
        'horario_fim' => '12:00',
        'duracao_aula_minutos' => 50,
        'duracao_intervalo_minutos' => 10,
        'horarios_intervalos' => [],
        'duracoes_intervalos' => [],
        'permitir_janelas' => false,
        'agrupar_disciplinas' => false,
        'max_aulas_seguidas' => 2,
        'elitism_count' => 1,
        'target_fitness' => 0,
        'max_generations_without_improvement' => 10,
    ]);
}

function criarExecucao(Horario $horario): ScheduleExecution
{
    return ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'finished',
        'start_time' => now()->subMinute(),
        'end_time' => now(),
    ]);
}

it('exibe aulas nao alocadas com base na execucao atual', function () {
    $horario = Horario::factory()->create();
    configurarHorarioVisualizacao($horario);
    $turma = Turma::factory()->create();
    $professor = Professor::factory()->create();
    $disciplina = Disciplina::factory()->create([
        'codigo' => 'MAT',
        'nome' => 'Matematica',
    ]);

    $execution = criarExecucao($horario);

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
    configurarHorarioVisualizacao($horario);
    $turma = Turma::factory()->create();
    $professor = Professor::factory()->create();
    $disciplina = Disciplina::factory()->create([
        'codigo' => 'FIS',
        'nome' => 'Fisica',
    ]);

    $execution = criarExecucao($horario);

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

    expect(Alocacao::query()->whereKey($alocacao->id)->exists())->toBeFalse();
});

it('aloca manualmente uma aula nao alocada ao soltar na grade', function () {
    $horario = Horario::factory()->create();
    configurarHorarioVisualizacao($horario);

    $turma = Turma::factory()->create([
        'codigo' => '1A',
    ]);

    $professor = Professor::factory()->create([
        'nome' => 'Professor Teste',
    ]);

    $disciplina = Disciplina::factory()->create([
        'codigo' => 'QUI',
        'nome' => 'Quimica',
    ]);

    $execution = criarExecucao($horario);

    $aula = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'professor_id' => $professor->id,
        'disciplina_id' => $disciplina->id,
        'aulas_semana' => 1,
        'tipo' => 'simples',
        'ativa' => true,
    ]);

    Livewire::test(Show::class, ['horario' => $horario])
        ->call('allocateUnallocatedAula', $aula->id, 'segunda', '07:00')
        ->assertSee('Aula alocada manualmente com sucesso.');

    expect(Alocacao::query()->where([
        'horario_id' => $horario->id,
        'execution_id' => $execution->id,
        'aula_id' => $aula->id,
        'turma_id' => $turma->id,
        'professor_id' => $professor->id,
        'dia_semana' => 'segunda',
        'tempo' => 1,
        'duracao_tempos' => 1,
        'eh_manual' => true,
    ])->exists())->toBeTrue();
});

it('impede alocar aula nao alocada quando o professor ja possui aula no mesmo horario', function () {
    $horario = Horario::factory()->create();
    configurarHorarioVisualizacao($horario);

    $turmaA = Turma::factory()->create([
        'codigo' => '1A',
    ]);

    $turmaB = Turma::factory()->create([
        'codigo' => '1B',
    ]);

    $professor = Professor::factory()->create([
        'nome' => 'Maria Silva',
    ]);

    $disciplinaA = Disciplina::factory()->create([
        'codigo' => 'MAT',
    ]);

    $disciplinaB = Disciplina::factory()->create([
        'codigo' => 'BIO',
    ]);

    $execution = criarExecucao($horario);

    $aulaExistente = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turmaA->id,
        'professor_id' => $professor->id,
        'disciplina_id' => $disciplinaA->id,
        'aulas_semana' => 1,
        'ativa' => true,
    ]);

    $aulaNaoAlocada = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turmaB->id,
        'professor_id' => $professor->id,
        'disciplina_id' => $disciplinaB->id,
        'aulas_semana' => 1,
        'ativa' => true,
    ]);

    Alocacao::query()->create([
        'horario_id' => $horario->id,
        'execution_id' => $execution->id,
        'aula_id' => $aulaExistente->id,
        'turma_id' => $turmaA->id,
        'disciplina_id' => $disciplinaA->id,
        'professor_id' => $professor->id,
        'dia_semana' => 'segunda',
        'tempo' => 1,
        'duracao_tempos' => 1,
        'horario_inicio' => '07:00:00',
        'horario_fim' => '07:50:00',
        'eh_manual' => false,
        'bloqueada' => false,
    ]);

    Livewire::test(Show::class, ['horario' => $horario])
        ->call('allocateUnallocatedAula', $aulaNaoAlocada->id, 'segunda', '07:00')
        ->assertSee('Conflito de horario')
        ->assertSee('professor Maria Silva');

    expect(Alocacao::query()->where([
        'horario_id' => $horario->id,
        'aula_id' => $aulaNaoAlocada->id,
        'dia_semana' => 'segunda',
        'tempo' => 1,
    ])->exists())->toBeFalse();
});

it('impede alocar aula nao alocada quando a turma ja possui aula no mesmo horario', function () {
    $horario = Horario::factory()->create();
    configurarHorarioVisualizacao($horario);

    $turma = Turma::factory()->create([
        'codigo' => '2A',
    ]);

    $professorA = Professor::factory()->create([
        'nome' => 'Carlos Lima',
    ]);

    $professorB = Professor::factory()->create([
        'nome' => 'Ana Souza',
    ]);

    $disciplinaA = Disciplina::factory()->create([
        'codigo' => 'HIS',
    ]);

    $disciplinaB = Disciplina::factory()->create([
        'codigo' => 'GEO',
    ]);

    $execution = criarExecucao($horario);

    $aulaExistente = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'professor_id' => $professorA->id,
        'disciplina_id' => $disciplinaA->id,
        'aulas_semana' => 1,
        'ativa' => true,
    ]);

    $aulaNaoAlocada = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'professor_id' => $professorB->id,
        'disciplina_id' => $disciplinaB->id,
        'aulas_semana' => 1,
        'ativa' => true,
    ]);

    Alocacao::query()->create([
        'horario_id' => $horario->id,
        'execution_id' => $execution->id,
        'aula_id' => $aulaExistente->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplinaA->id,
        'professor_id' => $professorA->id,
        'dia_semana' => 'terca',
        'tempo' => 2,
        'duracao_tempos' => 1,
        'horario_inicio' => '07:50:00',
        'horario_fim' => '08:40:00',
        'eh_manual' => false,
        'bloqueada' => false,
    ]);

    Livewire::test(Show::class, ['horario' => $horario])
        ->call('allocateUnallocatedAula', $aulaNaoAlocada->id, 'terca', '07:50')
        ->assertSee('Conflito de horario')
        ->assertSee('turma 2A');

    expect(Alocacao::query()->where([
        'horario_id' => $horario->id,
        'aula_id' => $aulaNaoAlocada->id,
        'dia_semana' => 'terca',
        'tempo' => 2,
    ])->exists())->toBeFalse();
});

it('exibe o codigo da turma no bloco alocado quando a visualizacao e por professor', function () {
    $horario = Horario::factory()->create();
    configurarHorarioVisualizacao($horario);

    $turma = Turma::factory()->create([
        'codigo' => '3A',
        'nome' => 'Terceiro Ano A',
    ]);

    $professor = Professor::factory()->create([
        'nome' => 'Joao Pereira',
    ]);

    $disciplina = Disciplina::factory()->create([
        'codigo' => 'ART',
    ]);

    $execution = criarExecucao($horario);

    $aula = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'professor_id' => $professor->id,
        'disciplina_id' => $disciplina->id,
        'aulas_semana' => 1,
        'ativa' => true,
    ]);

    Alocacao::query()->create([
        'horario_id' => $horario->id,
        'execution_id' => $execution->id,
        'aula_id' => $aula->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
        'professor_id' => $professor->id,
        'dia_semana' => 'quarta',
        'tempo' => 1,
        'duracao_tempos' => 1,
        'horario_inicio' => '07:00:00',
        'horario_fim' => '07:50:00',
        'eh_manual' => false,
        'bloqueada' => false,
    ]);

    Livewire::test(Show::class, ['horario' => $horario])
        ->set('view', 'professores')
        ->set('entidadeId', $professor->id)
        ->assertSee('3A');
});

it('exibe a matriz consolidada por professor e turma', function () {
    $horario = Horario::factory()->create();
    configurarHorarioVisualizacao($horario);

    $turma = Turma::factory()->create([
        'codigo' => '4A',
        'nome' => 'Quarto Ano A',
    ]);

    $professor = Professor::factory()->create([
        'nome' => 'Paula Santos',
    ]);

    $disciplina = Disciplina::factory()->create([
        'codigo' => 'ING',
    ]);

    $execution = criarExecucao($horario);

    $aula = Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turma->id,
        'professor_id' => $professor->id,
        'disciplina_id' => $disciplina->id,
        'aulas_semana' => 1,
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
        'horario_fim' => '07:50:00',
        'eh_manual' => false,
        'bloqueada' => false,
    ]);

    Livewire::test(Show::class, ['horario' => $horario])
        ->set('view', 'matriz-professores')
        ->assertSee('Matriz geral por professor, dia e tempo')
        ->assertSee('Paula Santos')
        ->assertSee('4A')
        ->assertSee('Segunda')
        ->assertSee('ING');
});
