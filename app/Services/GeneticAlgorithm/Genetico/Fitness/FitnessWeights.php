<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness;

class FitnessWeights
{
    private array $weights = []; // [RuleClass => weight]

    public function __construct(array $initialWeights = [])
    {
        $this->weights = $initialWeights;
    }

    public function setWeight(string $ruleClass, float $weight): void
    {
        $this->weights[$ruleClass] = $weight;
    }

    public function getWeight(string $ruleClass): float
    {
        return $this->weights[$ruleClass] ?? 1.0; // Peso padrão de 1.0 se não configurado
    }

    public function getAllWeights(): array
    {
        return $this->weights;
    }
}
