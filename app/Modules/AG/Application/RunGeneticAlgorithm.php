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
use App\Modules\AG\Domain\HyperHeuristic\LearningHyperHeuristicController;
use App\Modules\AG\Domain\HyperHeuristic\OperatorPerformanceTracker;
use App\Modules\AG\Domain\HyperHeuristic\OperatorRewardCalculator;
use App\Modules\AG\Domain\HyperHeuristic\Strategies\EpsilonGreedySelector;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
use App\Modules\AG\Domain\Intensification\LNS\Conflict\ConflictDetector;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\ClusterDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\ConflictDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\RandomDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Repair\LNSRepairAdapter;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RegretInsertionOperator;
use App\Modules\AG\Domain\Metrics\GeneticDistance;
use App\Modules\AG\Domain\Metrics\HashDiversityCalculator;
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
use App\Modules\AG\Domain\Operators\Selection\FitnessSharing\FitnessSharingCalculator;
use App\Modules\AG\Domain\Operators\Selection\FitnessSharing\SharingFunction;
use App\Modules\AG\Domain\Operators\Selection\TournamentSelection;
use App\Modules\AG\Domain\Termination\MaxGenerationsOrFitnessCriterion;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Builders\ScheduleDataBuilder;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;
use Illuminate\Support\Facades\Log;

final class RunGeneticAlgorithm
{
    public function execute(Horario $horario, ?ProgressReporterInterface $progress = null): array
    {

        Log::info("RunGeneticAlgorithm::execute() iniciado");

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


        $distance = new GeneticDistance();

        $sharingFunction = new SharingFunction(sigma: 0.35, alpha: 1.0);

        $sharingCalculator = new FitnessSharingCalculator($distance, $sharingFunction);

        $selection = new TournamentSelection(3, $sharingCalculator);


        $crossover = new ConflictGraphCrossoverOperator();

        $elitism = new TopEliteStrategy(3);

        $termination = new MaxGenerationsOrFitnessCriterion(maxGenerations: 500, targetFitness: 100.0, maxGenerationsWithoutImprovement: 120);

        /* ============================================================
         | 5️⃣ Configuração Island Model
         ============================================================ */

        $islandCount = 4;
        $populationSize = 120;
        $migrationInterval = 25;

        $islandEngine = new IslandModelEngine(migrationPolicy: new BestIndividualsMigration(2), migrationInterval: $migrationInterval);

        $metricsGlobal = [];

        /* ============================================================
         | 6️⃣ Criação das ilhas
         ============================================================ */

        for ($i = 0; $i < $islandCount; $i++) {
            Log::info("Ilha {$i} iniciada");
            $metrics = new MetricsRecorder();


            $metrics->setDiversityCalculator(new HashDiversityCalculator());


            $metrics->setEntropyCalculator(new PopulationEntropyCalculator());

            $mutation = new AdaptiveDiversityMutation(structured: new StructuredSwapMutation(), swap: new GeneSwapMutation(), conflict: new ConflictGuidedMutation(maxDias: 5, maxPeriodosPorDia: 6));


            $tracker = new OperatorPerformanceTracker();

            $rewardCalculator = new OperatorRewardCalculator();

            $selectionStrategy = new EpsilonGreedySelector(epsilon: 0.15);

            $hyperHeuristic = new LearningHyperHeuristicController($tracker, $selectionStrategy, $rewardCalculator);


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


            $engine = new GeneticAlgorithmEngine(problem: $problem, selection: $selection, crossover: $crossover, mutation: $mutation, termination: $termination, metrics: $metrics, elitism: $elitism, adaptiveMutation: $adaptiveMutation, populationEvaluator: $populationEvaluator, replacement: $replacement, hyperHeuristic: $hyperHeuristic, lns: $lns, progress: $progress, lnsFrequency: 50);


            $islandEngine->addIsland(new Island($i + 1, engine: $engine, populationSize: $populationSize, replacement: $replacement));

            $metricsGlobal[] = $metrics;
        }

        Log::info("RunGeneticAlgorithm::execute() finalizado: Ilhas criadas com sucesso!");

        /* ============================================================
         | 7️⃣ Conexão de Telemetria e Execução Global
         ============================================================ */

        // Injeta o ProgressReporter e o primeiro MetricsRecorder para monitorar o cenário global
        $islandEngine->setTelemetry($metricsGlobal[0], $progress);

        // Roda as gerações (Agora o painel vai atualizar a cada ciclo!)
        $best = $islandEngine->run(50);

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
