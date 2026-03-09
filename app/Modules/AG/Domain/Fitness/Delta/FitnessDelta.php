<?php

namespace App\Modules\AG\Domain\Fitness\Delta;

final class FitnessDelta {
    public function __construct(
        public readonly float $hardDelta,
        public readonly float $softDelta
    ) {
    }

    public function total(): float {
        return $this->hardDelta + $this->softDelta;
    }
}
