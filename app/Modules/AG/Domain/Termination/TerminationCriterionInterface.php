<?php

namespace App\Modules\AG\Domain\Termination;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

interface TerminationCriterionInterface {
    /**
     * @param Cromossomo[] $population
     */
    public function shouldTerminate(int $generation, array $population): bool;

    public function getGenerationsWithoutImprovement(): int;
}
