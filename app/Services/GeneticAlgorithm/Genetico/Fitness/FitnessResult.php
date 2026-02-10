<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness;

class FitnessResult
{
    public float $totalScore;
    public array $ruleScores; // [RuleClass => score]
    public array $conflicts; // [RuleClass => [conflict_details]]

    public function __construct(float $totalScore, array $ruleScores, array $conflicts)
    {
        $this->totalScore = $totalScore;
        $this->ruleScores = $ruleScores;
        $this->conflicts = $conflicts;
    }

    public function hasConflicts(): bool
    {
        return !empty($this->conflicts);
    }

    public function getConflictDetails(): array
    {
        return $this->conflicts;
    }
}
