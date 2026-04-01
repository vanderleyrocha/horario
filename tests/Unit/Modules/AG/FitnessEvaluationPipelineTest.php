<?php

declare(strict_types=1);

use App\Modules\AG\Application\PopulationFitnessEvaluator;
use App\Modules\AG\Domain\Contracts\FitnessEvaluatorInterface;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\AG\Infrastructure\Parallel\AsyncFitnessEvaluator;
use Spatie\Async\Pool;

it('uses the fitness evaluator contract in sync and async adapters', function (): void {
    $problem = makeFakeGeneticProblem();

    $sync = new PopulationFitnessEvaluator(problem: $problem);
    $async = new AsyncFitnessEvaluator(problem: $problem, concurrency: 4);

    expect($sync)->toBeInstanceOf(FitnessEvaluatorInterface::class)
        ->and($async)->toBeInstanceOf(FitnessEvaluatorInterface::class);
});

it('uses sequential fallback when async is not eligible', function (): void {
    $problem = makeFakeGeneticProblem();
    $evaluator = new AsyncFitnessEvaluator(problem: $problem, concurrency: 1);

    $population = [
        new Cromossomo([new Gene(1, 1, 1, 1, 1, 1, 1)]),
        new Cromossomo([new Gene(2, 2, 2, 2, 2, 3, 1)]),
    ];

    $evaluator->evaluate($population);

    expect($population[0]->fitness())->toBe(1.0)
        ->and($population[1]->fitness())->toBe(1.0)
        ->and($problem->evaluations)->toBe(2);
});

it('produces the same population fitness in sync and async modes when the async pool is supported', function (): void {
    if (! Pool::isSupported()) {
        $this->markTestSkipped('Spatie Async Pool is not supported in this environment.');
    }

    config()->set('ag.parallel_evaluation', true);

    $syncPopulation = makePopulationForEvaluation();
    $asyncPopulation = makePopulationForEvaluation();

    $syncProblem = makeSerializableFakeGeneticProblem();
    $asyncProblem = makeSerializableFakeGeneticProblem();

    $sync = new PopulationFitnessEvaluator(problem: $syncProblem);
    $async = new AsyncFitnessEvaluator(problem: $asyncProblem, concurrency: 2, timeoutSeconds: 30);

    $sync->evaluate($syncPopulation);
    $async->evaluate($asyncPopulation);

    $syncFitness = array_map(static fn (Cromossomo $individual): float => $individual->fitness(), $syncPopulation);
    $asyncFitness = array_map(static fn (Cromossomo $individual): float => $individual->fitness(), $asyncPopulation);

    expect($asyncFitness)->toBe($syncFitness)
        ->and($syncFitness)->toHaveCount(4)
        ->and($syncFitness)->toBe([
            17.0,
            48.0,
            100.0,
            38.0,
        ]);
});

function makePopulationForEvaluation(): array
{
    return [
        new Cromossomo([
            new Gene(1, 2, 3, 4, 1, 1, 1),
            new Gene(2, 3, 4, 5, 2, 2, 2),
        ]),
        new Cromossomo([
            new Gene(3, 4, 5, 6, 3, 3, 1),
            new Gene(4, 5, 6, 7, 4, 4, 1),
            new Gene(5, 6, 7, 8, 5, 5, 1),
        ]),
        new Cromossomo([
            new Gene(6, 7, 8, 9, 1, 6, 2),
            new Gene(7, 8, 9, 10, 2, 7, 2),
            new Gene(8, 9, 10, 11, 3, 8, 2),
            new Gene(9, 10, 11, 12, 4, 9, 2),
        ]),
        new Cromossomo([
            new Gene(10, 11, 12, 13, 5, 1, 1),
            new Gene(11, 12, 13, 14, 1, 2, 1),
        ]),
    ];
}

function makeFakeGeneticProblem(): GeneticProblem
{
    return new class () implements GeneticProblem {
        public int $evaluations = 0;

        public function createIndividual(): Cromossomo
        {
            return new Cromossomo([]);
        }

        public function evaluate(Cromossomo $individual): FitnessResult
        {
            $this->evaluations++;
            $score = (float) count($individual->genes());
            $individual->setFitness($score);

            return new FitnessResult(
                score: $score,
                totalPenalty: 0.0,
                hardPenalty: 0.0,
                softPenalty: 0.0,
            );
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

function makeSerializableFakeGeneticProblem(): GeneticProblem
{
    return new class () implements GeneticProblem {
        public function createIndividual(): Cromossomo
        {
            return new Cromossomo([]);
        }

        public function evaluate(Cromossomo $individual): FitnessResult
        {
            $score = 0.0;

            foreach ($individual->genes() as $gene) {
                $score += $gene->diaSemana() + $gene->periodoDia() + $gene->duracaoTempos();
            }

            $individual->setFitness($score);

            return new FitnessResult(
                score: $score,
                totalPenalty: 0.0,
                hardPenalty: 0.0,
                softPenalty: 0.0,
            );
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
