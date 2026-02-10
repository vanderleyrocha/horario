<?php

namespace App\Services\GeneticAlgorithm\Genetico\Metrics;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use Illuminate\Support\Collection;

class MetricsRecorder
{
    private array $generationData = []; // Armazena métricas por geração
    private ?Cromossomo $bestCromossomoOverall = null;
    private float $bestFitnessOverall = -INF;

    public function recordGeneration(int $generation, Collection $population): void
    {
        if ($population->isEmpty()) {
            return;
        }

        $fitnessScores = $population->map(fn(Cromossomo $c) => $c->getFitnessScore());

        $bestInGeneration = $fitnessScores->max();
        $averageInGeneration = $fitnessScores->avg();
        $worstInGeneration = $fitnessScores->min();

        $this->generationData[$generation] = [
            'best_fitness' => $bestInGeneration,
            'average_fitness' => $averageInGeneration,
            'worst_fitness' => $worstInGeneration,
            // Outras métricas, como número de conflitos hard/soft, etc.
        ];

        // Atualiza o melhor cromossomo geral
        $currentBestCromossomo = $population->sortByDesc(fn(Cromossomo $c) => $c->getFitnessScore())->first();
        if ($currentBestCromossomo && $currentBestCromossomo->getFitnessScore() > $this->bestFitnessOverall) {
            $this->bestFitnessOverall = $currentBestCromossomo->getFitnessScore();
            $this->bestCromossomoOverall = $currentBestCromossomo->clone();
        }
    }

    public function getGenerationData(): array
    {
        return $this->generationData;
    }

    public function getBestCromossomoOverall(): ?Cromossomo
    {
        return $this->bestCromossomoOverall;
    }

    public function getBestFitnessOverall(): float
    {
        return $this->bestFitnessOverall;
    }
}
