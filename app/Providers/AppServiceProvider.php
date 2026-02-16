<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

use App\Services\GeneticAlgorithm\Genetico\Operators\SelectionOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Operators\TournamentSelection;

use App\Services\GeneticAlgorithm\Genetico\Operators\CrossoverOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Operators\BlockPreservingCrossover;
use App\Services\GeneticAlgorithm\Genetico\Operators\GeneSwapMutation;
use App\Services\GeneticAlgorithm\Genetico\Operators\MutationOperatorInterface;


class AppServiceProvider extends ServiceProvider {
    public function register(): void {

        $this->app->bind(SelectionOperatorInterface::class, TournamentSelection::class);

        $this->app->bind(CrossoverOperatorInterface::class, BlockPreservingCrossover::class);

        $this->app->bind(MutationOperatorInterface::class, GeneSwapMutation::class);

    }

    public function boot(): void {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction(),);

        Password::defaults(fn(): ?Password => app()->isProduction() ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised() : null);
    }
}
