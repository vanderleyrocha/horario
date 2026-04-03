<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Metrics\DiversityCalculatorInterface;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Metrics\PopulationEntropyCalculator;
use App\Modules\AG\Domain\Metrics\PopulationStatistics;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;

it('records generation metrics from population statistics as the single source of truth', function (): void {
    $population = makeMetricsPopulation();

    $expectedStatistics = (new PopulationStatistics(
        diversityCalculator: makeSequenceDiversityCalculator([0.64]),
        entropyCalculator: new PopulationEntropyCalculator
    ))->calculate($population, 0);

    $recorder = new MetricsRecorder;
    $recorder->setPopulationStatistics(new PopulationStatistics(
        diversityCalculator: makeSequenceDiversityCalculator([0.64]),
        entropyCalculator: new PopulationEntropyCalculator
    ));

    $metrics = $recorder->recordExtended(
        generation: 0,
        population: $population,
        mutationRate: 0.14,
        stagnation: 3,
        landscapeState: 'steady'
    );

    expect($metrics->bestFitness)->toBe($expectedStatistics['best'])
        ->and($metrics->avgFitness)->toBe($expectedStatistics['average'])
        ->and($metrics->variance)->toBe($expectedStatistics['variance'])
        ->and($metrics->diversity)->toBe($expectedStatistics['diversity'])
        ->and($metrics->entropy)->toBe($expectedStatistics['entropy'])
        ->and($recorder->lastDiversity())->toBe($expectedStatistics['diversity'])
        ->and($recorder->lastEntropy())->toBe($expectedStatistics['entropy'])
        ->and($recorder->generationData()[0])->toMatchArray([
            'generation' => 0,
            'best_fitness' => $expectedStatistics['best'],
            'avg_fitness' => $expectedStatistics['average'],
            'variance' => $expectedStatistics['variance'],
            'diversity' => $expectedStatistics['diversity'],
            'entropy' => $expectedStatistics['entropy'],
            'mutation_rate' => 0.14,
            'stagnation' => 3,
            'landscape_state' => 'steady',
        ]);
});

it('reuses sampled diversity between telemetry generations to keep metrics efficient', function (): void {
    $population = makeMetricsPopulation();
    $diversityCalculator = makeSequenceDiversityCalculator([0.64, 0.28]);

    $recorder = new MetricsRecorder;
    $recorder->setPopulationStatistics(new PopulationStatistics(
        diversityCalculator: $diversityCalculator,
        entropyCalculator: new PopulationEntropyCalculator,
        diversitySamplingInterval: 5,
        diversityCollapseThreshold: 0.05
    ));

    $first = $recorder->recordExtended(0, $population, 0.10, 0);
    $second = $recorder->recordExtended(1, $population, 0.11, 1);
    $third = $recorder->recordExtended(5, $population, 0.12, 2);

    expect($first->diversity)->toBe(0.64)
        ->and($second->diversity)->toBe(0.64)
        ->and($third->diversity)->toBe(0.28)
        ->and($diversityCalculator->calls)->toBe(2);
});

it('overwrites the same generation when metrics are recalculated after intensification', function (): void {
    $population = makeMetricsPopulation();
    $diversityCalculator = makeSequenceDiversityCalculator([0.64, 0.28]);

    $recorder = new MetricsRecorder;
    $recorder->setPopulationStatistics(new PopulationStatistics(
        diversityCalculator: $diversityCalculator,
        entropyCalculator: new PopulationEntropyCalculator,
        diversitySamplingInterval: 5,
        diversityCollapseThreshold: 0.05
    ));

    $first = $recorder->recordExtended(5, $population, 0.10, 0);
    $recalculated = $recorder->recordExtended(
        generation: 5,
        population: $population,
        mutationRate: 0.12,
        stagnation: 1,
        landscapeState: 'intensified',
        forceRefreshStatistics: true
    );

    expect($first->diversity)->toBe(0.64)
        ->and($recalculated->diversity)->toBe(0.28)
        ->and($diversityCalculator->calls)->toBe(2)
        ->and($recorder->generationData())->toHaveCount(1)
        ->and($recorder->generationData()[0])->toMatchArray([
            'generation' => 5,
            'diversity' => 0.28,
            'mutation_rate' => 0.12,
            'stagnation' => 1,
            'landscape_state' => 'intensified',
        ]);
});

function makeMetricsPopulation(): array
{
    return [
        new Cromossomo([
            new Gene(1, 1, 1, 1, 1, 1, 1),
            new Gene(2, 1, 1, 2, 1, 2, 1),
        ]),
        new Cromossomo([
            new Gene(3, 2, 2, 3, 2, 3, 2),
            new Gene(4, 2, 2, 4, 2, 4, 2),
        ]),
        new Cromossomo([
            new Gene(5, 3, 3, 5, 3, 5, 1),
        ]),
    ];
}

function makeSequenceDiversityCalculator(array $values): DiversityCalculatorInterface
{
    return new class($values) implements DiversityCalculatorInterface
    {
        public int $calls = 0;

        public function __construct(private array $values) {}

        public function calculate(array $population): float
        {
            $index = min($this->calls, count($this->values) - 1);
            $value = $this->values[$index] ?? 0.0;
            $this->calls++;

            return $value;
        }
    };
}
