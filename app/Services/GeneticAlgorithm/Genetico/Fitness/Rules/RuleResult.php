<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules;

class RuleResult
{
    public float $score;
    public array $conflicts;

    public function __construct(float $score, array $conflicts = [])
    {
        $this->score = $score;
        $this->conflicts = $conflicts;
    }
}
