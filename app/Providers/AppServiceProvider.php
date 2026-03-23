<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

use Livewire\Livewire;
use App\Modules\Horarios\UI\Livewire\Index;
use App\Modules\Horarios\UI\Livewire\Create;
use App\Modules\Horarios\UI\Livewire\Manage;
use App\Modules\Horarios\UI\Livewire\Configurar;
use App\Modules\Horarios\UI\Livewire\GerenciarAulas;
use App\Modules\Horarios\UI\Livewire\GerenciarRestricoes;
use App\Modules\Horarios\UI\Livewire\ResumoConfiguracao;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
    }

    public function boot(): void {
        $this->configureDefaults();
        Livewire::component('horarios.index', Index::class);
        Livewire::component('horarios.create', Create::class);
        Livewire::component('horarios.manage', Manage::class);
        Livewire::component('horarios.configurar', Configurar::class);
        Livewire::component('horarios.gerenciar-aulas', GerenciarAulas::class);
        Livewire::component('horarios.gerenciar-restricoes', GerenciarRestricoes::class);
        Livewire::component('horarios.resumo-configuracao', ResumoConfiguracao::class);
    }

    protected function configureDefaults(): void {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn(): ?Password => app()->isProduction() ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised() : null);
    }
}
