<?php

namespace App\Modules\AG\Domain\Termination;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class MaxGenerationsOrFitnessCriterion
implements TerminationCriterionInterface {
    private int $generationsWithoutImprovement = 0;
    private float $bestFitnessSeen = -INF; // Maximização

    public function __construct(
        private readonly int $maxGenerations,
        private readonly float $targetFitness = 0.0,
        private readonly int $maxGenerationsWithoutImprovement = 50
    ) {
    }

    /**
     * @param Cromossomo[] $population
     */
    public function shouldTerminate(
        int $generation,
        array $population
    ): bool {

        /* ============================================================
         | 1️⃣ Limite absoluto de gerações
         ============================================================ */
        if ($generation >= $this->maxGenerations) {
            return true;
        }

        if (empty($population)) {
            return true;
        }

        /* ============================================================
         | 2️⃣ Melhor fitness atual
         |    (Engine já ordena, então índice 0 é o melhor)
         ============================================================ */
        $bestCurrent = $population[0]->fitness();

        /* ============================================================
         | 3️⃣ Controle de estagnação
         ============================================================ */
        if ($bestCurrent > $this->bestFitnessSeen) {

            $this->bestFitnessSeen = $bestCurrent;
            $this->generationsWithoutImprovement = 0;
        } else {

            $this->generationsWithoutImprovement++;
        }

        /* ============================================================
         | 4️⃣ Target fitness
         ============================================================ */
        if (
            $this->targetFitness > 0.0 &&
            $bestCurrent >= $this->targetFitness
        ) {
            return true;
        }

        /* ============================================================
         | 5️⃣ Estagnação prolongada
         ============================================================ */
        if (
            $this->generationsWithoutImprovement
            >= $this->maxGenerationsWithoutImprovement
        ) {
            return true;
        }

        return false;
    }

    public function getGenerationsWithoutImprovement(): int {
        return $this->generationsWithoutImprovement;
    }
}
