<?php

declare(strict_types=1);

use App\Models\Aula;
use App\Models\ConfiguracaoHorario;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\Turma;
use App\Modules\Horarios\Domain\Builders\ScheduleDataBuilder;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('injects custom constraint snapshots into schedule data', function (): void {
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

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'professor_id' => $professor->id,
        'turma_id' => $turma->id,
        'disciplina_id' => $disciplina->id,
    ]);

    $constraint = new CustomConstraintData(
        id: 10,
        name: 'Sincronismo piloto',
        description: 'Snapshot entregue ao solver.',
        type: 'SYNC_SAME_TIMESLOT',
        level: 'HARD',
        weight: 1,
        isActive: true,
        payload: [
            'left_group' => ['lesson_ids' => [1, 2]],
            'right_group' => ['lesson_ids' => [3, 4]],
            'occurrence_mode' => 'ALL',
            'match_mode' => 'ALL_TO_ALL',
        ],
    );

    $scheduleData = (new ScheduleDataBuilder)->build($horario, [$constraint]);

    expect($scheduleData->customConstraints)
        ->toHaveCount(1)
        ->and($scheduleData->customConstraints[0])->toBeInstanceOf(CustomConstraintData::class)
        ->and($scheduleData->customConstraints[0]->name)->toBe('Sincronismo piloto');
});
