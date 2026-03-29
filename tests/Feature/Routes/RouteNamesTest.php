<?php

use App\Modules\Horarios\UI\Livewire\Aulas as HorariosAulas;
use App\Modules\Horarios\UI\Livewire\Disciplinas as HorariosDisciplinas;
use App\Modules\Horarios\UI\Livewire\Professores as HorariosProfessores;
use App\Modules\Horarios\UI\Livewire\Turmas as HorariosTurmas;
use Illuminate\Support\Facades\Route;

it('has required named routes for horarios and algoritmo', function () {
    expect(Route::has('horarios.index'))->toBeTrue();
    expect(Route::has('horarios.manage'))->toBeTrue();
    expect(Route::has('algoritmo.index'))->toBeTrue();
    expect(Route::has('algoritmo.execution.logs'))->toBeTrue();
});

it('keeps direct CRUD routes wired through the Horarios module', function () {
    expect(Route::getRoutes()->getByName('aulas.index')?->getActionName())->toBe(HorariosAulas\Index::class)
        ->and(Route::getRoutes()->getByName('aulas.edit')?->getActionName())->toBe(HorariosAulas\Edit::class)
        ->and(Route::getRoutes()->getByName('professores.index')?->getActionName())->toBe(HorariosProfessores\Index::class)
        ->and(Route::getRoutes()->getByName('professores.create')?->getActionName())->toBe(HorariosProfessores\Create::class)
        ->and(Route::getRoutes()->getByName('professores.edit')?->getActionName())->toBe(HorariosProfessores\Edit::class)
        ->and(Route::getRoutes()->getByName('turmas.index')?->getActionName())->toBe(HorariosTurmas\Index::class)
        ->and(Route::getRoutes()->getByName('turmas.create')?->getActionName())->toBe(HorariosTurmas\Create::class)
        ->and(Route::getRoutes()->getByName('turmas.edit')?->getActionName())->toBe(HorariosTurmas\Edit::class)
        ->and(Route::getRoutes()->getByName('turmas.aulas')?->getActionName())->toBe(HorariosTurmas\Aulas::class)
        ->and(Route::getRoutes()->getByName('disciplinas.index')?->getActionName())->toBe(HorariosDisciplinas\Index::class)
        ->and(Route::getRoutes()->getByName('disciplinas.create')?->getActionName())->toBe(HorariosDisciplinas\Create::class)
        ->and(Route::getRoutes()->getByName('disciplinas.edit')?->getActionName())->toBe(HorariosDisciplinas\Edit::class);
});
