<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class PopulationStatistics
{
    private ?float $lastDiversity = null;

    private int $lastDiversityGeneration = -1;

    public function __construct(
        private readonly ?DiversityCalculatorInterface $diversityCalculator = null,
        private readonly ?PopulationEntropyCalculator $entropyCalculator = null,
        private readonly int $diversitySamplingInterval = 1,
        private readonly ?float $diversityCollapseThreshold = null
    ) {}

    /**
     * @param  Cromossomo[]  $population
     */
    public function calculate(array $population, ?int $generation = null): array
    {
        $n = count($population);

        if ($n === 0) {
            throw new \RuntimeException('Population cannot be empty');
        }

        $fitnessSummary = $this->calculateFitnessSummary($population);
        $diversity = $this->resolveDiversity($population, $generation);
        $entropy = $this->entropyCalculator?->normalized($population) ?? 0.0;

        return [
            'best' => $fitnessSummary['best'],
            'average' => $fitnessSummary['average'],
            'variance' => $fitnessSummary['variance'],
            'diversity' => $diversity,
            'entropy' => $entropy,
        ];
    }

    /**
     * @param  Cromossomo[]  $population
     */
    public function toGenerationMetrics(
        int $generation,
        array $population,
        float $mutationRate,
        int $stagnation,
        ?string $landscapeState = null
    ): GenerationMetrics {
        $stats = $this->calculate($population, $generation);

        return new GenerationMetrics(
            generation: $generation,
            bestFitness: (float) $stats['best'],
            avgFitness: (float) $stats['average'],
            variance: (float) $stats['variance'],
            diversity: (float) $stats['diversity'],
            entropy: (float) $stats['entropy'],
            mutationRate: $mutationRate,
            stagnation: $stagnation,
            landscapeState: $landscapeState
        );
    }

    /**
     * @param  Cromossomo[]  $population
     * @return array{best: float, average: float, variance: float}
     */
    private function calculateFitnessSummary(array $population): array
    {
        $fitnessValues = [];
        $sum = 0.0;
        $best = -INF;

        foreach ($population as $individual) {
            $fitness = $individual->fitness();
            $fitnessValues[] = $fitness;
            $sum += $fitness;

            if ($fitness > $best) {
                $best = $fitness;
            }
        }

        $average = $sum / count($fitnessValues);

        return [
            'best' => $best,
            'average' => $average,
            'variance' => $this->variance($fitnessValues, $average),
        ];
    }

    /**
     * @param  Cromossomo[]  $population
     */
    private function resolveDiversity(array $population, ?int $generation): float
    {
        if ($this->diversityCalculator === null) {
            return 0.0;
        }

        if ($generation === null || $this->diversitySamplingInterval <= 1) {
            return $this->storeDiversity($this->diversityCalculator->calculate($population), $generation);
        }

        if (
            $this->lastDiversity !== null &&
            $this->diversityCollapseThreshold !== null &&
            $this->lastDiversity <= $this->diversityCollapseThreshold
        ) {
            return $this->lastDiversity;
        }

        if ($this->lastDiversity === null || $this->lastDiversityGeneration < 0) {
            return $this->storeDiversity($this->diversityCalculator->calculate($population), $generation);
        }

        if ($generation === $this->lastDiversityGeneration) {
            return $this->lastDiversity;
        }

        if ($generation % $this->diversitySamplingInterval !== 0) {
            return $this->lastDiversity;
        }

        return $this->storeDiversity($this->diversityCalculator->calculate($population), $generation);
    }

    private function storeDiversity(float $diversity, ?int $generation): float
    {
        $this->lastDiversity = $diversity;

        if ($generation !== null) {
            $this->lastDiversityGeneration = $generation;
        }

        return $diversity;
    }

    private function variance(array $values, float $mean): float
    {
        $sum = 0.0;

        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return $sum / count($values);
    }
}
