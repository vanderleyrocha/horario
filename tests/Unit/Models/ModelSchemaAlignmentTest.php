<?php

use App\Models\Alocacao;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\Turma;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('keeps the active models aligned with the current horario schema', function (): void {
    $horario = new Horario;
    $alocacao = new Alocacao;
    $professor = new Professor;
    $disciplina = new Disciplina;
    $turma = new Turma;

    expect($horario->getFillable())
        ->toContain('diagnostico_json')
        ->toContain('indice_risco')
        ->toContain('tempo_processamento_segundos')
        ->and($horario->getCasts())
        ->toMatchArray([
            'diagnostico_json' => 'array',
            'indice_risco' => 'integer',
            'configuracao' => 'array',
        ])
        ->and($alocacao->getCasts())
        ->toMatchArray([
            'horario_inicio' => 'string',
            'horario_fim' => 'string',
            'eh_manual' => 'boolean',
            'bloqueada' => 'boolean',
        ])
        ->and($professor->getFillable())
        ->not->toContain('id')
        ->and($disciplina->getFillable())
        ->not->toContain('id')
        ->and($turma->getFillable())
        ->not->toContain('id');
});

it('removes the legacy solver tables from the active database', function (): void {
    expect(Schema::hasTable('solver_executions'))->toBeFalse()
        ->and(Schema::hasTable('solver_generation_metrics'))->toBeFalse()
        ->and(Schema::hasTable('solver_landscape_history'))->toBeFalse()
        ->and(Schema::hasTable('solver_operator_usage'))->toBeFalse();
});
