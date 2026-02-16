<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness;

final class FitnessResult
{
    public function __construct(public readonly float $totalPenalty, public readonly float $hardPenalty, public readonly float $softPenalty) {}
}