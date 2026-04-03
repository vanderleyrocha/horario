<?php

namespace App\Modules\AG\Application\Progress;

class EvolutionProgress
{
    public string $phase;

    public int $generation;

    public int $maxGenerations;

    public float $bestFitness;

    public float $avgFitness;

    public float $diversity;

    public float $entropy;

    public float $mutationRate;

    public int $stagnation;

    public string $landscapeState;

    public function toArray(): array
    {
        return [
            'phase' => $this->phase,
            'generation' => $this->generation,
            'max_generations' => $this->maxGenerations,
            'best_fitness' => $this->bestFitness,
            'avg_fitness' => $this->avgFitness,
            'diversity' => $this->diversity,
            'entropy' => $this->entropy,
            'mutation_rate' => $this->mutationRate,
            'stagnation' => $this->stagnation,
            'landscape_state' => $this->landscapeState ?? 'unknown',
        ];
    }
}
