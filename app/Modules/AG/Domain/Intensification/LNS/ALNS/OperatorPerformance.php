<?php

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

class OperatorPerformance {
    public float $weight = 1.0;

    public int $uses = 0;

    public float $score = 0.0;

    public function reward(float $value): void {
        $this->score += $value;
        $this->uses++;
        $this->weight = 1 + ($this->score / max(1, $this->uses));
    }
}
