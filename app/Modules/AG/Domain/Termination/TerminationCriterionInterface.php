<?php

namespace App\Modules\AG\Domain\Termination;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

interface TerminationCriterionInterface {
    public function shouldTerminate(int $generation, array $population): bool;

    public function getGenerationsWithoutImprovement(): int;

    public function getMaxGenerations(): ?int;
}
