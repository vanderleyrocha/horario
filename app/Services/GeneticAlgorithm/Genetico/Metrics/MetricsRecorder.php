<?php

namespace App\Services\GeneticAlgorithm\Genetico\Metrics;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;

final class MetricsRecorder
{
    private array $generationData = [];

    private ?Cromossomo $bestOverall = null;

    private float $bestFitnessOverall = INF;

    public function recordGeneration(int $generation, array $population): void
    {
        if (empty($population)) {
            return;
        }

        $bestFitness = INF;
        $bestIndividual = null;
        $sum = 0.0;
        $count = count($population);

        foreach ($population as $cromossomo) {

            $fitness = $cromossomo->getFitness();

            $sum += $fitness;

            if ($fitness < $bestFitness) {
                $bestFitness = $fitness;
                $bestIndividual = $cromossomo;
            }
        }

        $averageFitness = $sum / $count;

        // Atualiza melhor global se necessário
        if ($bestFitness < $this->bestFitnessOverall) {

            $this->bestFitnessOverall = $bestFitness;

            // IMPORTANTE: copiar para evitar mutação posterior
            $this->bestOverall = $bestIndividual?->copy();
        }

        $this->generationData[$generation] = [
            'best_fitness' => $bestFitness,
            'average_fitness' => $averageFitness,
        ];
    }

    public function getBestCromossomoOverall(): ?Cromossomo
    {
        return $this->bestOverall;
    }

    public function getBestFitnessOverall(): float
    {
        return $this->bestFitnessOverall;
    }

    public function getGenerationData(): array
    {
        return $this->generationData;
    }
}