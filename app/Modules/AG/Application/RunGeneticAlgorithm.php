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
use App\Modules\AG\Infrastructure\Progress\NullProgressReporter;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Support\DTO\GeneticAlgorithmConfigDTO;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Builders\ScheduleDataBuilder;
use App\Modules\Horarios\Domain\Evaluation\HardRules\ClassConflictRule;
use App\Modules\Horarios\Domain\Evaluation\HardRules\MandatoryBlockViolationRule;
use App\Modules\Horarios\Domain\Evaluation\HardRules\TeacherConflictRule;
use App\Modules\Horarios\Domain\Evaluation\HardRules\WorkloadExceededRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\ConsecutiveLessonRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\DistributionRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\MaxLessonsPerDayRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\PreferredTimeRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\WindowPenaltyRule;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;
use Illuminate\Support\Facades\Log;

final class RunGeneticAlgorithm
{
    public function execute(Horario $horario, ?ProgressReporterInterface $progress = null): array
    {
        Log::info("RunGeneticAlgorithm::execute() iniciado");

        $config = GeneticAlgorithmConfigDTO::fromModels($horario);
        $executionMetrics = null;

        $progress = $progress ?? new NullProgressReporter();

        /*
        =============================================================
        1️⃣ Construção dos dados
        =============================================================
        */

        $dataBuilder = new ScheduleDataBuilder();
        $scheduleData = $dataBuilder->build($horario);

        /*
        =============================================================
        2️⃣ Fitness
        =============================================================
        */

        $fitnessEvaluator = new FitnessEvaluator(weights: FitnessWeights::default(), rules: [
            new TeacherConflictRule(),
            new ClassConflictRule(),
            new WorkloadExceededRule(),
            new MandatoryBlockViolationRule(),
            new WindowPenaltyRule(),
            new DistributionRule(),
            new MaxLessonsPerDayRule($config->aulasPorDia),
            new ConsecutiveLessonRule(),
            new PreferredTimeRule(),
        ]);

        $repairOperator = new GreedyRepairOperator();

        /*
        =============================================================
        3️⃣ Problema
        =============================================================
        */

        $problem = new ScheduleProblem(data: $scheduleData, contextBuilder: new EvaluationContextBuilder(), fitnessEvaluator: $fitnessEvaluator, repairOperator: $repairOperator);

        /*
        =============================================================
        4️⃣ Operadores
        =============================================================
        */

        $distance = new GeneticDistance();

        $sharingFunction = new SharingFunction(sigma: 0.35, alpha: 1.0);

        $sharingCalculator = new FitnessSharingCalculator($distance, $sharingFunction);

        $selection = new TournamentSelection(3, $sharingCalculator);

        $crossover = new ConflictGraphCrossoverOperator();

        $elitism = new TopEliteStrategy(max(1, $config->eliteCount()));

        $termination = new MaxGenerationsOrFitnessCriterion(
            maxGenerations: $config->numeroGeracoes,
            targetFitness: $config->targetFitness,
            maxGenerationsWithoutImprovement: $config->maxGenerationsWithoutImprovement
        );

        /*
        =============================================================
        5️⃣ Island Model
        =============================================================
        */

        $islandCount = 2;
        $populationSize = $config->tamanhoPopulacao;
        $migrationInterval = 25;

        $islandEngine = new IslandModelEngine(migrationPolicy: new BestIndividualsMigration(2), migrationInterval: $migrationInterval);

        $metricsGlobal = [];

        /*
        =============================================================
        6️⃣ Criar ilhas
        =============================================================
        */

        for ($i = 0; $i < $islandCount; $i++) {

            Log::info("Ilha {$i} iniciada");

            $metrics = new MetricsRecorder();

            $metrics->setExecutionId($horario->id);


            $metrics->setDiversityCalculator(new HashDiversityCalculator());
            $metrics->setEntropyCalculator(new PopulationEntropyCalculator());

            $metricsGlobal[] = $metrics;

            $mutation = new AdaptiveDiversityMutation(structured: new StructuredSwapMutation(), swap: new GeneSwapMutation(), conflict: new ConflictGuidedMutation(maxDias: 5, maxPeriodosPorDia: 6));

            /*
            Hyper Heuristic
            */

            $tracker = new OperatorPerformanceTracker();

            $rewardCalculator = new OperatorRewardCalculator();

            $selectionStrategy = new EpsilonGreedySelector(epsilon: 0.15);

            $hyperHeuristic = new LearningHyperHeuristicController($tracker, $selectionStrategy, $rewardCalculator);

            /*
            Mutation controller
            */

            $adaptiveMutation = new AdaptiveMutationController(baseRate: 0.02, amplification: 0.25, maxRate: 0.35);

            /*
            Population evaluator
            */

            $populationEvaluator = new PopulationFitnessEvaluator(problem: $problem, concurrency: config('ag.max_workers', 8));

            $replacement = new WorstIndividualReplacement(2);

            /*
            ALNS
            */

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

            /*
            GA engine
            */


            $engine = new GeneticAlgorithmEngine(problem: $problem, selection: $selection, crossover: $crossover, mutation: $mutation, termination: $termination, metrics: $metrics, elitism: $elitism, adaptiveMutation: $adaptiveMutation, populationEvaluator: $populationEvaluator, replacement: $replacement, hyperHeuristic: $hyperHeuristic, lns: $lns, progress: $progress, lnsFrequency: 50, executionMetrics: $executionMetrics);


            $islandEngine->addIsland(new Island($i + 1, engine: $engine, populationSize: $populationSize, replacement: $replacement));
        }

        Log::info("Ilhas criadas com sucesso!");

        /*
        =============================================================
        7️⃣ Telemetria
        =============================================================
        */

        $islandEngine->setTelemetry($metricsGlobal[0], $progress, $config->taxaMutacao);

        /*
        =============================================================
        8️⃣ Execução
        =============================================================
        */

        $best = $islandEngine->run($config->numeroGeracoes);

        /*
        =============================================================
        9️⃣ Consolidar métricas
        =============================================================
        */

        $generationMetrics = [];

        foreach ($metricsGlobal as $metrics) {
            $generationMetrics[] = $metrics->generationData();
        }

        /*
        =============================================================
        🔟 Resultado
        =============================================================
        */

        return [
            'best' => $best,
            'best_fitness' => $best->fitness(),
            'generation_metrics' => $generationMetrics,
        ];
    }
}
