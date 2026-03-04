<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class TournamentSelection implements SelectionOperatorInterface {
    public function __construct(private int $k = 3) {
    }

    public function select(array $population): Cromossomo {
        $candidates = [];

        for ($i = 0; $i < $this->k; $i++) {
            $candidates[] = $population[array_rand($population)];
        }

        usort($candidates, fn($a, $b) => $b->fitness() <=> $a->fitness());

        return $candidates[0];
    }
}
