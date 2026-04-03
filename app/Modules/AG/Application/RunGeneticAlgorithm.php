<?php

declare(strict_types=1);

namespace App\Modules\AG\Application;

use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Evolution\IslandModel\BestIndividualsMigration;
use App\Modules\AG\Domain\Evolution\IslandModel\Island;
use App\Modules\AG\Domain\Evolution\IslandModel\IslandModelEngine;
use App\Modules\AG\Domain\Evolution\IslandModel\IslandProfile;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\HyperHeuristic\LearningHyperHeuristicController;
use App\Modules\AG\Domain\HyperHeuristic\OperatorPerformanceTracker;
use App\Modules\AG\Domain\HyperHeuristic\OperatorRewardCalculator;
use App\Modules\AG\Domain\HyperHeuristic\Strategies\EpsilonGreedySelector;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\Acceptance\StrictScoreImprovementAcceptance;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
use App\Modules\AG\Domain\Intensification\LNS\Conflict\ConflictDetector;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\ClusterDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\ConflictDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\RandomDestroyOperator;
use App\Modules\AG\Domain\Intensification\LNS\Repair\LNSRepairAdapter;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RegretInsertionOperator;
use App\Modules\AG\Domain\Landscape\LandscapeAnalyzer;
use App\Modules\AG\Domain\Landscape\LandscapeDetector;
use App\Modules\AG\Domain\Landscape\LandscapeEngine;
use App\Modules\AG\Domain\Landscape\LandscapeMemory;
use App\Modules\AG\Domain\Landscape\LandscapeResponseStrategy;
use App\Modules\AG\Domain\Metrics\GeneticDistance;
use App\Modules\AG\Domain\Metrics\HashDiversityCalculator;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Metrics\PopulationEntropyCalculator;
use App\Modules\AG\Domain\Metrics\PopulationStatistics;
use App\Modules\AG\Domain\Operators\Adaptive\AdaptiveMutationController;
use App\Modules\AG\Domain\Operators\Crossover\BlockPreservingCrossover;
use App\Modules\AG\Domain\Operators\Crossover\ConflictGraphCrossoverOperator;
use App\Modules\AG\Domain\Operators\Crossover\SinglePointCrossover;
use App\Modules\AG\Domain\Operators\Elitism\TopEliteStrategy;
use App\Modules\AG\Domain\Operators\Mutation\AdaptiveDiversityMutation;
use App\Modules\AG\Domain\Operators\Mutation\ConflictGuidedMutation;
use App\Modules\AG\Domain\Operators\Mutation\GeneSwapMutation;
use App\Modules\AG\Domain\Operators\Mutation\StructuredSwapMutation;
use App\Modules\AG\Domain\Operators\Replacement\AdaptiveNichingReplacement;
use App\Modules\AG\Domain\Operators\Selection\FitnessSharing\FitnessSharingCalculator;
use App\Modules\AG\Domain\Operators\Selection\FitnessSharing\SharingFunction;
use App\Modules\AG\Domain\Operators\Selection\TournamentSelection;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Termination\VarianceBasedTerminationCriterion;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Infrastructure\Parallel\AsyncFitnessEvaluator;
use App\Modules\AG\Infrastructure\Progress\NullProgressReporter;
use App\Modules\AG\Support\DTO\GeneticAlgorithmConfigDTO;
use App\Modules\Horarios\Application\Constraints\ConstraintSolverPayloadMapper;
use App\Modules\Horarios\Application\LoadActiveScheduleConstraintsAction;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Builders\ScheduleDataBuilder;
use App\Modules\Horarios\Domain\Constraints\Evaluators\ConstraintEvaluationPipeline;
use App\Modules\Horarios\Domain\Constraints\Evaluators\MutualExclusionConstraintEvaluator;
use App\Modules\Horarios\Domain\Constraints\Evaluators\SyncSameTimeslotConstraintEvaluator;
use App\Modules\Horarios\Domain\Constraints\Evaluators\TimePlacementConstraintEvaluator;
use App\Modules\Horarios\Domain\Constraints\Repair\CustomConstraintRepairExtension;
use App\Modules\Horarios\Domain\Evaluation\HardRules\ClassConflictRule;
use App\Modules\Horarios\Domain\Evaluation\HardRules\CustomConstraintHardRule;
use App\Modules\Horarios\Domain\Evaluation\HardRules\MandatoryBlockViolationRule;
use App\Modules\Horarios\Domain\Evaluation\HardRules\TeacherConflictRule;
use App\Modules\Horarios\Domain\Evaluation\HardRules\WorkloadExceededRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\ConsecutiveLessonRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\CustomConstraintSoftRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\DistributionRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\MaxLessonsPerDayRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\PreferredTimeRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\WindowPenaltyRule;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class RunGeneticAlgorithm
{
    public function __construct(
        private readonly ScheduleDataBuilder $scheduleDataBuilder,
        private readonly LoadActiveScheduleConstraintsAction $loadActiveScheduleConstraints,
        private readonly ConstraintSolverPayloadMapper $constraintSolverPayloadMapper,
    ) {
    }

    public function execute(Horario $horario, ?ProgressReporterInterface $progress = null, ?ExecutionMetricsRecorder $executionMetrics = null): array
    {
        Log::info('RunGeneticAlgorithm::execute() iniciado');

        $config = GeneticAlgorithmConfigDTO::fromModels($horario);
        $executionMetrics ??= new ExecutionMetricsRecorder();

        // ✅ AÇÃO 06: Sincronizar island_count (UI/backend)
        if ($executionMetrics->hasExecutionId()) {
            // Se execução já existe, recuperar island_count dela
            $executionId = $executionMetrics->getExecutionId();
            $execution = ScheduleExecution::find($executionId);
            $islandCount = ($execution?->island_count) ?? (int) config('ag.islands', 2);
        } else {
            // Caso contrário, usar config como source of truth
            $islandCount = (int) config('ag.islands', 2);

            $executionId = $executionMetrics->startExecution(
                horarioId: $horario->id,
                populationSize: $config->tamanhoPopulacao,
                generations: $config->numeroGeracoes,
                parameters: [
                    'mutation_rate' => $config->taxaMutacao,
                    'elite_count' => $config->eliteCount(),
                    'islands' => $islandCount,
                ],
            );
        }

        $progress = $progress ?? new NullProgressReporter();

        $constraintSnapshots = $this->loadConstraintSnapshots($horario, $executionId);

        $scheduleData = $this->scheduleDataBuilder->build($horario, $constraintSnapshots);

        $maxLessonsPerDay = (int) ($horario->configuracaoHorario?->aulas_por_dia ?? 7);

        $constraintPipeline = new ConstraintEvaluationPipeline([
            new SyncSameTimeslotConstraintEvaluator(),
            new MutualExclusionConstraintEvaluator(),
            new TimePlacementConstraintEvaluator(),
        ]);

        $fitnessRules = [
            new TeacherConflictRule(),
            new ClassConflictRule(),
            new WorkloadExceededRule(),
            new MandatoryBlockViolationRule(),
            new CustomConstraintHardRule($constraintPipeline),
            new WindowPenaltyRule(),
            new DistributionRule(),
            new MaxLessonsPerDayRule($maxLessonsPerDay),
            new ConsecutiveLessonRule(),
            new PreferredTimeRule(),
            new CustomConstraintSoftRule($constraintPipeline),
        ];

        $fitnessEvaluator = new FitnessEvaluator(
            weights: FitnessWeights::default(),
            rules: $fitnessRules,
        );

        $repairOperator = new GreedyRepairOperator([
            new CustomConstraintRepairExtension(),
        ]);

        $problem = new ScheduleProblem(
            data: $scheduleData,
            contextBuilder: new EvaluationContextBuilder(),
            fitnessEvaluator: $fitnessEvaluator,
            repairOperator: $repairOperator,
            progress: $progress,
            executionId: $executionId,
        );

        $distance = new GeneticDistance();

        $sharingFunction = new SharingFunction(sigma: 0.35, alpha: 1.0);

        $sharingCalculator = new FitnessSharingCalculator($distance, $sharingFunction);

        $selection = new TournamentSelection(3, $sharingCalculator);

        $crossover = new ConflictGraphCrossoverOperator();

        $elitism = new TopEliteStrategy(max(1, $config->eliteCount()));

        $termination = new VarianceBasedTerminationCriterion(
            maxGenerations: $config->numeroGeracoes,
            populationStatistics: new PopulationStatistics(
                new HashDiversityCalculator(),
                new PopulationEntropyCalculator(),
            ),
            targetFitness: $config->targetFitness,
            maxGenerationsWithoutImprovement: $config->maxGenerationsWithoutImprovement,
            varianceThreshold: (float) config('ag.termination_variance_threshold', 0.0005),
            varianceWindowSize: (int) config('ag.termination_variance_window', 8),
            minGenerationsBeforeVarianceCheck: (int) config('ag.termination_min_generations_before_variance', 20),
            minDiversity: (float) config('ag.termination_min_diversity', 0.08),
            minEntropy: (float) config('ag.termination_min_entropy', 0.10),
        );

        $islandEngine = new IslandModelEngine(
            migrationPolicy: new BestIndividualsMigration(2),
            migrationInterval: 5,
        );

        $baseLnsFrequency = $this->resolveBaseLnsFrequency($config->numeroGeracoes);

        $metricsGlobal = [];

        for ($i = 0; $i < $islandCount; $i++) {
            // Sprint 4: perfil da ilha determina alpha GRASP e parâmetros de mutação
            $islandProfile = match ($i) {
                0 => IslandProfile::Conservative,
                1 => IslandProfile::Exploratory,
                default => IslandProfile::Balanced,
            };

            $metrics = new MetricsRecorder();
            $metrics->setExecutionId($executionId);
            $metrics->setPopulationStatistics(
                new PopulationStatistics(
                    diversityCalculator: new HashDiversityCalculator(),
                    entropyCalculator: new PopulationEntropyCalculator(),
                    diversitySamplingInterval: 5,
                    diversityCollapseThreshold: 0.05,
                ),
            );

            $mutation = new AdaptiveDiversityMutation(
                structured: new StructuredSwapMutation(),
                swap: new GeneSwapMutation(),
                conflict: new ConflictGuidedMutation(maxDias: 5, maxPeriodosPorDia: 6),
            );

            $tracker = new OperatorPerformanceTracker();
            $rewardCalculator = new OperatorRewardCalculator();
            $selectionStrategy = new EpsilonGreedySelector(epsilon: 0.15);

            $hyperHeuristic = new LearningHyperHeuristicController(
                $tracker,
                $selectionStrategy,
                $rewardCalculator,
            );

            $hyperHeuristic->registerOperators([
                new StructuredSwapMutation(),
                new GeneSwapMutation(),
                new ConflictGuidedMutation(maxDias: 5, maxPeriodosPorDia: 6),
                new SinglePointCrossover(),
                new BlockPreservingCrossover(),
                new RandomDestroyOperator(),
                new ConflictDestroyOperator(new ConflictDetector()),
                new ClusterDestroyOperator(),
                new RegretInsertionOperator($scheduleData),
            ]);

            $adaptiveMutation = new AdaptiveMutationController(
                baseRate:      $islandProfile->mutationBaseRate(),
                amplification: $islandProfile->mutationAmplification(),
                maxRate:       $islandProfile->mutationMaxRate(),
            );

            // ✅ AÇÃO 07: Condicional parallel evaluation por population_size threshold
            $parallelEvaluationEnabled = (bool) config('ag.parallel_evaluation', true);
            $parallelEvaluationThreshold = (int) config('ag.parallel_evaluation_threshold', 200);
            $maxWorkers = (int) config('ag.max_workers', 8);

            // Usar avaliação paralela apenas se population_size > threshold
            $useParallelEvaluation = $parallelEvaluationEnabled && ($config->tamanhoPopulacao > $parallelEvaluationThreshold);

            $populationEvaluator = $useParallelEvaluation
                ? new AsyncFitnessEvaluator(problem: $problem, concurrency: $maxWorkers)
                : new PopulationFitnessEvaluator(problem: $problem, concurrency: $maxWorkers);

            $replacement = new AdaptiveNichingReplacement(new GeneticDistance());

            $lns = new AdaptiveLargeNeighborhoodSearch(
                [
                    new RandomDestroyOperator(),
                    new ConflictDestroyOperator(new ConflictDetector()),
                    new ClusterDestroyOperator(),
                ],
                [
                    new LNSRepairAdapter($repairOperator, $scheduleData),
                    new RegretInsertionOperator($scheduleData),
                ],
            );

            $alnsAcceptance = new StrictScoreImprovementAcceptance();

            $landscapeEngine = new LandscapeEngine(
                new LandscapeAnalyzer(),
                new LandscapeDetector(),
                new LandscapeResponseStrategy(),
                new LandscapeMemory(),
            );

            $engine = new GeneticAlgorithmEngine(
                alnsAcceptance: $alnsAcceptance,
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
                hyperHeuristic: $hyperHeuristic,
                lns: $lns,
                progress: $progress,
                lnsFrequency: $baseLnsFrequency,
                executionMetrics: $executionMetrics,
                landscapeEngine: $landscapeEngine,
            );

            // Sprint 4: propaga perfil de ilha para o ScheduleProblem via engine
            $engine->setIslandProfile($islandProfile);

            $islandEngine->addIsland(
                new Island(
                    $i + 1,
                    engine: $engine,
                    populationSize: $config->tamanhoPopulacao,
                    replacement: $replacement,
                ),
            );

            $metricsGlobal[] = $metrics;
        }

        // Sprint 4: log de perfis configurados por ilha
        Log::info('solver.islands.profiles_configured', [
            'execution_id' => $executionId,
            'island_count' => $islandCount,
            'profiles' => array_map(
                fn (int $idx) => match ($idx) {
                    0 => IslandProfile::Conservative->value,
                    1 => IslandProfile::Exploratory->value,
                    default => IslandProfile::Balanced->value,
                },
                range(0, $islandCount - 1),
            ),
        ]);

        $islandEngine->setTelemetry($metricsGlobal[0], $progress, $config->taxaMutacao);
        $islandEngine->setExecutionId($executionId);

        $best = $islandEngine->run($config->numeroGeracoes);
        $best = $this->finalizeBestSolution($best, $problem);

        return [
            'best' => $best,
            'best_fitness' => $best->fitness(),
            'generation_metrics' => array_map(
                fn ($m) => $m->generationData(),
                $metricsGlobal,
            ),
        ];
    }

    /**
     * @return array<int, CustomConstraintData>
     */
    private function loadConstraintSnapshots(Horario $horario, ?int $executionId): array
    {
        $constraints = $this->loadActiveScheduleConstraints->execute($horario->id);
        $snapshots = $this->constraintSolverPayloadMapper->mapCollection($constraints);

        $typeBreakdown = [];

        foreach ($snapshots as $snapshot) {
            $typeBreakdown[$snapshot->type] = ($typeBreakdown[$snapshot->type] ?? 0) + 1;
        }

        Log::info('solver.custom_constraints.loaded', [
            'horario_id' => $horario->id,
            'execution_id' => $executionId,
            'constraint_count' => count($snapshots),
            'type_breakdown' => $typeBreakdown,
        ]);

        return $snapshots;
    }

    private function finalizeBestSolution(Cromossomo $best, ScheduleProblem $problem): Cromossomo
    {
        $candidate = $best->copy();
        $attempts = 3;
        $lastHardPenalty = INF;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $candidate = $problem->repairWithTelemetry(
                $candidate,
                reportProgress: true,
                source: 'final_repair',
            );

            $result = $problem->evaluate($candidate);
            $lastHardPenalty = $result->hardPenalty();
            $repairTelemetry = $problem->lastRepairTelemetry();

            if ($result->hardPenalty() <= 0.0 && $problem->isFeasible($candidate)) {
                Log::info('solver.final_repair_succeeded', [
                    'attempt' => $attempt,
                    'hard_penalty' => $result->hardPenalty(),
                    'soft_penalty' => $result->softPenalty(),
                    'score' => $result->score(),
                    'repair' => $repairTelemetry,
                ]);

                return $candidate;
            }

            Log::warning('solver.final_repair_attempt_failed', [
                'attempt' => $attempt,
                'hard_penalty' => $result->hardPenalty(),
                'soft_penalty' => $result->softPenalty(),
                'score' => $result->score(),
                'repair' => $repairTelemetry,
            ]);
        }

        throw new RuntimeException(sprintf(
            'Solver finalizou sem solucao viavel apos reparo final (hard_penalty=%.4f).',
            $lastHardPenalty,
        ));
    }

    private function resolveBaseLnsFrequency(int $maxGenerations): int
    {
        $configured = (int) config('ag.lns_frequency', 50);

        return min(
            max(2, $configured),
            max(2, (int) ceil(max(1, $maxGenerations) / 2)),
        );
    }
}
