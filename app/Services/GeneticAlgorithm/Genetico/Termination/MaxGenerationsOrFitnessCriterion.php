<?php

namespace App\Services\GeneticAlgorithm\Genetico\Termination;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

final class MaxGenerationsOrFitnessCriterion implements TerminationCriterionInterface
{
    private int $generationsWithoutImprovement = 0;

    private float $bestFitnessSeen = INF;

    public function __construct(
        private readonly int $maxGenerations,
        private readonly float $targetFitness = 0.0,
        private readonly int $maxGenerationsWithoutImprovement = 50
    ) {}

    public function shouldTerminate(
        array $population,
        int $generation,
        ?Cromossomo $bestOverall
    ): bool {

        // 1️⃣ Critério: limite de gerações
        if ($generation >= $this->maxGenerations) {
            return true;
        }

        if (!$bestOverall) {
            return false;
        }

        $currentBest = $bestOverall->getFitness();

        // 2️⃣ Atualiza controle de estagnação
        if ($currentBest < $this->bestFitnessSeen) {

            $this->bestFitnessSeen = $currentBest;
            $this->generationsWithoutImprovement = 0;

        } else {

            $this->generationsWithoutImprovement++;
        }

        // 3️⃣ Critério: target fitness (minimização)
        if ($this->targetFitness > 0.0 &&
            $currentBest <= $this->targetFitness) {
            return true;
        }

        // 4️⃣ Critério: estagnação
        if ($this->generationsWithoutImprovement >=
            $this->maxGenerationsWithoutImprovement) {
            return true;
        }

        return false;
    }

    public function getGenerationsWithoutImprovement(): int
    {
        return $this->generationsWithoutImprovement;
    }
}