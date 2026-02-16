<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness;

final class FitnessWeights
{
    private array $weights;

    public function __construct(array $weights)
    {
        $this->weights = $weights;
    }

    public function get(string $ruleClass): float
    {
        return $this->weights[$ruleClass] ?? 1.0;
    }
}