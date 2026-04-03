<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Replacement;

use App\Modules\AG\Domain\Metrics\GeneticDistance;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class SimilarityReplacement implements ReplacementStrategyInterface
{
    public function __construct(private GeneticDistance $distance, private int $windowSize = 10) {}

    public function replace(array &$population, Cromossomo $incoming): void
    {
        $size = count($population);

        if ($size === 0) {

            $population[] = $incoming;

            return;
        }

        $window = $this->sampleWindow($population);

        $mostSimilarIndex = null;
        $lowestDistance = INF;

        foreach ($window as $index => $individual) {

            $d = $this->distance->distance($incoming, $individual);

            if ($d < $lowestDistance) {

                $lowestDistance = $d;
                $mostSimilarIndex = $index;
            }
        }

        if ($mostSimilarIndex === null) {
            return;
        }

        if (
            $incoming->fitness() >
            $population[$mostSimilarIndex]->fitness()
        ) {

            $population[$mostSimilarIndex] = $incoming;
        }
    }

    private function sampleWindow(array $population): array
    {
        $size = count($population);

        $windowSize = min($this->windowSize, $size);

        $indexes = array_rand($population, $windowSize);

        $window = [];

        foreach ((array) $indexes as $index) {
            $window[$index] = $population[$index];
        }

        return $window;
    }
}
