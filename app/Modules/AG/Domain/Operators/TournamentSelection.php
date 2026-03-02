<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class TournamentSelection implements SelectionOperatorInterface {
    public function __construct(private readonly int $tournamentSize = 3) {
    }

    public function select(array $population, int $count): array {
        $selected = [];

        for ($i = 0; $i < $count; $i++) {
            $selected[] = $this->runTournament($population);
        }

        return $selected;
    }

    private function runTournament(array $population): Cromossomo {
        $best = null;

        for ($i = 0; $i < $this->tournamentSize; $i++) {

            $candidate = $population[array_rand($population)];

            if ($best === null || $candidate->fitness() > $best->fitness()) {
                $best = $candidate;
            }
        }

        return $best;
    }
}
