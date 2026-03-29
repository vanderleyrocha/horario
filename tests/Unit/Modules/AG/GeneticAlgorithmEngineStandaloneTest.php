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
use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\DestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RepairOperatorInterface;
use App\Modules\AG\Domain\Landscape\LandscapeEngine;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Operators\Adaptive\AdaptiveMutationController;
use App\Modules\AG\Domain\Operators\Crossover\CrossoverOperatorInterface;
use App\Modules\AG\Domain\Operators\Elitism\ElitismStrategyInterface;
use App\Modules\AG\Domain\Operators\Mutation\MutationOperatorInterface;
use App\Modules\AG\Domain\Operators\Replacement\ReplacementStrategyInterface;
use App\Modules\AG\Domain\Operators\Selection\SelectionOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\AG\Domain\Termination\TerminationCriterionInterface;
use App\Modules\AG\Support\AGError;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

uses(TestCase::class);

it('runs the standalone evolution path without using an undefined operator when mutation does not happen', function (): void {
    $problem = makeStandaloneFakeProblem();
    $mutation = makeCountingMutationOperator();

    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: $mutation,
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1)
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
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 0)
    );

    $engine->run(3);

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'ga.population.initialized'
                && ($context['population_size'] ?? null) === 3
                && count($context['sample_gene_counts'] ?? []) === 3
                && array_key_exists('best_fitness', $context)
                && array_key_exists('avg_fitness', $context);
        });

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
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 1)
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
        progress: $progress
    );

    $engine->run(4);

    expect($progress->reports)->toHaveCount(1)
        ->and($progress->reports[0])->toMatchArray([
            'phase' => 'evolution',
            'generation' => 0,
            'max_generations' => 1,
            'mutation_rate' => 0.0,
            'stagnation' => 0,
            'landscape_state' => 'unknown',
        ])
        ->and($progress->reports[0])->toHaveKeys([
            'best_fitness',
            'avg_fitness',
            'diversity',
            'entropy',
        ]);
});

it('triggers alns adaptively in short runs and publishes trigger telemetry', function (): void {
    $progress = makeCollectingProgressReporter();
    $problem = makeStandaloneFakeProblem();
    $lns = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [makeStandaloneFakeDestroyOperator('AdaptiveDestroy')],
        repairOperators: [makeStandaloneFakeRepairOperator('AdaptiveRepair')],
        selector: makeStandaloneFixedAlnsSelectionStrategy(['AdaptiveDestroy', 'AdaptiveRepair'])
    );

    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: makeCountingMutationOperator(),
        termination: makeStandaloneTerminationCriterion(maxGenerationExclusive: 5),
        progress: $progress,
        lns: $lns,
        lnsFrequency: 50
    );

    $engine->run(4);

    $alnsReports = array_values(array_filter(
        $progress->reports,
        static fn (array $payload): bool => ($payload['alns_triggered'] ?? false) === true
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
            selector: makeStandaloneFixedAlnsSelectionStrategy(['AdaptiveDestroy', 'AdaptiveRepair'])
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
        false
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

function makeStandaloneEngine(
    GeneticProblem $problem,
    MutationOperatorInterface $mutation,
    TerminationCriterionInterface $termination,
    ?ProgressReporterInterface $progress = null,
    ?AdaptiveLargeNeighborhoodSearch $lns = null,
    int $lnsFrequency = 50,
    ?LandscapeEngine $landscapeEngine = null
): GeneticAlgorithmEngine {
    return new GeneticAlgorithmEngine(
        problem: $problem,
        selection: makeFirstParentSelection(),
        crossover: makeCopyingCrossover(),
        mutation: $mutation,
        termination: $termination,
        metrics: new MetricsRecorder(),
        elitism: makeNoElitism(),
        adaptiveMutation: new AdaptiveMutationController(baseRate: 0.0, amplification: 0.0, maxRate: 0.0),
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
    return new class implements GeneticProblem
    {
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
                $individual->genes()
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

        public function clearFitnessCache(): void {}
    };
}

function makeFirstParentSelection(): SelectionOperatorInterface
{
    return new class implements SelectionOperatorInterface
    {
        public function select(array $population): Cromossomo
        {
            return $population[0];
        }
    };
}

function makeCopyingCrossover(): CrossoverOperatorInterface
{
    return new class implements CrossoverOperatorInterface
    {
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

function makeCountingMutationOperator()
{
    return new class implements MutationOperatorInterface
    {
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
    return new class implements ElitismStrategyInterface
    {
        public function selectElites(array $population): array
        {
            return [];
        }
    };
}

function makeNoopReplacement(): ReplacementStrategyInterface
{
    return new class implements ReplacementStrategyInterface
    {
        public function replace(array &$population, Cromossomo $incoming): void {}
    };
}

function makeStandaloneTerminationCriterion(int $maxGenerationExclusive): TerminationCriterionInterface
{
    return new class ($maxGenerationExclusive) implements TerminationCriterionInterface
    {
        public function __construct(private readonly int $maxGenerationExclusive) {}

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
    return new class implements ProgressReporterInterface
    {
        public array $reports = [];

        public function report(array $data): void
        {
            $this->reports[] = $data;
        }

        public function reportError(AGError $error): void {}
    };
}

function makeStandaloneFixedAlnsSelectionStrategy(array $selectionOrder): OperatorSelectionStrategy
{
    return new class ($selectionOrder) implements OperatorSelectionStrategy
    {
        public function __construct(private array $selectionOrder) {}

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
    return new class ($name) implements DestroyOperatorInterface
    {
        public function __construct(private readonly string $name) {}

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
    return new class ($name) implements RepairOperatorInterface
    {
        public function __construct(private readonly string $name) {}

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
