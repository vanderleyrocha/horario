<?php

namespace App\Modules\AG\Domain\Contracts;

interface GeneticProblem {
    public function createIndividual(): mixed;

    public function fitness(mixed $individual): float;

    public function crossover(mixed $parentA, mixed $parentB): mixed;

    public function mutate(mixed $individual): mixed;

    public function isFeasible(mixed $individual): bool;
}
