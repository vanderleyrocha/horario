<?php

declare(strict_types=1);

use App\Modules\AG\Application\GeneticAlgorithmEngine;
use App\Modules\AG\Application\PopulationFitnessEvaluator;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\OperatorSelectionStrategy;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\DestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RepairOperatorInterface;
use App\Modules\AG\Domain\Landscape\LandscapeEngine;
use App\Modules\AG\Domain\Metrics\GeneticDistance;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Operators\Adaptive\AdaptiveMutationController;
use App\Modules\AG\Domain\Operators\Crossover\CrossoverOperatorInterface;
use App\Modules\AG\Domain\Operators\Elitism\ElitismStrategyInterface;
use App\Modules\AG\Domain\Operators\Mutation\MutationOperatorInterface;
use App\Modules\AG\Domain\Operators\Replacement\ReplacementStrategyInterface;
use App\Modules\AG\Domain\Operators\Selection\FitnessSharing\FitnessSharingCalculator;
use App\Modules\AG\Domain\Operators\Selection\FitnessSharing\SharingFunction;
use App\Modules\AG\Domain\Operators\Selection\SelectionOperatorInterface;
use App\Modules\AG\Domain\Operators\Selection\TournamentSelection;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\AG\Domain\Termination\TerminationCriterionInterface;
use App\Modules\AG\Support\AGError;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('runs the standalone evolution path without using an undefined operator when mutation does not happen', function (): void {
    $problem = makeStandaloneFakeProblem();
    $mutation = makeCountingMutationOperator();

    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: $mutation,
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $best = $engine->run(4);

    expect($best)->toBeInstanceOf(Cromossomo::class)
        ->and($mutation->calls)->toBe(0);
});

it('logs a single structured summary for the initial population instead of one entry per individual', function (): void {
    Log::spy();

    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 0),
    );

    $engine->run(3);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'ga.population.initialized'
                && ($context['population_size'] ?? null) === 3
                && count($context['sample_gene_counts'] ?? []) === 3
                && array_key_exists('avg_grasp_attempts_per_individual', $context)
                && array_key_exists('quality_gate_success_rate', $context)
                && array_key_exists('initial_population_avg_score', $context)
                && array_key_exists('best_fitness', $context)
                && array_key_exists('avg_fitness', $context);
        })
        ->once();

    Log::shouldNotHaveReceived('info', ['Criando indivÃ­duo 0']);
    Log::shouldNotHaveReceived('info', ['Criando indivÃ­duo 1']);
    Log::shouldNotHaveReceived('info', ['Criando indivÃ­duo 2']);
});

it('reuses the shared generation step in evolveGeneration without emitting mutation telemetry when nothing mutates', function (): void {
    $problem = makeStandaloneFakeProblem();
    $mutation = makeCountingMutationOperator();
    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: $mutation,
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $population = [
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
    ];

    (new PopulationFitnessEvaluator($problem))->evaluate($population);

    $nextPopulation = $engine->evolveGeneration($population, 4);
    $telemetry = $engine->lastEvolutionTelemetry();

    expect($nextPopulation)->toHaveCount(4)
        ->and($mutation->calls)->toBe(0)
        ->and($telemetry)->toMatchArray([
            'operator_used' => 'none',
            'operator_reward' => 0.0,
            'mutation_rate' => 0.0,
        ]);
});

it('publishes the same structured progress payload for the frontend through the dedicated generation publisher', function (): void {
    $progress = makeCollectingProgressReporter();
    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
        progress: $progress,
    );

    $engine->run(4);

    $generationReport = collect($progress->reports)
        ->first(fn (array $payload): bool => ($payload['best_fitness'] ?? null) !== null);
    $operationalStages = collect($progress->reports)
        ->map(fn (array $payload): string => (string) ($payload['stage'] ?? ''))
        ->filter()
        ->values()
        ->all();

    expect($progress->reports)->toHaveCount(4)
        ->and($generationReport)->toBeArray()
        ->and($generationReport)->toMatchArray([
            'phase' => 'evolution',
            'generation' => 0,
            'max_generations' => 1,
            'mutation_rate' => 0.0,
            'stagnation' => 0,
            'landscape_state' => 'unknown',
        ])
        ->and($generationReport)->toHaveKeys([
            'best_fitness',
            'avg_fitness',
            'diversity',
            'entropy',
        ])
        ->and($operationalStages)->toContain('generation_started', 'evaluating_population');
});

it('logs the current long-running operation when a generation stage exceeds five minutes', function (): void {
    Log::spy();

    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
        progress: makeCollectingProgressReporter(),
    );

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'reportOperationalHeartbeat');
    $method->setAccessible(true);

    $method->invoke(
        $engine,
        3,
        'building_offspring',
        'Montando descendentes da geracao',
        microtime(true) - 301,
        [
            'population_target' => 20,
            'offspring_built' => 11,
        ],
        true,
    );

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'ga.execution.long_running_operation'
                && ($context['stage'] ?? null) === 'building_offspring'
                && ($context['operation_label'] ?? null) === 'Montando descendentes da geracao'
                && ($context['heartbeat_policy_seconds'] ?? null) === 30
                && ($context['log_threshold_seconds'] ?? null) === 300;
        });
});

it('triggers alns adaptively in short runs and publishes trigger telemetry', function (): void {
    $progress = makeCollectingProgressReporter();
    $problem = makeStandaloneFakeProblem();
    $lns = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [makeStandaloneFakeDestroyOperator('AdaptiveDestroy')],
        repairOperators: [makeStandaloneFakeRepairOperator('AdaptiveRepair')],
        selector: makeStandaloneFixedAlnsSelectionStrategy(['AdaptiveDestroy', 'AdaptiveRepair']),
    );

    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 5),
        progress: $progress,
        lns: $lns,
        lnsFrequency: 50,
    );

    $engine->run(4);

    $alnsReports = array_values(array_filter(
        $progress->reports,
        static fn (array $payload): bool => ($payload['alns_triggered'] ?? false) === true,
    ));

    expect($alnsReports)->not->toBeEmpty()
        ->and($alnsReports[0]['alns_trigger_reason'] ?? null)->toBe('budget_interval')
        ->and($alnsReports[0]['alns_effective_frequency'] ?? null)->toBe(2)
        ->and($alnsReports[0]['alns_destroy_operator'] ?? null)->toBe('AdaptiveDestroy')
        ->and($alnsReports[0]['alns_repair_operator'] ?? null)->toBe('AdaptiveRepair')
        ->and($alnsReports[0]['landscape_observation']['alns_trigger']['response']['aggression_label'] ?? null)->toBeString()
        ->and($alnsReports[0]['landscape_observation']['alns_trigger']['response']['destroy_ratio'] ?? null)->toBeFloat()
        ->and($alnsReports[0]['landscape_observation']['alns_trigger']['response']['recent_sample_size'] ?? null)->toBeInt();
});

it('activates temporary intensive alns via activation gate in opt-in mode before the regular interval', function (): void {
    config()->set('ag.search_response_activation.enable_temporary_intensive_alns', true);
    config()->set('ag.search_response_activation.temporary_intensive_alns_cooldown', 2);

    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
        lns: new AdaptiveLargeNeighborhoodSearch(
            destroyOperators: [makeStandaloneFakeDestroyOperator('AdaptiveDestroy')],
            repairOperators: [makeStandaloneFakeRepairOperator('AdaptiveRepair')],
            selector: makeStandaloneFixedAlnsSelectionStrategy(['AdaptiveDestroy', 'AdaptiveRepair']),
        ),
        lnsFrequency: 50,
    );

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'buildAlnsTriggerTelemetry');
    $method->setAccessible(true);

    $telemetry = $method->invoke(
        $engine,
        1,
        'exploration',
        [
            'phenomenon' => 'neutral',
            'basin_of_attraction_lock_detected' => false,
            'search_response_simulation' => [
                'policy' => 'basin_lock_escape',
                'would_escalate' => true,
                'activate_alns' => true,
            ],
            'search_response_activation_gate' => [
                'eligible_as_candidate' => true,
                'candidate_policy' => 'basin_lock_escape',
                'mode' => 'diagnostic_only',
            ],
        ],
        false,
    );

    expect($telemetry)->toMatchArray([
        'alns_triggered' => true,
        'alns_trigger_reason' => 'activation_gate',
        'alns_real_activation_enabled' => true,
        'alns_real_activation_requested' => true,
        'alns_real_activation_applied' => true,
        'alns_real_activation_policy' => 'basin_lock_escape',
        'alns_real_activation_mode' => 'opt_in',
    ])
        ->and($telemetry['alns_trigger_sources'] ?? [])->toContain('activation_gate');
});

it('arms a temporary mutation shock via activation gate and applies it on the next generation', function (): void {
    config()->set('ag.search_response_activation.enable_temporary_mutation_shock', true);
    config()->set('ag.search_response_activation.temporary_mutation_shock_cooldown', 2);
    config()->set('ag.search_response_activation.temporary_mutation_shock_duration', 2);
    $problem = makeStandaloneFakeProblem();

    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
        adaptiveMutation: new AdaptiveMutationController(baseRate: 0.1, amplification: 0.0, maxRate: 0.1),
    );

    $resolver = new ReflectionMethod(GeneticAlgorithmEngine::class, 'resolveRealMutationShockActivation');
    $resolver->setAccessible(true);

    $telemetry = $resolver->invoke(
        $engine,
        4,
        [
            'search_response_simulation' => [
                'policy' => 'basin_lock_escape',
                'would_escalate' => true,
                'mutation_multiplier' => 3.0,
            ],
            'search_response_activation_gate' => [
                'eligible_as_candidate' => true,
                'candidate_policy' => 'basin_lock_escape',
                'mode' => 'diagnostic_only',
            ],
        ],
        true,
    );

    expect($telemetry)->toMatchArray([
        'mutation_shock_enabled' => true,
        'mutation_shock_requested' => true,
        'mutation_shock_applied' => true,
        'mutation_shock_policy' => 'basin_lock_escape',
        'mutation_shock_mode' => 'opt_in',
        'mutation_shock_multiplier' => 3.0,
        'mutation_shock_duration_generations' => 2,
        'mutation_shock_effective_from_generation' => 5,
    ]);

    $population = [
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
    ];

    (new PopulationFitnessEvaluator($problem))->evaluate($population);

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'executeGenerationStep');
    $method->setAccessible(true);

    $step = $method->invoke($engine, $population, 4);

    expect($step)->toMatchArray([
        'mutation_shock_active' => true,
        'mutation_shock_multiplier' => 3.0,
        'mutation_rate_base' => 0.1,
        'mutation_shock_remaining_generations_before' => 2,
        'mutation_shock_remaining_generations_after' => 1,
    ])
        ->and(round((float) $step['mutation_rate_effective'], 6))->toBe(0.3)
        ->and(round((float) $step['mutation_rate'], 6))->toBe(0.3);
});

it('applies reduced selection pressure from the landscape response to the real selector', function (): void {
    $problem = makeStandaloneFakeProblem();
    $selection = new TournamentSelection(
        3,
        new FitnessSharingCalculator(new GeneticDistance(), new SharingFunction(sigma: 0.35, alpha: 1.0)),
    );

    $engine = makeStandaloneEngine(
        problem: $problem,
        selection: $selection,
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $applySelectionPressure = new ReflectionMethod(GeneticAlgorithmEngine::class, 'applySelectionPressureMultiplier');
    $applySelectionPressure->setAccessible(true);
    $applySelectionPressure->invoke($engine, 0.8, 'landscape_response', 'premature_convergence');

    $population = [
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
    ];

    (new PopulationFitnessEvaluator($problem))->evaluate($population);

    $executeGenerationStep = new ReflectionMethod(GeneticAlgorithmEngine::class, 'executeGenerationStep');
    $executeGenerationStep->setAccessible(true);
    $step = $executeGenerationStep->invoke($engine, $population, 4);

    expect($step)->toMatchArray([
        'selection_pressure_supported' => true,
        'selection_pressure_source' => 'landscape_response',
        'selection_pressure_state' => 'reduced',
        'selection_pressure_base_tournament_size' => 3,
        'selection_pressure_effective_tournament_size' => 2,
        'selection_pressure_base_multiplier' => 0.8,
        'selection_pressure_effective_multiplier' => 0.8,
        'selection_pressure_reduction_active' => false,
    ]);
});

it('arms a temporary selection pressure reduction via activation gate and applies it on the next generation', function (): void {
    config()->set('ag.search_response_activation.enable_temporary_selection_pressure_reduction', true);
    config()->set('ag.search_response_activation.temporary_selection_pressure_reduction_cooldown', 2);
    config()->set('ag.search_response_activation.temporary_selection_pressure_reduction_duration', 2);

    $problem = makeStandaloneFakeProblem();
    $selection = new TournamentSelection(
        3,
        new FitnessSharingCalculator(new GeneticDistance(), new SharingFunction(sigma: 0.35, alpha: 1.0)),
    );

    $engine = makeStandaloneEngine(
        problem: $problem,
        selection: $selection,
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $resolver = new ReflectionMethod(GeneticAlgorithmEngine::class, 'resolveRealSelectionPressureReductionActivation');
    $resolver->setAccessible(true);

    $telemetry = $resolver->invoke(
        $engine,
        4,
        [
            'search_response_simulation' => [
                'policy' => 'basin_lock_escape',
                'would_escalate' => true,
                'selection_pressure_multiplier' => 0.65,
            ],
            'search_response_activation_gate' => [
                'eligible_as_candidate' => true,
                'candidate_policy' => 'basin_lock_escape',
                'mode' => 'diagnostic_only',
            ],
        ],
        true,
    );

    expect($telemetry)->toMatchArray([
        'selection_pressure_real_enabled' => true,
        'selection_pressure_real_requested' => true,
        'selection_pressure_real_applied' => true,
        'selection_pressure_real_policy' => 'basin_lock_escape',
        'selection_pressure_real_mode' => 'opt_in',
        'selection_pressure_real_multiplier' => 0.65,
        'selection_pressure_real_duration_generations' => 2,
        'selection_pressure_real_effective_from_generation' => 5,
    ]);

    $population = [
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
    ];

    (new PopulationFitnessEvaluator($problem))->evaluate($population);

    $executeGenerationStep = new ReflectionMethod(GeneticAlgorithmEngine::class, 'executeGenerationStep');
    $executeGenerationStep->setAccessible(true);
    $step = $executeGenerationStep->invoke($engine, $population, 4);

    expect($step)->toMatchArray([
        'selection_pressure_supported' => true,
        'selection_pressure_source' => 'activation_gate',
        'selection_pressure_state' => 'reduced',
        'selection_pressure_base_tournament_size' => 3,
        'selection_pressure_effective_tournament_size' => 2,
        'selection_pressure_base_multiplier' => 1.0,
        'selection_pressure_effective_multiplier' => 0.65,
        'selection_pressure_reduction_active' => true,
        'selection_pressure_reduction_multiplier' => 0.65,
        'selection_pressure_reduction_policy' => 'basin_lock_escape',
        'selection_pressure_reduction_remaining_generations_before' => 2,
        'selection_pressure_reduction_remaining_generations_after' => 1,
    ]);
});

it('applies an adaptive cooldown brake when recent alns outcomes have low return', function (): void {
    $repair = new class () implements RepairOperatorInterface {
        public function repair(PartialSolution $partial): Cromossomo
        {
            $candidate = new Cromossomo(array_merge($partial->assigned(), $partial->unassigned()));
            $candidate->setFitness(4.0);

            return $candidate;
        }

        public function getName(): string
        {
            return 'AdaptiveRepair';
        }
    };

    $lns = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [makeStandaloneFakeDestroyOperator('AdaptiveDestroy')],
        repairOperators: [$repair],
        selector: makeStandaloneFixedAlnsSelectionStrategy([
            'AdaptiveDestroy', 'AdaptiveRepair',
            'AdaptiveDestroy', 'AdaptiveRepair',
            'AdaptiveDestroy', 'AdaptiveRepair',
        ]),
    );

    $solution = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
        new Gene(2, 1, 1, 2, 1, 2, 1),
        new Gene(3, 1, 1, 3, 1, 3, 1),
        new Gene(4, 1, 1, 4, 1, 4, 1),
    ]);
    $solution->setFitness(4.0);

    $lns->improve($solution, ['trigger' => ['alns_trigger_reason' => 'budget_interval']]);
    $lns->improve($solution, ['trigger' => ['alns_trigger_reason' => 'budget_interval']]);
    $lns->improve($solution, ['trigger' => ['alns_trigger_reason' => 'budget_interval']]);

    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 5),
        lns: $lns,
        lnsFrequency: 50,
    );

    $lastAlnsGeneration = new ReflectionProperty(GeneticAlgorithmEngine::class, 'lastAlnsGeneration');
    $lastAlnsGeneration->setAccessible(true);
    $lastAlnsGeneration->setValue($engine, 1);

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'buildAlnsTriggerTelemetry');
    $method->setAccessible(true);

    $telemetry = $method->invoke(
        $engine,
        2,
        'premature_convergence',
        [
            'phenomenon' => 'neutral',
            'basin_of_attraction_lock_detected' => false,
        ],
        false,
    );

    expect($telemetry)->toMatchArray([
        'alns_triggered' => false,
        'alns_trigger_reason' => 'cooldown_recent_low_return',
        'alns_base_cooldown_generations' => 1,
        'alns_cooldown_generations' => 4,
        'alns_cooldown_brake_applied' => true,
        'alns_cooldown_brake_extra_generations' => 3,
    ])
        ->and($telemetry['alns_cooldown_brake_reason'] ?? null)->toBe('Recent ALNS outcomes are consistently negative or null.')
        ->and($telemetry['alns_recent_effectiveness_sample_size'] ?? null)->toBe(3)
        ->and($telemetry['alns_recent_effectiveness_mean_improvement'] ?? null)->toBe(0.0)
        ->and($telemetry['alns_recent_effectiveness_success_rate'] ?? null)->toBe(0.0);
});

it('evaluates each offspring only once during generation step', function (): void {
    $problem = new class () implements GeneticProblem {
        public int $sequence = 1;

        public int $evaluateCalls = 0;

        public function createIndividual(): Cromossomo
        {
            $seed = $this->sequence++;
            $individual = new Cromossomo([
                new Gene($seed, $seed, $seed, $seed, 1, 1, 1),
                new Gene($seed + 100, $seed, $seed, $seed + 1, 2, 2, 1),
            ]);
            $individual->setFitness((float) $seed);

            return $individual;
        }

        public function evaluate(Cromossomo $individual): FitnessResult
        {
            $this->evaluateCalls++;

            $score = (float) array_sum(array_map(
                static fn (Gene $gene): int => $gene->aulaId(),
                $individual->genes(),
            ));
            $individual->setFitness($score);

            return new FitnessResult($score, 0.0, 0.0, 0.0);
        }

        public function evaluateDelta(Cromossomo $individual, AffectedRegion $region, FitnessResult $previous): FitnessResult
        {
            return $this->evaluate($individual);
        }

        public function repair(Cromossomo $individual): Cromossomo
        {
            return $individual;
        }

        public function isFeasible(Cromossomo $individual): bool
        {
            return true;
        }

        public function clearFitnessCache(): void
        {
        }

        public function recordFitness(Cromossomo $individual, FitnessResult $fitness): void
        {
        }

        public function clearFitnessDeltaCache(): void
        {
        }
    };

    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $population = [
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
        $problem->createIndividual(),
    ];

    (new PopulationFitnessEvaluator($problem))->evaluate($population);
    $problem->evaluateCalls = 0;

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'executeGenerationStep');
    $method->setAccessible(true);
    $step = $method->invoke($engine, $population, 4);

    expect($step['population'])->toHaveCount(4)
        ->and($problem->evaluateCalls)->toBe(4);
});

it('accepts ALNS candidates only when they strictly improve best fitness', function (): void {
    $replacement = new class () implements ReplacementStrategyInterface {
        public int $calls = 0;

        public function replace(array &$population, Cromossomo $incoming): void
        {
            $this->calls++;
            $population[] = $incoming;
        }
    };

    $makeEngine = static function (GeneticProblem $problem, AdaptiveLargeNeighborhoodSearch $lns, ReplacementStrategyInterface $replacement): GeneticAlgorithmEngine {
        return new GeneticAlgorithmEngine(
            alnsAcceptance: null,
            problem: $problem,
            selection: makeFirstParentSelection(),
            crossover: makeCopyingCrossover(),
            mutation: makeCountingMutationOperator(),
            termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
            metrics: new MetricsRecorder(),
            elitism: makeNoElitism(),
            adaptiveMutation: new AdaptiveMutationController(baseRate: 0.0, amplification: 0.0, maxRate: 0.0),
            populationEvaluator: new PopulationFitnessEvaluator($problem),
            replacement: $replacement,
            hyperHeuristic: null,
            lns: $lns,
            progress: null,
            lnsFrequency: 50,
            landscapeEngine: null,
        );
    };

    $applyLns = new ReflectionMethod(GeneticAlgorithmEngine::class, 'applyLns');
    $applyLns->setAccessible(true);

    $problemReject = makeStandaloneFakeProblem();
    $lnsReject = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [makeStandaloneFakeDestroyOperator('AdaptiveDestroy')],
        repairOperators: [
            new class () implements RepairOperatorInterface {
                public function repair(PartialSolution $partial): Cromossomo
                {
                    return new Cromossomo([
                        new Gene(1, 1, 1, 1, 1, 1, 1),
                        new Gene(2, 1, 1, 2, 1, 2, 1),
                    ]);
                }

                public function getName(): string
                {
                    return 'WorseRepair';
                }
            },
        ],
        selector: makeStandaloneFixedAlnsSelectionStrategy(['AdaptiveDestroy', 'WorseRepair']),
    );

    $engineReject = $makeEngine($problemReject, $lnsReject, $replacement);
    $populationReject = [
        $problemReject->createIndividual(),
        $problemReject->createIndividual(),
        $problemReject->createIndividual(),
        $problemReject->createIndividual(),
    ];
    (new PopulationFitnessEvaluator($problemReject))->evaluate($populationReject);
    $replacement->calls = 0;

    $rejectArgs = [&$populationReject, [], null];
    $telemetryReject = $applyLns->invokeArgs($engineReject, $rejectArgs);

    expect($telemetryReject)->toMatchArray([
        'alns_accepted' => false,
        'alns_acceptance_policy' => 'StrictScoreImprovementAcceptance',
        'alns_acceptance_reason' => 'rejected_no_score_improvement',
    ])
        ->and($replacement->calls)->toBe(0);

    $problemAccept = makeStandaloneFakeProblem();
    $lnsAccept = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [makeStandaloneFakeDestroyOperator('AdaptiveDestroy')],
        repairOperators: [
            new class () implements RepairOperatorInterface {
                public function repair(PartialSolution $partial): Cromossomo
                {
                    return new Cromossomo([
                        new Gene(5000, 1, 1, 1, 1, 1, 1),
                        new Gene(6000, 1, 1, 2, 1, 2, 1),
                    ]);
                }

                public function getName(): string
                {
                    return 'BetterRepair';
                }
            },
        ],
        selector: makeStandaloneFixedAlnsSelectionStrategy(['AdaptiveDestroy', 'BetterRepair']),
    );

    $engineAccept = $makeEngine($problemAccept, $lnsAccept, $replacement);
    $populationAccept = [
        $problemAccept->createIndividual(),
        $problemAccept->createIndividual(),
        $problemAccept->createIndividual(),
        $problemAccept->createIndividual(),
    ];
    (new PopulationFitnessEvaluator($problemAccept))->evaluate($populationAccept);
    $replacement->calls = 0;

    $acceptArgs = [&$populationAccept, [], null];
    $telemetryAccept = $applyLns->invokeArgs($engineAccept, $acceptArgs);

    expect($telemetryAccept)->toMatchArray([
        'alns_accepted' => true,
        'alns_acceptance_policy' => 'StrictScoreImprovementAcceptance',
        'alns_acceptance_reason' => 'accepted_score_improved_same_hard_penalty',
    ])
        ->and($replacement->calls)->toBe(1);
});

function makeStandaloneEngine(
    GeneticProblem $problem,
    MutationOperatorInterface $mutation,
    TerminationCriterionInterface $termination,
    ?SelectionOperatorInterface $selection = null,
    ?ProgressReporterInterface $progress = null,
    ?AdaptiveLargeNeighborhoodSearch $lns = null,
    int $lnsFrequency = 50,
    ?LandscapeEngine $landscapeEngine = null,
    ?AdaptiveMutationController $adaptiveMutation = null,
): GeneticAlgorithmEngine {
    return new GeneticAlgorithmEngine(
        alnsAcceptance: null,
        problem: $problem,
        selection: $selection ?? makeFirstParentSelection(),
        crossover: makeCopyingCrossover(),
        mutation: $mutation,
        termination: $termination,
        metrics: new MetricsRecorder(),
        elitism: makeNoElitism(),
        adaptiveMutation: $adaptiveMutation ?? new AdaptiveMutationController(baseRate: 0.0, amplification: 0.0, maxRate: 0.0),
        populationEvaluator: new PopulationFitnessEvaluator($problem),
        replacement: makeNoopReplacement(),
        hyperHeuristic: null,
        lns: $lns,
        progress: $progress,
        lnsFrequency: $lnsFrequency,
        landscapeEngine: $landscapeEngine,
    );
}

function makeStandaloneFakeProblem(): GeneticProblem
{
    return new class () implements GeneticProblem {
        private int $sequence = 1;

        public function createIndividual(): Cromossomo
        {
            $seed = $this->sequence++;

            $individual = new Cromossomo([
                new Gene($seed, $seed, $seed, $seed, 1, 1, 1),
                new Gene($seed + 100, $seed, $seed, $seed + 1, 2, 2, 1),
            ]);

            $individual->setFitness((float) $seed);

            return $individual;
        }

        public function evaluate(Cromossomo $individual): FitnessResult
        {
            $score = (float) array_sum(array_map(
                static fn (Gene $gene): int => $gene->aulaId(),
                $individual->genes(),
            ));

            $individual->setFitness($score);

            return new FitnessResult($score, 0.0, 0.0, 0.0);
        }

        public function evaluateDelta(Cromossomo $individual, AffectedRegion $region, FitnessResult $previous): FitnessResult
        {
            return $this->evaluate($individual);
        }

        public function repair(Cromossomo $individual): Cromossomo
        {
            return $individual;
        }

        public function isFeasible(Cromossomo $individual): bool
        {
            return true;
        }

        public function clearFitnessCache(): void
        {
        }

        public function recordFitness(Cromossomo $individual, FitnessResult $fitness): void
        {
        }

        public function clearFitnessDeltaCache(): void
        {
        }
    };
}

function makeFirstParentSelection(): SelectionOperatorInterface
{
    return new class () implements SelectionOperatorInterface {
        public function select(array $population): Cromossomo
        {
            return $population[0];
        }
    };
}

function makeCopyingCrossover(): CrossoverOperatorInterface
{
    return new class () implements CrossoverOperatorInterface {
        public function crossover(Cromossomo $parentA, Cromossomo $parentB): array
        {
            return [$parentA->copy(), $parentB->copy()];
        }

        public function getName(): string
        {
            return 'CopyingCrossover';
        }
    };
}

// ---------------------------------------------------------------------------
// Sprint 3 — Tests for computeInitialPopulationBatchQuality (private method)
// ---------------------------------------------------------------------------

it('publishes a batch_quality report in initial_population phase after run', function (): void {
    $progress = makeCollectingProgressReporter();
    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
        progress: $progress,
    );

    $engine->run(4);

    $batchReport = collect($progress->reports)
        ->first(fn (array $r): bool => ($r['stage'] ?? '') === 'batch_quality');

    expect($batchReport)->toBeArray()
        ->and($batchReport['phase'])->toBe('initial_population')
        ->and($batchReport)->toHaveKey('verdict')
        ->and($batchReport)->toHaveKey('uniqueness_ratio')
        ->and($batchReport)->toHaveKey('fitness_min')
        ->and($batchReport)->toHaveKey('fitness_max')
        ->and($batchReport)->toHaveKey('fitness_avg')
        ->and($batchReport)->toHaveKey('fitness_std_dev')
        ->and($batchReport)->toHaveKey('fitness_coefficient_of_variation')
        ->and($batchReport)->toHaveKey('issues');
});

it('computeInitialPopulationBatchQuality returns ok for distinct fitness values and full uniqueness', function (): void {
    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'computeInitialPopulationBatchQuality');

    $result = $method->invoke($engine, [70.0, 85.0, 100.0, 115.0], 4, 4);

    expect($result['verdict'])->toBe('ok')
        ->and($result['issues'])->toBeEmpty()
        ->and($result['uniqueness_ratio'])->toBe(1.0);
});

it('computeInitialPopulationBatchQuality flags low_signature_diversity at 50 percent uniqueness', function (): void {
    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'computeInitialPopulationBatchQuality');

    // 2 únicos de 4 = 50 %  →  abaixo do limite de 75 %
    $result = $method->invoke($engine, [80.0, 90.0, 100.0, 110.0], 2, 4);

    expect($result['issues'])->toContain('low_signature_diversity');
});

it('computeInitialPopulationBatchQuality flags fitness_collapsed when all fitness values are identical', function (): void {
    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'computeInitialPopulationBatchQuality');

    $result = $method->invoke($engine, [100.0, 100.0, 100.0, 100.0], 4, 4);

    expect($result['issues'])->toContain('fitness_collapsed');
});

it('computeInitialPopulationBatchQuality returns verdict empty when population is empty', function (): void {
    $engine = makeStandaloneEngine(
        problem: makeStandaloneFakeProblem(),
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1),
    );

    $method = new ReflectionMethod(GeneticAlgorithmEngine::class, 'computeInitialPopulationBatchQuality');

    $result = $method->invoke($engine, [], 0, 0);

    expect($result['verdict'])->toBe('empty')
        ->and($result['fitness_min'])->toBeNull();
});

function makeCountingMutationOperator()
{
    return new class () implements MutationOperatorInterface {
        public int $calls = 0;

        public function mutate(Cromossomo $individual): Cromossomo
        {
            $this->calls++;

            return $individual;
        }

        public function getName(): string
        {
            return 'CountingMutation';
        }
    };
}

function makeNoElitism(): ElitismStrategyInterface
{
    return new class () implements ElitismStrategyInterface {
        public function selectElites(array $population): array
        {
            return [];
        }
    };
}

function makeNoopReplacement(): ReplacementStrategyInterface
{
    return new class () implements ReplacementStrategyInterface {
        public function replace(array &$population, Cromossomo $incoming): void
        {
        }
    };
}

function makeStandaloneTerminationCriterion(int $maxGenerationExclusive): TerminationCriterionInterface
{
    return new class ($maxGenerationExclusive) implements TerminationCriterionInterface {
        public function __construct(private readonly int $maxGenerationExclusive)
        {
        }

        public function shouldTerminate(int $generation, array $population): bool
        {
            return $generation >= $this->maxGenerationExclusive;
        }

        public function getGenerationsWithoutImprovement(): int
        {
            return 0;
        }

        public function getMaxGenerations(): ?int
        {
            return $this->maxGenerationExclusive;
        }
    };
}

function makeCollectingProgressReporter()
{
    return new class () implements ProgressReporterInterface {
        public array $reports = [];

        public function report(array $data): void
        {
            $this->reports[] = $data;
        }

        public function reportError(AGError $error): void
        {
        }
    };
}

function makeStandaloneFixedAlnsSelectionStrategy(array $selectionOrder): OperatorSelectionStrategy
{
    return new class ($selectionOrder) implements OperatorSelectionStrategy {
        public function __construct(private array $selectionOrder)
        {
        }

        public function select(array $operators, array $stats): object
        {
            $target = array_shift($this->selectionOrder);

            if ($target === null) {
                return $operators[0];
            }

            foreach ($operators as $operator) {
                if (method_exists($operator, 'getName') && $operator->getName() === $target) {
                    return $operator;
                }
            }

            throw new RuntimeException('No operator matched the fixed ALNS selection.');
        }
    };
}

function makeStandaloneFakeDestroyOperator(string $name): DestroyOperatorInterface
{
    return new class ($name) implements DestroyOperatorInterface {
        public function __construct(private readonly string $name)
        {
        }

        public function destroy(Cromossomo $solution): PartialSolution
        {
            return new PartialSolution($solution->genes(), []);
        }

        public function getName(): string
        {
            return $this->name;
        }
    };
}

function makeStandaloneFakeRepairOperator(string $name): RepairOperatorInterface
{
    return new class ($name) implements RepairOperatorInterface {
        public function __construct(private readonly string $name)
        {
        }

        public function repair(PartialSolution $partial): Cromossomo
        {
            $candidate = new Cromossomo([
                new Gene(999, 1, 1, 1, 1, 1, 1),
                new Gene(1000, 2, 2, 2, 2, 2, 1),
            ]);
            $candidate->setFitness(1999.0);

            return $candidate;
        }

        public function getName(): string
        {
            return $this->name;
        }
    };
}
