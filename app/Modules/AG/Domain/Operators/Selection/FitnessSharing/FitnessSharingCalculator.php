<?php

namespace App\Modules\AG\Domain\Operators\Selection\FitnessSharing;

use App\Modules\AG\Domain\Metrics\GeneticDistance;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class FitnessSharingCalculator
{
    public function __construct(private GeneticDistance $distance, private SharingFunction $sharing)
    {
    }

    /**
     * @param Cromossomo[] $population
     */
    public function sharedFitness(Cromossomo $individual, array $population): float
    {

        $denominator = 0.0;

        foreach ($population as $other) {

            $d = $this->distance->distance($individual, $other);

            $denominator +=
                $this->sharing->value($d);
        }

        if ($denominator <= 0) {
            return $individual->fitness();
        }

        return $individual->fitness() / $denominator;
    }
}
