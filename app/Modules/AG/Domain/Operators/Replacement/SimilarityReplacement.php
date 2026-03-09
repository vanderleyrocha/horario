<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Replacement;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class SimilarityReplacement implements ReplacementStrategyInterface {
    private int $windowSize;

    public function __construct(int $windowSize = 10) {
        $this->windowSize = $windowSize;
    }

    public function replace(array &$population, Cromossomo $incoming): void {
        $size = count($population);

        if ($size === 0) {
            $population[] = $incoming;
            return;
        }

        $window = $this->sampleWindow($population);

        $mostSimilarIndex = null;
        $lowestDistance = PHP_INT_MAX;

        foreach ($window as $index => $individual) {

            $distance = $this->hammingDistance($incoming, $individual);

            if ($distance < $lowestDistance) {

                $lowestDistance = $distance;
                $mostSimilarIndex = $index;
            }
        }

        if ($mostSimilarIndex === null) {
            return;
        }

        if ($incoming->fitness() > $population[$mostSimilarIndex]->fitness()) {

            $population[$mostSimilarIndex] = $incoming;
        }
    }

    private function sampleWindow(array $population): array {
        $size = count($population);

        $windowSize = min($this->windowSize, $size);

        $indexes = array_rand($population, $windowSize);

        $window = [];

        foreach ((array) $indexes as $index) {
            $window[$index] = $population[$index];
        }

        return $window;
    }

    private function hammingDistance(Cromossomo $a, Cromossomo $b): int {

        $genesA = $a->genes();
        $genesB = $b->genes();

        $distance = 0;

        $size = min(count($genesA), count($genesB));

        for ($i = 0; $i < $size; $i++) {

            if ($genesA[$i]->diaSemana() !== $genesB[$i]->diaSemana() || $genesA[$i]->periodoDia() !== $genesB[$i]->periodoDia()) {
                $distance++;
            }
        }

        return $distance;
    }
}
