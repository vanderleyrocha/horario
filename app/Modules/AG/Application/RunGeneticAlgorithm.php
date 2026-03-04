<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Models\Horario;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;

use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Metrics\HammingDiversityCalculator;

use App\Modules\AG\Domain\Operators\TournamentSelection;
use App\Modules\AG\Domain\Operators\BlockPreservingCrossover;
use App\Modules\AG\Domain\Operators\StructuredSwapMutation;
use App\Modules\AG\Domain\Operators\TopEliteStrategy;

use App\Modules\AG\Domain\Termination\MaxGenerationsOrFitnessCriterion;

use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Builders\ScheduleDataBuilder;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;

final class RunGeneticAlgorithm {
    public function execute(
        Horario $horario,
        ?ProgressReporterInterface $progress = null
    ): array {

        /* ============================================================
         | 1️⃣ Construção dos dados do problema
         ============================================================ */

        $dataBuilder = new ScheduleDataBuilder();

        $scheduleData = $dataBuilder->build($horario);

        /* ============================================================
         | 2️⃣ Fitness
         ============================================================ */

        $fitnessEvaluator = new FitnessEvaluator(
            weights: FitnessWeights::default(),
            rules: [] // Rules serão injetadas futuramente
        );

        /* ============================================================
         | 3️⃣ Problema específico de horário
         ============================================================ */

        $problem = new ScheduleProblem(
            data: $scheduleData,
            contextBuilder: new EvaluationContextBuilder(),
            fitnessEvaluator: $fitnessEvaluator
        );

        /* ============================================================
         | 4️⃣ Operadores do AG
         ============================================================ */

        $selection = new TournamentSelection(3);

        $crossover = new BlockPreservingCrossover();

        $mutation = new StructuredSwapMutation();

        $elitism = new TopEliteStrategy(
            eliteCount: 3
        );

        /* ============================================================
         | 5️⃣ Critério de parada
         ============================================================ */

        $termination = new MaxGenerationsOrFitnessCriterion(
            maxGenerations: 500,
            targetFitness: 100.0,
            maxGenerationsWithoutImprovement: 120
        );

        /* ============================================================
         | 6️⃣ Métricas
         ============================================================ */

        $metrics = new MetricsRecorder();

        $metrics->setDiversityCalculator(
            new HammingDiversityCalculator()
        );

        /* ============================================================
         | 7️⃣ Engine
         ============================================================ */

        $engine = new GeneticAlgorithmEngine(
            problem: $problem,
            selection: $selection,
            crossover: $crossover,
            mutation: $mutation,
            termination: $termination,
            metrics: $metrics,
            elitism: $elitism,
            progress: $progress
        );

        /* ============================================================
         | 8️⃣ Execução
         ============================================================ */

        $populationSize = 120;

        $best = $engine->run($populationSize);

        /* ============================================================
         | 9️⃣ Resultado estruturado
         ============================================================ */

        return [
            'best' => $best,
            'best_fitness' => $best->fitness(),
            'generation_metrics' => $metrics->generationData(),
        ];
    }
}
