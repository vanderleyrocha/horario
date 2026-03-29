<?php

namespace App\Providers;

use App\Modules\Horarios\UI\Livewire\Aulas as HorariosAulas;
use App\Modules\Horarios\UI\Livewire\Configurar;
use App\Modules\Horarios\UI\Livewire\Create;
use App\Modules\Horarios\UI\Livewire\Disciplinas as HorariosDisciplinas;
use App\Modules\Horarios\UI\Livewire\GerenciarAulas;
use App\Modules\Horarios\UI\Livewire\GerenciarRestricoes;
use App\Modules\Horarios\UI\Livewire\Index;
use App\Modules\Horarios\UI\Livewire\Manage;
use App\Modules\Horarios\UI\Livewire\Professores as HorariosProfessores;
use App\Modules\Horarios\UI\Livewire\ResumoConfiguracao;
use App\Modules\Horarios\UI\Livewire\Turmas as HorariosTurmas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureDefaults();
        Livewire::component('horarios.index', Index::class);
        Livewire::component('horarios.create', Create::class);
        Livewire::component('horarios.manage', Manage::class);
        Livewire::component('horarios.configurar', Configurar::class);
        Livewire::component('horarios.gerenciar-aulas', GerenciarAulas::class);
        Livewire::component('horarios.gerenciar-restricoes', GerenciarRestricoes::class);
        Livewire::component('horarios.resumo-configuracao', ResumoConfiguracao::class);
        Livewire::component('app.modules.horarios.ui.livewire.aulas.index', HorariosAulas\Index::class);
        Livewire::component('app.modules.horarios.ui.livewire.aulas.edit', HorariosAulas\Edit::class);
        Livewire::component('app.modules.horarios.ui.livewire.professores.index', HorariosProfessores\Index::class);
        Livewire::component('app.modules.horarios.ui.livewire.professores.create', HorariosProfessores\Create::class);
        Livewire::component('app.modules.horarios.ui.livewire.professores.edit', HorariosProfessores\Edit::class);
        Livewire::component('app.modules.horarios.ui.livewire.turmas.index', HorariosTurmas\Index::class);
        Livewire::component('app.modules.horarios.ui.livewire.turmas.create', HorariosTurmas\Create::class);
        Livewire::component('app.modules.horarios.ui.livewire.turmas.edit', HorariosTurmas\Edit::class);
        Livewire::component('app.modules.horarios.ui.livewire.turmas.aulas', HorariosTurmas\Aulas::class);
        Livewire::component('app.modules.horarios.ui.livewire.disciplinas.index', HorariosDisciplinas\Index::class);
        Livewire::component('app.modules.horarios.ui.livewire.disciplinas.create', HorariosDisciplinas\Create::class);
        Livewire::component('app.modules.horarios.ui.livewire.disciplinas.edit', HorariosDisciplinas\Edit::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction() ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised() : null);
    }
}
