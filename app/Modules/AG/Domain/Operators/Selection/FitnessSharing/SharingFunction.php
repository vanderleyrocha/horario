<?php

namespace App\Modules\AG\Domain\Operators\Selection\FitnessSharing;

final class SharingFunction {
    public function __construct(
        private float $sigma = 0.35,
        private float $alpha = 1.0
    ) {
    }

    public function value(float $distance): float {
        if ($distance >= $this->sigma) {
            return 0.0;
        }

        return 1.0 - pow($distance / $this->sigma, $this->alpha);
    }
}
