<?php

namespace App\Modules\AG\Domain\Operators\Selection\FitnessSharing;

use App\Modules\AG\Domain\Metrics\GeneticDistance;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class FitnessSharingCalculator {
    private GeneticDistance $distance;

    private SharingFunction $sharing;

    public function __construct(
        float $sigma = 0.35,
        float $alpha = 1.0
    ) {
        $this->distance = new GeneticDistance();
        $this->sharing = new SharingFunction($sigma, $alpha);
    }

    /**
     * @param Cromossomo[] $population
     */
    public function sharedFitness(
        Cromossomo $individual,
        array $population
    ): float {

        $denominator = 0.0;

        foreach ($population as $other) {

            $d = $this->distance->hamming(
                $individual,
                $other
            );

            $denominator +=
                $this->sharing->value($d);
        }

        if ($denominator <= 0) {
            return $individual->fitness();
        }

        return $individual->fitness() / $denominator;
    }
}
