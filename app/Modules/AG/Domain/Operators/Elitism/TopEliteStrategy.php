<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Elitism;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class TopEliteStrategy implements ElitismStrategyInterface
{
    public function __construct(
        private readonly int $eliteCount = 1
    ) {}

    public function selectElites(array $population): array
    {
        if ($this->eliteCount <= 0) {
            return [];
        }

        usort(
            $population,
            fn (Cromossomo $a, Cromossomo $b) => $b->fitness() <=> $a->fitness()
        );

        $elites = array_slice($population, 0, $this->eliteCount);

        // cópia defensiva
        return array_map(
            fn (Cromossomo $c) => $c->copy(),
            $elites
        );
    }
}
