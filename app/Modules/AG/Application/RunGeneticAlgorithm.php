<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Models\Horario;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Evolution\IslandModel\BestIndividualsMigration;
use App\Modules\AG\Domain\Evolution\IslandModel\Island;
use App\Modules\AG\Domain\Evolution\IslandModel\IslandModelEngine;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
use App\Modules\AG\Domain\Intensification\LNS\Conflict\ConflictDetector;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\ClusterDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\ConflictDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\RandomDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Repair\LNSRepairAdapter;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RegretInsertionOperator;
use App\Modules\AG\Domain\Metrics\HammingDiversityCalculator;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Metrics\PopulationEntropyCalculator;
use App\Modules\AG\Domain\Operators\Adaptive\AdaptiveMutationController;
use App\Modules\AG\Domain\Operators\Crossover\ConflictGraphCrossoverOperator;
use App\Modules\AG\Domain\Operators\Elitism\TopEliteStrategy;
use App\Modules\AG\Domain\Operators\Mutation\AdaptiveDiversityMutation;
use App\Modules\AG\Domain\Operators\Mutation\ConflictGuidedMutation;
use App\Modules\AG\Domain\Operators\Mutation\GeneSwapMutation;
use App\Modules\AG\Domain\Operators\Mutation\StructuredSwapMutation;
use App\Modules\AG\Domain\Operators\Replacement\WorstIndividualReplacement;
use App\Modules\AG\Domain\Operators\Selection\TournamentSelection;
use App\Modules\AG\Domain\Termination\MaxGenerationsOrFitnessCriterion;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Builders\ScheduleDataBuilder;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;


final class RunGeneticAlgorithm {
    public function execute(Horario $horario, ?ProgressReporterInterface $progress = null): array {

        /* ============================================================
         | 1️⃣ Construção dos dados do problema
         ============================================================ */

        $dataBuilder = new ScheduleDataBuilder();

        $scheduleData = $dataBuilder->build($horario);

        /* ============================================================
         | 2️⃣ Fitness
         ============================================================ */

        $fitnessEvaluator = new FitnessEvaluator(weights: FitnessWeights::default(), rules: []);

        $repairOperator = new GreedyRepairOperator();
        /* ============================================================
         | 3️⃣ Problema específico
         ============================================================ */

        $problem = new ScheduleProblem(data: $scheduleData, contextBuilder: new EvaluationContextBuilder(), fitnessEvaluator: $fitnessEvaluator, repairOperator: $repairOperator);

        /* ============================================================
         | 4️⃣ Operadores base
         ============================================================ */

        $selection = new TournamentSelection(3);

        $crossover = new ConflictGraphCrossoverOperator();

        $elitism = new TopEliteStrategy(3);

        $termination = new MaxGenerationsOrFitnessCriterion(
            maxGenerations: 500,
            targetFitness: 100.0,
            maxGenerationsWithoutImprovement: 120
        );

        /* ============================================================
         | 5️⃣ Configuração Island Model
         ============================================================ */

        $islandCount = 4;
        $populationSize = 120;
        $migrationInterval = 25;

        $islandEngine = new IslandModelEngine(
            migrationPolicy: new BestIndividualsMigration(2),
            migrationInterval: $migrationInterval
        );

        $metricsGlobal = [];

        /* ============================================================
         | 6️⃣ Criação das ilhas
         ============================================================ */

        for ($i = 0; $i < $islandCount; $i++) {

            $metrics = new MetricsRecorder();

            $metrics->setDiversityCalculator(new HammingDiversityCalculator());

            $metrics->setEntropyCalculator(new PopulationEntropyCalculator());

            $mutation = new AdaptiveDiversityMutation(
                structured: new StructuredSwapMutation(),
                swap: new GeneSwapMutation(),
                conflict: new ConflictGuidedMutation(
                    maxDias: 5,
                    maxPeriodosPorDia: 6
                )
            );

            $adaptiveMutation = new AdaptiveMutationController(baseRate: 0.02, amplification: 0.25, maxRate: 0.35);

            $populationEvaluator = new PopulationFitnessEvaluator(problem: $problem, concurrency: config('ag.max_workers', 8));

            $replacement = new WorstIndividualReplacement(2);

            $conflictDetector = new ConflictDetector();

            $destroyOperators = [
                new RandomDestroyOperator(),
                new ConflictDestroyOperator($conflictDetector),
                new ClusterDestroyOperator()
            ];

            $repairOperators = [
                new LNSRepairAdapter($repairOperator, $scheduleData),
                new RegretInsertionOperator($scheduleData)
            ];

            $lns = new AdaptiveLargeNeighborhoodSearch($destroyOperators, $repairOperators);

            $engine = new GeneticAlgorithmEngine(
                problem: $problem,
                selection: $selection,
                crossover: $crossover,
                mutation: $mutation,
                termination: $termination,
                metrics: $metrics,
                elitism: $elitism,
                adaptiveMutation: $adaptiveMutation,
                populationEvaluator: $populationEvaluator,
                replacement: $replacement,
                lns: $lns,
                progress: $progress,
                lnsFrequency: 50
            );

            $islandEngine->addIsland(
                new Island(
                    engine: $engine,
                    populationSize: $populationSize,
                    replacement: $replacement
                )
            );

            $metricsGlobal[] = $metrics;
        }
        /* ============================================================
         | 7️⃣ Execução global
         ============================================================ */

        $best = $islandEngine->run(500);

        /* ============================================================
         | 8️⃣ Consolidação de métricas
         ============================================================ */

        $generationMetrics = [];

        foreach ($metricsGlobal as $metrics) {
            $generationMetrics[] = $metrics->generationData();
        }

        /* ============================================================
         | 9️⃣ Resultado estruturado
         ============================================================ */

        return [
            'best' => $best,
            'best_fitness' => $best->fitness(),
            'generation_metrics' => $generationMetrics,
        ];
    }
}
