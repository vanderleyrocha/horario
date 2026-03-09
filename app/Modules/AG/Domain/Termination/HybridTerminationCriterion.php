<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Termination;

use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class HybridTerminationCriterion implements TerminationCriterionInterface {
    private float $bestFitness = 0.0;

    private int $generationsWithoutImprovement = 0;

    public function __construct(
        private readonly int $maxGenerations,
        private readonly float $targetFitness,
        private readonly int $maxStagnation,
        private readonly float $minDiversity,
        private readonly float $minEntropy,
        private readonly MetricsRecorder $metrics
    ) {
    }

    /**
     * @param Cromossomo[] $population
     */
    public function shouldTerminate(int $generation, array $population): bool {
        if ($generation >= $this->maxGenerations) {
            return true;
        }

        $best = max(array_map(
            fn(Cromossomo $c) => $c->fitness(),
            $population
        ));

        /**
         * Atualiza estagnação
         */
        if ($best > $this->bestFitness) {

            $this->bestFitness = $best;

            $this->generationsWithoutImprovement = 0;
        } else {

            $this->generationsWithoutImprovement++;
        }

        /**
         * Fitness alvo
         */
        if ($best >= $this->targetFitness) {
            return true;
        }

        /**
         * Estagnação prolongada
         */
        if ($this->generationsWithoutImprovement >= $this->maxStagnation) {
            return true;
        }

        /**
         * Convergência genética
         */
        $last = $this->metrics->lastGeneration();

        if ($last !== null) {

            $diversity = $last['diversity'] ?? 1.0;
            $entropy   = $last['entropy'] ?? 1.0;

            if (
                $diversity < $this->minDiversity &&
                $entropy   < $this->minEntropy
            ) {

                return true;
            }
        }

        return false;
    }

    public function getGenerationsWithoutImprovement(): int {
        return $this->generationsWithoutImprovement;
    }

    public function getMaxGenerations(): ?int {
        return $this->maxGenerations;
    }
}
