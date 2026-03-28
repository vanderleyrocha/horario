<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Metrics\HashDiversityCalculator;
use App\Modules\AG\Domain\Metrics\PopulationEntropyCalculator;
use App\Modules\AG\Domain\Metrics\PopulationStatistics;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\AG\Domain\Termination\VarianceBasedTerminationCriterion;

it('terminates when variance window collapses with low diversity and entropy', function (): void {
    $criterion = new VarianceBasedTerminationCriterion(
        maxGenerations: 500,
        populationStatistics: new PopulationStatistics(
            new HashDiversityCalculator(),
            new PopulationEntropyCalculator()
        ),
        targetFitness: 0.0,
        maxGenerationsWithoutImprovement: 500,
        varianceThreshold: 0.00001,
        varianceWindowSize: 3,
        minGenerationsBeforeVarianceCheck: 2,
        minDiversity: 0.10,
        minEntropy: 0.10
    );

    $population = [];

    for ($i = 0; $i < 6; $i++) {
        $chromosome = new Cromossomo([
            new Gene(1, 1, 1, 1, 1, 1, 1),
        ]);
        $chromosome->setFitness(75.0);
        $population[] = $chromosome;
    }

    expect($criterion->shouldTerminate(0, $population))->toBeFalse()
        ->and($criterion->shouldTerminate(1, $population))->toBeFalse()
        ->and($criterion->shouldTerminate(2, $population))->toBeFalse()
        ->and($criterion->shouldTerminate(3, $population))->toBeFalse()
        ->and($criterion->shouldTerminate(4, $population))->toBeTrue();
});

it('does not terminate by variance when diversity remains high', function (): void {
    $criterion = new VarianceBasedTerminationCriterion(
        maxGenerations: 500,
        populationStatistics: new PopulationStatistics(
            new HashDiversityCalculator(),
            new PopulationEntropyCalculator()
        ),
        targetFitness: 0.0,
        maxGenerationsWithoutImprovement: 500,
        varianceThreshold: 0.00001,
        varianceWindowSize: 3,
        minGenerationsBeforeVarianceCheck: 2,
        minDiversity: 0.10,
        minEntropy: 0.10
    );

    $population = [];

    for ($i = 1; $i <= 6; $i++) {
        $chromosome = new Cromossomo([
            new Gene($i, $i, $i, $i, 1, $i, 1),
        ]);
        $chromosome->setFitness(80.0);
        $population[] = $chromosome;
    }

    expect($criterion->shouldTerminate(0, $population))->toBeFalse()
        ->and($criterion->shouldTerminate(1, $population))->toBeFalse()
        ->and($criterion->shouldTerminate(2, $population))->toBeFalse()
        ->and($criterion->shouldTerminate(3, $population))->toBeFalse();
});
