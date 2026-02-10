<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

// ✅ ALTERADO: Renomeado FitnessFunction para FitnessEvaluator
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessEvaluator;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessWeights; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\FitnessRuleInterface; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Fitness\HardRuleInterface; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Fitness\SoftRuleInterface; // ✅ ADICIONADO

// Suas regras de fitness
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\AulasNaoConsecutivasRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\BloqueiosHardRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\BloqueiosPreferenciaisRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\CargaHorariaExcedidaRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\ConflitoProfessorRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\ConflitoTurmaRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\DistribuicaoRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\JanelasRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\MaxAulasDiaRule;

// Novas classes e interfaces para o Orchestrator
use App\Services\GeneticAlgorithm\Genetico\HorarioGeneticoOrchestrator; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\PopulationGenerator; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Repositories\ConfiguracaoHorarioReadRepository; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Operators\SelectionOperatorInterface; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Operators\TournamentSelection; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Operators\CrossoverOperatorInterface; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Operators\SinglePointCrossover; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Operators\MutationOperatorInterface; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Operators\GeneSwapMutation; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Termination\TerminationCriterionInterface; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Termination\MaxGenerationsOrFitnessCriterion; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Metrics\MetricsRecorder; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO; // ✅ ADICIONADO

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        // Bind para as regras de fitness
        $this->app->bind(AulasNaoConsecutivasRule::class, AulasNaoConsecutivasRule::class);
        $this->app->bind(BloqueiosHardRule::class, BloqueiosHardRule::class);
        $this->app->bind(BloqueiosPreferenciaisRule::class, BloqueiosPreferenciaisRule::class);
        $this->app->bind(CargaHorariaExcedidaRule::class, CargaHorariaExcedidaRule::class);
        $this->app->bind(ConflitoProfessorRule::class, ConflitoProfessorRule::class);
        $this->app->bind(ConflitoTurmaRule::class, ConflitoTurmaRule::class);
        $this->app->bind(DistribuicaoRule::class, DistribuicaoRule::class);
        $this->app->bind(JanelasRule::class, JanelasRule::class);
        $this->app->bind(MaxAulasDiaRule::class, MaxAulasDiaRule::class);

        // ✅ ALTERADO: Registro do FitnessEvaluator (antigo FitnessFunction)
        $this->app->singleton(FitnessEvaluator::class, function ($app) {
            // Todas as regras de fitness
            $allRules = [
                $app->make(AulasNaoConsecutivasRule::class),
                $app->make(BloqueiosHardRule::class),
                $app->make(BloqueiosPreferenciaisRule::class),
                $app->make(CargaHorariaExcedidaRule::class),
                $app->make(ConflitoProfessorRule::class),
                $app->make(ConflitoTurmaRule::class),
                $app->make(DistribuicaoRule::class),
                $app->make(JanelasRule::class),
                $app->make(MaxAulasDiaRule::class),
            ];

            // Pesos padrão para as regras. Estes podem ser carregados do DB dinamicamente.
            $fitnessWeights = new FitnessWeights([
                AulasNaoConsecutivasRule::class => 0.8,
                BloqueiosHardRule::class => 100.0, // Penalidade alta para regras hard
                BloqueiosPreferenciaisRule::class => 0.5,
                CargaHorariaExcedidaRule::class => 50.0,
                ConflitoProfessorRule::class => 100.0,
                ConflitoTurmaRule::class => 100.0,
                DistribuicaoRule::class => 0.7,
                JanelasRule::class => 0.6,
                MaxAulasDiaRule::class => 0.9,
            ]);

            return new FitnessEvaluator($allRules, $fitnessWeights);
        });

        // Bind para as interfaces dos operadores
        $this->app->bind(SelectionOperatorInterface::class, TournamentSelection::class);
        $this->app->bind(CrossoverOperatorInterface::class, SinglePointCrossover::class);
        $this->app->bind(MutationOperatorInterface::class, GeneSwapMutation::class);
        $this->app->bind(TerminationCriterionInterface::class, MaxGenerationsOrFitnessCriterion::class);

        // Bind para PopulationGenerator e MetricsRecorder
        $this->app->singleton(PopulationGenerator::class, PopulationGenerator::class);
        $this->app->singleton(MetricsRecorder::class, MetricsRecorder::class);

        // ✅ ADICIONADO: Registro do HorarioGeneticoOrchestrator
        $this->app->singleton(HorarioGeneticoOrchestrator::class, function ($app) {
            return new HorarioGeneticoOrchestrator(
                $app->make(ConfiguracaoHorarioReadRepository::class),
                $app->make(PopulationGenerator::class),
                $app->make(FitnessEvaluator::class),
                $app->make(SelectionOperatorInterface::class),
                $app->make(CrossoverOperatorInterface::class),
                $app->make(MutationOperatorInterface::class),
                $app->make(TerminationCriterionInterface::class),
                $app->make(MetricsRecorder::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null
        );
    }
}
