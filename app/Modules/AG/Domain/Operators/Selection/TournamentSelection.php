<?php

namespace App\Modules\AG\Domain\Operators\Selection;

use App\Modules\AG\Domain\Operators\Selection\FitnessSharing\FitnessSharingCalculator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class TournamentSelection implements SelectionOperatorInterface {
    private FitnessSharingCalculator $sharing;

    public function __construct(private int $k = 3, float $sigma = 0.35) {
        $this->sharing = new FitnessSharingCalculator($sigma);
    }

    public function select(array $population): Cromossomo {
        $candidates = [];

        for ($i = 0; $i < $this->k; $i++) {

            $candidates[] = $population[array_rand($population)];
        }

        usort(
            $candidates,
            function (Cromossomo $a, Cromossomo $b) use ($population) {

                $fa = $this->sharing->sharedFitness($a, $population);

                $fb = $this->sharing->sharedFitness($b, $population);

                return $fb <=> $fa;
            }
        );

        return $candidates[0];
    }
}
