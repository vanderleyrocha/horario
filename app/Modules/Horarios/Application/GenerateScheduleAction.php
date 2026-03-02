<?php

namespace App\Modules\Horarios\Application;

use App\Models\Horario;
use App\Modules\AG\Application\GeneticAlgorithmEngine;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Operators\BlockPreservingCrossover;
use App\Modules\AG\Domain\Operators\ConflictGuidedMutation;
use App\Modules\AG\Domain\Operators\TournamentSelection;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Termination\MaxGenerationsOrFitnessCriterion;
use App\Modules\AG\Infrastructure\Population\PopulationGenerator;
use App\Modules\AG\Support\DTO\GeneticAlgorithmConfigDTO;

final class GenerateScheduleAction {
    public function execute(
        Horario $horario,
        ?ProgressReporterInterface $progressReporter = null
    ): array {

        /* ============================================================
         | 1️⃣ Configuração
         ============================================================ */

        $config = GeneticAlgorithmConfigDTO::fromModels($horario);

        /* ============================================================
         | 2️⃣ População Inicial
         ============================================================ */

        $aulas = $horario->aulas()
            ->with(['professor', 'turma', 'disciplina'])
            ->get()
            ->all();

        $populationGenerator = new PopulationGenerator(
            aulas: $aulas,
            config: $config,
            progressReporter: $progressReporter
        );

        $initialPopulation = $populationGenerator->generate();

        /* ============================================================
         | 3️⃣ Fitness
         ============================================================ */

        $fitnessEvaluator = new FitnessEvaluator(
            weights: new FitnessWeights([]),
            rules: []
        );

        /* ============================================================
         | 4️⃣ Operadores
         ============================================================ */

        $selection = new TournamentSelection(5);

        $crossover = new BlockPreservingCrossover();

        // ✅ CORREÇÃO: Mutation compatível
        $mutation = new ConflictGuidedMutation(
            maxDias: $config->diasSemana,
            maxPeriodosPorDia: $config->aulasPorDia
        );

        // ✅ CORREÇÃO: Repair compatível
        $repair = new GreedyRepairOperator(
            horariosDisponiveis: $config->horariosDisponiveis,
            aulasPorDia: $config->aulasPorDia
        );

        $termination = new MaxGenerationsOrFitnessCriterion(
            maxGenerations: $config->numeroGeracoes,
            targetFitness: 100.0,
            maxGenerationsWithoutImprovement: $config->limiteEstagnacao
        );

        $metrics = new MetricsRecorder();

        /* ============================================================
         | 5️⃣ Engine
         ============================================================ */

        $engine = new GeneticAlgorithmEngine(
            fitnessEvaluator: $fitnessEvaluator,
            selectionOperator: $selection,
            crossoverOperator: $crossover,
            mutationOperator: $mutation,
            terminationCriterion: $termination,
            repairOperator: $repair,
            metricsRecorder: $metrics,
            progressReporter: $progressReporter
        );

        $best = $engine->run(
            initialPopulation: $initialPopulation,
            config: $config
        );

        /* ============================================================
         | 6️⃣ Retorno estruturado
         ============================================================ */

        return [
            'best' => $best,
            'best_fitness' => $metrics->bestFitnessOverall(),
            'generation_metrics' => $metrics->generationData(),
        ];
    }
}
