<?php

use App\Modules\Horarios\Domain\Risk\RiskClassification;
use App\Modules\Horarios\Domain\Risk\RiskIndexCalculator;
use App\Modules\Horarios\Domain\Risk\StructuralEntropyCalculator;

it('calculates a bounded formal risk index using all structural dimensions', function () {
    $calculator = new RiskIndexCalculator;

    $riskIndex = $calculator->calculate(
        globalSaturation: 118.0,
        turmaOverloads: [
            10 => ['excedente' => 3],
            11 => ['excedente' => 2],
        ],
        professorOverloads: [
            7 => ['excedente' => 2],
        ],
        doubleBlockIssues: [
            ['deficit' => 2],
        ],
        structuralEntropy: 32.5
    );

    expect($riskIndex)->toBeInt()
        ->and($riskIndex)->toBeGreaterThan(0)
        ->and($riskIndex)->toBeLessThanOrEqual(100)
        ->and((new RiskClassification)->classify($riskIndex))->toBe('ALTO');
});

it('calculates normalized structural entropy from load distribution', function () {
    $entropy = (new StructuralEntropyCalculator)->calculate([10, 10, 10, 10]);
    $concentratedEntropy = (new StructuralEntropyCalculator)->calculate([37, 1, 1, 1]);

    expect($entropy)->toBeGreaterThan($concentratedEntropy)
        ->and($entropy)->toBeLessThanOrEqual(100.0)
        ->and($concentratedEntropy)->toBeGreaterThanOrEqual(0.0);
});
