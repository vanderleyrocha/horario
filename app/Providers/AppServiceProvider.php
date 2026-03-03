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

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
    }

    public function boot(): void {
        $this->configureDefaults();
        Livewire::component('horarios.index', Index::class);
        Livewire::component('horarios.create', Create::class);
        Livewire::component('horarios.manage', Manage::class);
    }

    protected function configureDefaults(): void {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn(): ?Password => app()->isProduction() ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised() : null);
    }
}
