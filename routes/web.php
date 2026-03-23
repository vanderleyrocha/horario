<?php

// routes/web.php

use App\Http\Controllers\TesteController;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Professores;
use App\Livewire\Turmas;
use App\Livewire\Disciplinas;
use App\Livewire\Aulas;
use App\Livewire\Auth\UserManager;
use App\Models\Horario;
use Illuminate\Support\Facades\Route;

Route::get('/home', function () {

    /** @var \Illuminate\Contracts\Auth\Guard $auth */
    $auth = auth();
    return $auth->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

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


    // SOLVER

    Route::get('/algoritmo/{horario}', \App\Livewire\Algoritmo\ExecutionCenter::class)->name('algoritmo.center');
    Route::get('/algoritmo/index/{horario}', fn (Horario $horario) => redirect()->route('algoritmo.center', $horario))->name('algoritmo.index');


    Route::get('/algoritmo/execution/{execution}', \App\Livewire\Algoritmo\ExecutionDashboard::class)->name('algoritmo.execution');

    Route::get('/algoritmo/execution/{execution}/stream', \App\Livewire\Algoritmo\ExecutionMetricsStream::class)->name('algoritmo.stream');

    /* NOVA ROTA PARA POLLING DO DASHBOARD */

    Route::get('/algoritmo/execution/{execution}/metrics', \App\Livewire\Algoritmo\ExecutionMetricsStream::class)->name('algoritmo.metrics');

    /*
    |--------------------------------------------------------------------------
    | MÓDULO HORÁRIOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('horarios')->name('horarios.')->group(function () {

        Route::get('/', App\Modules\Horarios\UI\Livewire\Index::class)->name('index');
        Route::get('/criar', App\Modules\Horarios\UI\Livewire\Create::class)->name('create');

        // Estrutura unificada (SPA de gerenciamento)
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

        // Aliases por secao (redirecionam para tabs do Manage)
        Route::get('/{horario}/overview', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'overview']))->name('overview');
        Route::get('/{horario}/configuracao', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'config']))->name('config');
        Route::get('/{horario}/aulas', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'aulas']))->name('aulas');
        Route::get('/{horario}/restricoes', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'restricoes']))->name('restricoes');
        Route::get('/{horario}/resumo', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'overview']))->name('resumo');
        Route::get('/{horario}/algoritmo', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'algoritmo']))->name('algoritmo');
        Route::get('/{horario}/diagnostico', fn (Horario $horario) => redirect()->route('horarios.manage', ['horario' => $horario, 'tab' => 'diagnostico']))->name('diagnostico');
    });

    // Profile e Configurações (temporário)
    Route::get('/users', UserManager::class)->name('users.index');
    Route::get('/perfil', fn () => 'Em desenvolvimento')->name('profile');
    Route::get('/configuracoes', fn () => 'Em desenvolvimento')->name('configuracoes');

    // Testes
    Route::get('/teste/alocacoes', [TesteController::class, 'alocacoes'])->name('teste.alocacoes');
});

require __DIR__ . '/settings.php';
