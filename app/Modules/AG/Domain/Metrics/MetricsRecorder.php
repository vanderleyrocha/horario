<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use Illuminate\Support\Facades\Cache;

final class MetricsRecorder
{
    private array $generationMetrics = [];

    private float $bestFitnessOverall = 0.0;
    private ?int $executionId = null;

    /*
     |----------------------------------------------------
     | Diversity optimisation
     |----------------------------------------------------
     */

    private int $diversitySamplingInterval = 5;
    private float $lastDiversity = 1.0;
    private int $lastDiversityGeneration = -1;

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

    public function setExecutionId(int $executionId): void
    {
        $this->executionId = $executionId;
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

        /*
         |----------------------------------------------------
         | Fitness metrics
         |----------------------------------------------------
         */

        $fitnessValues = [];

        foreach ($population as $c) {
            $fitnessValues[] = $c->fitness();
        }

        $best = max($fitnessValues);

        if ($best > $this->bestFitnessOverall) {
            $this->bestFitnessOverall = $best;
        }

        $count = count($fitnessValues);
        $avg = array_sum($fitnessValues) / $count;

        $variance = $this->variance($fitnessValues, $avg);

        /*
         |----------------------------------------------------
         | Diversity (optimized)
         |----------------------------------------------------
         */

        if ($this->diversityCalculator) {

            // Early stop when diversity collapsed
            if ($this->lastDiversity < 0.05) {

                $diversity = $this->lastDiversity;

            } else {

                // Sampling interval
                if (
                    $generation % $this->diversitySamplingInterval === 0 &&
                    $generation !== $this->lastDiversityGeneration
                ) {

                    $this->lastDiversity =
                        $this->diversityCalculator->calculate($population);

                    $this->lastDiversityGeneration = $generation;
                }

                $diversity = $this->lastDiversity;
            }

        } else {

            $diversity = 0.0;
        }

        /*
         |----------------------------------------------------
         | Entropy
         |----------------------------------------------------
         */

        $entropy = $this->entropyCalculator
            ? $this->entropyCalculator->normalized($population)
            : 0.0;

        /*
         |----------------------------------------------------
         | Store generation data
         |----------------------------------------------------
         */

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
            $d = $v - $mean;
            $sum += $d * $d;
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

    public function publishGenerationMetrics(array $metrics): void
    {
        if ($this->executionId === null) {
            return;
        }

        $metrics['execution_id'] = $this->executionId;
        $metrics['timestamp'] = microtime(true);

        Cache::put("ga_execution_metrics_{$this->executionId}", $metrics, now()->addMinutes(10));
    }

    public function getExecutionId(): ?int
    {
        return $this->executionId;
    }
}
