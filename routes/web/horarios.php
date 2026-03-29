<?php

use App\Models\Horario;
use App\Modules\Horarios\UI\Livewire\Aulas as HorariosAulas;
use App\Modules\Horarios\UI\Livewire\Disciplinas as HorariosDisciplinas;
use App\Modules\Horarios\UI\Livewire\Professores as HorariosProfessores;
use App\Modules\Horarios\UI\Livewire\Turmas as HorariosTurmas;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/aulas/{horario_id}/index', HorariosAulas\Index::class)->name('aulas.index');
    Route::get('/aulas/{aula}/edit', HorariosAulas\Edit::class)->name('aulas.edit');

    Route::get('/professores', HorariosProfessores\Index::class)->name('professores.index');
    Route::get('/professores/criar', HorariosProfessores\Create::class)->name('professores.create');
    Route::get('/professores/{professor}/editar', HorariosProfessores\Edit::class)->name('professores.edit');

    Route::get('/turmas', HorariosTurmas\Index::class)->name('turmas.index');
    Route::get('/turmas/criar', HorariosTurmas\Create::class)->name('turmas.create');
    Route::get('/turmas/{turma}/editar', HorariosTurmas\Edit::class)->name('turmas.edit');
    Route::get('/turmas/{turma}/aulas', HorariosTurmas\Aulas::class)->name('turmas.aulas');

    Route::get('/disciplinas', HorariosDisciplinas\Index::class)->name('disciplinas.index');
    Route::get('/disciplinas/criar', HorariosDisciplinas\Create::class)->name('disciplinas.create');
    Route::get('/disciplinas/{disciplina}/editar', HorariosDisciplinas\Edit::class)->name('disciplinas.edit');

    Route::prefix('horarios')->name('horarios.')->group(function () {
        Route::get('/', App\Modules\Horarios\UI\Livewire\Index::class)->name('index');
        Route::get('/criar', App\Modules\Horarios\UI\Livewire\Create::class)->name('create');

        Route::get('/{horario}/manage', App\Modules\Horarios\UI\Livewire\Manage::class)->name('manage');
        Route::get('/{horario}/visualizar', App\Modules\Horarios\UI\Livewire\Show::class)->name('show');
        Route::get('/{horario}/configurar', function (Horario $horario) {
            $etapa = (int) request()->integer('etapa', 1);
            $tabByEtapa = [
                1 => 'overview',
                2 => 'aulas',
                3 => 'restricoes',
                4 => 'algoritmo',
                5 => 'overview',
            ];

            return redirect()->route('horarios.manage', [
                'horario' => $horario,
                'tab' => $tabByEtapa[$etapa] ?? 'overview',
            ]);
        })->name('configurar');

        Route::get('/{horario}/overview', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'overview']))->name('overview');
        Route::get('/{horario}/configuracao', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'config']))->name('config');
        Route::get('/{horario}/aulas', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'aulas']))->name('aulas');
        Route::get('/{horario}/restricoes', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'restricoes']))->name('restricoes');
        Route::get('/{horario}/resumo', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'overview']))->name('resumo');
        Route::get('/{horario}/algoritmo', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'algoritmo']))->name('algoritmo');
        Route::get('/{horario}/diagnostico', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'diagnostico']))->name('diagnostico');
    });
});
