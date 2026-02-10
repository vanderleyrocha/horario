<?php
// routes/web.php

use App\Http\Controllers\TesteController;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Professores;
use App\Livewire\Turmas;
use App\Livewire\Disciplinas;
use App\Livewire\Horarios;
use App\Livewire\Aulas;
use App\Livewire\Horarios\Configurar;

use App\Livewire\Auth\UserManager;

use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/aulas/{horario_id}/index', Aulas\Index::class)->name('aulas.index');
    Route::get('/aulas/{aula}/edit', Aulas\Edit::class)->name('aulas.edit');

    // Professores
    Route::get('/professores', Professores\Index::class)->name('professores.index');
    Route::get('/professores/criar', Professores\Create::class)->name('professores.create');
    Route::get('/professores/{professor}/editar', Professores\Edit::class)->name('professores.edit');

    // Turmas
    Route::get('/turmas', Turmas\Index::class)->name('turmas.index');
    Route::get('/turmas/criar', Turmas\Create::class)->name('turmas.create');
    Route::get('/turmas/{turma}/editar', Turmas\Edit::class)->name('turmas.edit');
    Route::get('/turmas/{turma}/aulas', Turmas\Aulas::class)->name('turmas.aulas');

    // Disciplinas
    Route::get('/disciplinas', Disciplinas\Index::class)->name('disciplinas.index');
    Route::get('/disciplinas/criar', Disciplinas\Create::class)->name('disciplinas.create');
    Route::get('/disciplinas/{disciplina}/editar', Disciplinas\Edit::class)->name('disciplinas.edit');

    // Horários
    Route::get('/horarios', Horarios\Index::class)->name('horarios.index');
    Route::get('/horarios/criar', Horarios\Create::class)->name('horarios.create');
    Route::get('/horarios/{horario}', Horarios\Show::class)->name('horarios.show');
    Route::get('/horarios/{horario}/configurar', Configurar::class)->name('horarios.configurar');

    // ✅ CORRIGIDO: Adicionar parâmetro {horario} na rota
    Route::get('/algoritmo/{horario?}', App\Livewire\Algoritmo\Index::class)->name('algoritmo.index');

    // Profile e Configurações (temporário)
    Route::get('/users', UserManager::class)->name('users.index');
    Route::get('/perfil', fn() => 'Em desenvolvimento')->name('profile');
    Route::get('/configuracoes', fn() => 'Em desenvolvimento')->name('configuracoes');

    // Testes
    Route::get('/teste/alocacoes', [TesteController::class, 'alocacoes'])->name('teste.alocacoes');
});

require __DIR__.'/settings.php';
