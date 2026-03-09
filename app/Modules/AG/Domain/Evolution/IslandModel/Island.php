<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use App\Modules\AG\Application\GeneticAlgorithmEngine;
use App\Modules\AG\Domain\Operators\Replacement\ReplacementStrategyInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class Island {
    private array $population = [];

    public function __construct(
        private readonly GeneticAlgorithmEngine $engine,
        private readonly int $populationSize,
        private readonly ReplacementStrategyInterface $replacement
    ) {
    }

    public function initialize(): void {
        $this->population = [];

        for ($i = 0; $i < $this->populationSize; $i++) {

            $individual = $this->engine->createIndividual();

            $this->population[] = $individual;
        }
    }

    public function evolveGeneration(): Cromossomo {
        $this->population = $this->engine->evolveGeneration(
            $this->population,
            $this->populationSize
        );

        return $this->best();
    }

    public function best(): Cromossomo {
        usort(
            $this->population,
            fn($a, $b) => $b->fitness() <=> $a->fitness()
        );

        return $this->population[0];
    }

    public function population(): array {
        return $this->population;
    }

    public function injectIndividual(Cromossomo $individual): void {
        $this->replacement->replace(
            $this->population,
            $individual
        );
    }
}
