<?php

use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Horario;
use App\Models\Professor;
use App\Models\Turma;
use App\Modules\Horarios\UI\Livewire\ResumoConfiguracao;
use Illuminate\Support\Collection;
use Livewire\Livewire;

it('calcula e estrutura a propriedade aulasPorTurma conforme o componente atual', function () {
    $horario = Horario::factory()->create();
    $turmaA = Turma::factory()->create(['nome' => 'Turma A']);
    $disciplinaA = Disciplina::factory()->create(['nome' => 'Matematica']);
    $disciplinaB = Disciplina::factory()->create(['nome' => 'Fisica']);
    $professor = Professor::factory()->create();

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turmaA->id,
        'disciplina_id' => $disciplinaA->id,
        'professor_id' => $professor->id,
        'aulas_semana' => 2,
        'tipo' => 'simples',
    ]);

    Aula::factory()->create([
        'horario_id' => $horario->id,
        'turma_id' => $turmaA->id,
        'disciplina_id' => $disciplinaB->id,
        'professor_id' => $professor->id,
        'aulas_semana' => 1,
        'tipo' => 'dupla',
    ]);

    Livewire::test(ResumoConfiguracao::class, ['horario' => $horario])
        ->assertOk()
        ->assertViewHas('aulasPorTurma', function ($collection) use ($turmaA, $disciplinaA, $disciplinaB) {
            expect($collection)->toBeInstanceOf(Collection::class)->toHaveCount(1);

            $dadosTurma = $collection->first();

            expect($dadosTurma)
                ->toHaveKeys(['turma', 'total_aulas', 'total_tempos', 'disciplinas'])
                ->and($dadosTurma['turma']->id)->toBe($turmaA->id)
                ->and($dadosTurma['total_aulas'])->toBe(2)
                ->and($dadosTurma['total_tempos'])->toBe(4)
                ->and($dadosTurma['disciplinas']->pluck('id')->sort()->values()->all())->toBe([
                    $disciplinaA->id,
                    $disciplinaB->id,
                ]);

            return true;
        });
});

it('retorna colecao vazia se nao houver aulas', function () {
    $horario = Horario::factory()->create();

    Livewire::test(ResumoConfiguracao::class, ['horario' => $horario])
        ->assertViewHas('aulasPorTurma', function ($collection) {
            expect($collection)->toBeInstanceOf(Collection::class);

            return $collection->isEmpty();
        });
});
