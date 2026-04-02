<?php

declare(strict_types=1);

use App\Models\Aula;
use App\Models\ConfiguracaoHorario;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\Turma;
use App\Modules\Horarios\UI\Livewire\GerenciarConstraints;
use Livewire\Livewire;

it('creates a custom constraint with dynamic form fields and preview text', function (): void {
    $horario = Horario::factory()->create();

    ConfiguracaoHorario::create([
        'horario_id' => $horario->id,
        'nome_escola' => 'Escola Constraints',
        'aulas_por_dia' => 6,
        'dias_semana' => 5,
        'horario_inicio' => '07:00',
        'horario_fim' => '12:30',
        'duracao_aula_minutos' => 50,
        'duracao_intervalo_minutos' => 15,
        'horarios_intervalos' => [2],
        'duracoes_intervalos' => [15],
        'permitir_janelas' => false,
        'agrupar_disciplinas' => true,
        'max_aulas_seguidas' => 3,
    ]);

    $professor = Professor::factory()->create(['nome' => 'Paula Santos']);
    $turma = Turma::factory()->create(['nome' => '1A']);
    $leftDisciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
    $rightDisciplina = Disciplina::factory()->create(['nome' => 'Física']);

    $leftLesson = Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $leftDisciplina->id,
    ]);

    $rightLesson = Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $rightDisciplina->id,
    ]);

    Livewire::test(GerenciarConstraints::class, ['horario' => $horario])
        ->set('name', 'Sincronismo dos laboratórios')
        ->set('type', 'SYNC_SAME_TIMESLOT')
        ->set('level', 'HARD')
        ->set('weight', 3)
        ->set('leftDisciplineIds', [$leftDisciplina->id])
        ->set('rightDisciplineIds', [$rightDisciplina->id])
        ->set('occurrenceMode', 'AT_LEAST_ONE')
        ->assertSee('Sincroniza')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Constraint criada com sucesso.')
        ->assertSee('Sincronismo dos laboratórios')
        ->assertSee('Sincronismo no mesmo horário');
});

it('reloads saved payload into the form and allows update toggle and delete', function (): void {
    $horario = Horario::factory()->create();

    ConfiguracaoHorario::create([
        'horario_id' => $horario->id,
        'nome_escola' => 'Escola Constraints',
        'aulas_por_dia' => 6,
        'dias_semana' => 5,
        'horario_inicio' => '07:00',
        'horario_fim' => '12:30',
        'duracao_aula_minutos' => 50,
        'duracao_intervalo_minutos' => 15,
        'horarios_intervalos' => [2],
        'duracoes_intervalos' => [15],
        'permitir_janelas' => false,
        'agrupar_disciplinas' => true,
        'max_aulas_seguidas' => 3,
    ]);

    $professor = Professor::factory()->create();
    $turma = Turma::factory()->create();
    $disciplina = Disciplina::factory()->create();
    $turmaSecundaria = Turma::factory()->create(['nome' => '2B']);

    $lesson = Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
    ]);

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turmaSecundaria->id,
        'disciplina_id' => $disciplina->id,
    ]);

    $component = Livewire::test(GerenciarConstraints::class, ['horario' => $horario])
        ->set('name', 'Janela preferida')
        ->set('type', 'TIME_PLACEMENT')
        ->set('level', 'SOFT')
        ->set('weight', 4)
        ->set('targetDisciplineIds', [$disciplina->id])
        ->set('targetTurmaIds', [$turma->id])
        ->set('timePlacementMode', 'PREFERRED')
        ->set('allowedDays', [1, 2])
        ->set('allowedPeriods', [1, 2])
        ->call('save')
        ->assertHasNoErrors();

    $constraintId = $horario->scheduleConstraints()->value('id');

    $component
        ->call('edit', $constraintId)
        ->assertSet('editingId', $constraintId)
        ->assertSet('type', 'TIME_PLACEMENT')
        ->assertSet('targetAllDisciplines', true)
        ->assertSet('targetTurmaIds', [$turma->id])
        ->assertSet('allowedDays', [1, 2])
        ->set('name', 'Janela bloqueada')
        ->set('timePlacementMode', 'FORBIDDEN')
        ->set('allowedDays', [5])
        ->set('allowedPeriods', [5, 6])
        ->call('save')
        ->assertSee('Constraint atualizada com sucesso.')
        ->assertSee('Janela bloqueada')
        ->call('toggleStatus', $constraintId)
        ->assertSee('Constraint desativada.')
        ->call('delete', $constraintId)
        ->assertSee('Constraint removida com sucesso.');

    expect($horario->scheduleConstraints()->count())->toBe(0);
});

it('accepts sync with only the left discipline and turma filter', function (): void {
    $horario = Horario::factory()->create();

    ConfiguracaoHorario::create([
        'horario_id' => $horario->id,
        'nome_escola' => 'Escola Constraints',
        'aulas_por_dia' => 6,
        'dias_semana' => 5,
        'horario_inicio' => '07:00',
        'horario_fim' => '12:30',
        'duracao_aula_minutos' => 50,
        'duracao_intervalo_minutos' => 15,
        'horarios_intervalos' => [2],
        'duracoes_intervalos' => [15],
        'permitir_janelas' => false,
        'agrupar_disciplinas' => true,
        'max_aulas_seguidas' => 3,
    ]);

    $professor = Professor::factory()->create();
    $disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
    $turmaA = Turma::factory()->create(['nome' => '1A']);
    $turmaB = Turma::factory()->create(['nome' => '1B']);

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turmaA->id,
        'disciplina_id' => $disciplina->id,
    ]);

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turmaB->id,
        'disciplina_id' => $disciplina->id,
    ]);

    Livewire::test(GerenciarConstraints::class, ['horario' => $horario])
        ->set('name', 'Sincronismo interno da matemática')
        ->set('type', 'SYNC_SAME_TIMESLOT')
        ->set('leftDisciplineIds', [$disciplina->id])
        ->set('leftTurmaIds', [$turmaA->id, $turmaB->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Constraint criada com sucesso.');
});

it('accepts exclusion with multiple disciplines on the left side only', function (): void {
    $horario = Horario::factory()->create();

    ConfiguracaoHorario::create([
        'horario_id' => $horario->id,
        'nome_escola' => 'Escola Constraints',
        'aulas_por_dia' => 6,
        'dias_semana' => 5,
        'horario_inicio' => '07:00',
        'horario_fim' => '12:30',
        'duracao_aula_minutos' => 50,
        'duracao_intervalo_minutos' => 15,
        'horarios_intervalos' => [2],
        'duracoes_intervalos' => [15],
        'permitir_janelas' => false,
        'agrupar_disciplinas' => true,
        'max_aulas_seguidas' => 3,
    ]);

    $professor = Professor::factory()->create();
    $turma = Turma::factory()->create(['nome' => '2A']);
    $disciplinaA = Disciplina::factory()->create(['nome' => 'História']);
    $disciplinaB = Disciplina::factory()->create(['nome' => 'Geografia']);

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplinaA->id,
    ]);

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplinaB->id,
    ]);

    Livewire::test(GerenciarConstraints::class, ['horario' => $horario])
        ->set('name', 'Sem sobreposição humanas')
        ->set('type', 'MUTUAL_EXCLUSION')
        ->set('leftDisciplineIds', [$disciplinaA->id, $disciplinaB->id])
        ->set('leftTurmaIds', [$turma->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Constraint criada com sucesso.');
});

it('allows using selecionar todas inside the turma block', function (): void {
    $horario = Horario::factory()->create();

    ConfiguracaoHorario::create([
        'horario_id' => $horario->id,
        'nome_escola' => 'Escola Constraints',
        'aulas_por_dia' => 6,
        'dias_semana' => 5,
        'horario_inicio' => '07:00',
        'horario_fim' => '12:30',
        'duracao_aula_minutos' => 50,
        'duracao_intervalo_minutos' => 15,
        'horarios_intervalos' => [2],
        'duracoes_intervalos' => [15],
        'permitir_janelas' => false,
        'agrupar_disciplinas' => true,
        'max_aulas_seguidas' => 3,
    ]);

    $professor = Professor::factory()->create();
    $disciplina = Disciplina::factory()->create();
    $turmaA = Turma::factory()->create(['nome' => '1A']);
    $turmaB = Turma::factory()->create(['nome' => '2A']);

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turmaA->id,
        'disciplina_id' => $disciplina->id,
    ]);

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turmaB->id,
        'disciplina_id' => $disciplina->id,
    ]);

    Livewire::test(GerenciarConstraints::class, ['horario' => $horario])
        ->set('name', 'Janela geral das turmas')
        ->set('type', 'TIME_PLACEMENT')
        ->set('targetAllDisciplines', true)
        ->set('targetAllTurmas', true)
        ->set('timePlacementMode', 'FORBIDDEN')
        ->set('allowedDays', [5])
        ->set('allowedPeriods', [6])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Constraint criada com sucesso.');

    expect($horario->scheduleConstraints()->count())->toBe(1);
});
