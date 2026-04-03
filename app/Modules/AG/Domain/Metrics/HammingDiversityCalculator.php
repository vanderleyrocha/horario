<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;

final class HammingDiversityCalculator implements DiversityCalculatorInterface
{
    public function calculate(array $population): float
    {
        $n = count($population);

        if ($n < 2) {
            return 0.0;
        }

        $geneCount = $population[0]->count();

        $distanceSum = 0.0;
        $pairs = 0;

        for ($i = 0; $i < $n; $i++) {

            for ($j = $i + 1; $j < $n; $j++) {

                $distanceSum += $this->chromosomeDistance($population[$i], $population[$j]);

                $pairs++;
            }
        }

        $maxDistance = $geneCount * $pairs;

        if ($maxDistance === 0) {
            return 0.0;
        }

        return $distanceSum / $maxDistance;
    }

    private function chromosomeDistance(Cromossomo $a, Cromossomo $b): int
    {

        $genesA = $a->genes();
        $genesB = $b->genes();

        $distance = 0;

        $length = count($genesA);

        for ($i = 0; $i < $length; $i++) {

            if (! $this->genesEqual($genesA[$i], $genesB[$i])) {
                $distance++;
            }
        }

        return $distance;
    }

    private function genesEqual(Gene $a, Gene $b): bool
    {
        return
            $a->diaSemana() === $b->diaSemana() &&
            $a->periodoDia() === $b->periodoDia() &&
            $a->professorId() === $b->professorId() &&
            $a->turmaId() === $b->turmaId();
    }
}
