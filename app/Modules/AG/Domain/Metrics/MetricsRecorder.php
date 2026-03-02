<?php

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class MetricsRecorder {
    private array $generationData = [];

    private ?Cromossomo $bestOverall = null;

    private float $bestFitnessOverall = -INF; // Maximização

    public function record(int $generation, array $population): void {
        if (empty($population)) {
            return;
        }

        $bestFitness = -INF;
        $worstFitness = INF;
        $bestIndividual = null;

        $sum = 0.0;
        $count = count($population);

        foreach ($population as $cromossomo) {

            $fitness = $cromossomo->fitness();

            $sum += $fitness;

            if ($fitness > $bestFitness) {
                $bestFitness = $fitness;
                $bestIndividual = $cromossomo;
            }

            if ($fitness < $worstFitness) {
                $worstFitness = $fitness;
            }
        }

        $averageFitness = $sum / $count;

        // Atualiza melhor global
        if ($bestFitness > $this->bestFitnessOverall) {

            $this->bestFitnessOverall = $bestFitness;

            // cópia defensiva
            $this->bestOverall = $bestIndividual?->copy();
        }

        $this->generationData[$generation] = [
            'best_fitness' => $bestFitness,
            'average_fitness' => $averageFitness,
            'worst_fitness' => $worstFitness,
        ];
    }

    public function bestOverall(): ?Cromossomo {
        return $this->bestOverall;
    }

    public function bestFitnessOverall(): float {
        return $this->bestFitnessOverall;
    }

    public function generationData(): array {
        return $this->generationData;
    }
}
