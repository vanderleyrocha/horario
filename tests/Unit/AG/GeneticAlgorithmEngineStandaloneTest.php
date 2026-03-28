<?php

declare(strict_types=1);

use App\Modules\AG\Application\GeneticAlgorithmEngine;
use App\Modules\AG\Application\PopulationFitnessEvaluator;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\FitnessResult;
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
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('runs the standalone evolution path without using an undefined operator when mutation does not happen', function (): void {
    $problem = new StandaloneFakeProblem;
    $mutation = new CountingMutationOperator;

    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: $mutation,
        termination: new StandaloneTerminationCriterion(maxGenerationExclusive: 1)
    );

    $best = $engine->run(4);

    expect($best)->toBeInstanceOf(Cromossomo::class)
        ->and($mutation->calls)->toBe(0);
});

it('logs a single structured summary for the initial population instead of one entry per individual', function (): void {
    Log::spy();

    $engine = makeStandaloneEngine(
        problem: new StandaloneFakeProblem,
        mutation: new CountingMutationOperator,
        termination: new StandaloneTerminationCriterion(maxGenerationExclusive: 0)
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

    Log::shouldNotHaveReceived('info', ['Criando indivíduo 0']);
    Log::shouldNotHaveReceived('info', ['Criando indivíduo 1']);
    Log::shouldNotHaveReceived('info', ['Criando indivíduo 2']);
});

it('reuses the shared generation step in evolveGeneration without emitting mutation telemetry when nothing mutates', function (): void {
    $problem = new StandaloneFakeProblem;
    $mutation = new CountingMutationOperator;
    $engine = makeStandaloneEngine(
        problem: $problem,
        mutation: $mutation,
        termination: new StandaloneTerminationCriterion(maxGenerationExclusive: 1)
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

function makeStandaloneEngine(
    GeneticProblem $problem,
    CountingMutationOperator $mutation,
    TerminationCriterionInterface $termination
): GeneticAlgorithmEngine {
    return new GeneticAlgorithmEngine(
        problem: $problem,
        selection: new FirstParentSelection,
        crossover: new CopyingCrossover,
        mutation: $mutation,
        termination: $termination,
        metrics: new MetricsRecorder,
        elitism: new NoElitism,
        adaptiveMutation: new AdaptiveMutationController(baseRate: 0.0, amplification: 0.0, maxRate: 0.0),
        populationEvaluator: new PopulationFitnessEvaluator($problem),
        replacement: new NoopReplacement,
        hyperHeuristic: null,
    );
}

final class StandaloneFakeProblem implements GeneticProblem
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
}

final class FirstParentSelection implements SelectionOperatorInterface
{
    public function select(array $population): Cromossomo
    {
        return $population[0];
    }
}

final class CopyingCrossover implements CrossoverOperatorInterface
{
    public function crossover(Cromossomo $parentA, Cromossomo $parentB): array
    {
        return [$parentA->copy(), $parentB->copy()];
    }

    public function getName(): string
    {
        return 'CopyingCrossover';
    }
}

final class CountingMutationOperator implements MutationOperatorInterface
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
}

final class NoElitism implements ElitismStrategyInterface
{
    public function selectElites(array $population): array
    {
        return [];
    }
}

final class NoopReplacement implements ReplacementStrategyInterface
{
    public function replace(array &$population, Cromossomo $incoming): void {}
}

final class StandaloneTerminationCriterion implements TerminationCriterionInterface
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
}
