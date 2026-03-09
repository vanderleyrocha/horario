<?php

namespace App\Modules\AG\Domain\Metrics\DTO;

class GenerationMetrics
{
    public int $generation;

    public float $bestFitness;

    public float $avgFitness;

    public float $variance;

    public float $diversity;

    public float $entropy;

    public float $mutationRate;

    public int $stagnation;

    public ?string $landscapeState;

    public function __construct(int $generation, float $bestFitness, float $avgFitness, float $variance, float $diversity, float $entropy, float $mutationRate, int $stagnation, ?string $landscapeState = null)
    {
        $this->generation = $generation;
        $this->bestFitness = $bestFitness;
        $this->avgFitness = $avgFitness;
        $this->variance = $variance;
        $this->diversity = $diversity;
        $this->entropy = $entropy;
        $this->mutationRate = $mutationRate;
        $this->stagnation = $stagnation;
        $this->landscapeState = $landscapeState;
    }
}
