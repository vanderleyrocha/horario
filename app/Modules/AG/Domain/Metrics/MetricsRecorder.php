<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class MetricsRecorder
{
    private array $generationMetrics = [];

    private float $bestFitnessOverall = 0.0;

    private ?DiversityCalculatorInterface $diversityCalculator = null;

    private ?PopulationEntropyCalculator $entropyCalculator = null;

    public function setDiversityCalculator(DiversityCalculatorInterface $calculator): void
    {
        $this->diversityCalculator = $calculator;
    }

    public function setEntropyCalculator(PopulationEntropyCalculator $calculator): void
    {
        $this->entropyCalculator = $calculator;
    }

    public function record(int $generation, array $population): void
    {
        $this->recordExtended($generation, $population, 0.0, 0);
    }

    public function recordExtended(int $generation, array $population, float $mutationRate, int $stagnation, ?string $landscapeState = null): GenerationMetrics
    {

        if (empty($population)) {
            throw new \RuntimeException("Population cannot be empty");
        }

        $fitnessValues = array_map(fn (Cromossomo $c) => $c->fitness(), $population);

        $best = max($fitnessValues);

        if ($best > $this->bestFitnessOverall) {
            $this->bestFitnessOverall = $best;
        }

        $avg = array_sum($fitnessValues) / count($fitnessValues);

        $variance = $this->variance($fitnessValues, $avg);

        $diversity = $this->diversityCalculator
            ? $this->diversityCalculator->calculate($population)
            : 0.0;

        $entropy = $this->entropyCalculator
            ? $this->entropyCalculator->normalized($population)
            : 0.0;

        $generationData = [
            'generation' => $generation,
            'best_fitness' => $best,
            'average_fitness' => $avg,
            'variance' => $variance,
            'diversity' => $diversity,
            'entropy' => $entropy,
            'mutation_rate' => $mutationRate,
            'stagnation' => $stagnation,
            'landscape_state' => $landscapeState,
        ];

        $this->generationMetrics[] = $generationData;

        return new GenerationMetrics($generation, $best, $avg, $variance, $diversity, $entropy, $mutationRate, $stagnation, $landscapeState);
    }

    private function variance(array $values, float $mean): float
    {
        $sum = 0.0;

        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return $sum / count($values);
    }

    public function lastGeneration(): ?array
    {
        if (empty($this->generationMetrics)) {
            return null;
        }

        return $this->generationMetrics[array_key_last($this->generationMetrics)];
    }

    public function lastEntropy(): float
    {
        $last = $this->lastGeneration();

        return $last['entropy'] ?? 1.0;
    }

    public function lastDiversity(): float
    {
        $last = $this->lastGeneration();

        return $last['diversity'] ?? 1.0;
    }

    public function bestFitnessOverall(): float
    {
        return $this->bestFitnessOverall;
    }

    public function generationData(): array
    {
        return $this->generationMetrics;
    }
}
