<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class PopulationStatistics
{
    public function __construct(private DiversityCalculatorInterface $diversityCalculator, private PopulationEntropyCalculator $entropyCalculator)
    {
    }

    /**
     * @param Cromossomo[] $population
     */
    public function calculate(array $population): array
    {
        $n = count($population);

        if ($n === 0) {
            throw new \RuntimeException("Population cannot be empty");
        }

        $fitnessValues = [];
        $sum = 0.0;
        $best = -INF;

        foreach ($population as $c) {

            $f = $c->fitness();

            $fitnessValues[] = $f;

            $sum += $f;

            if ($f > $best) {
                $best = $f;
            }
        }

        $avg = $sum / $n;

        $variance = $this->variance($fitnessValues, $avg);

        $diversity = $this->diversityCalculator
            ->calculate($population);

        $entropy = $this->entropyCalculator
            ->normalized($population);

        return [
            'best' => $best,
            'average' => $avg,
            'variance' => $variance,
            'diversity' => $diversity,
            'entropy' => $entropy,
        ];
    }

    private function variance(array $values, float $mean): float
    {
        $sum = 0.0;

        foreach ($values as $v) {

            $sum += ($v - $mean) ** 2;
        }

        return $sum / count($values);
    }
}
