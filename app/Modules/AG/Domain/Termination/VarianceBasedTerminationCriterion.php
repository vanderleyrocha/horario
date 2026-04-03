<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Termination;

use App\Modules\AG\Domain\Metrics\PopulationStatistics;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class VarianceBasedTerminationCriterion implements TerminationCriterionInterface
{
    private int $generationsWithoutImprovement = 0;

    private float $bestFitnessSeen = -INF;

    /**
     * @var float[]
     */
    private array $varianceWindow = [];

    public function __construct(
        private readonly int $maxGenerations,
        private readonly PopulationStatistics $populationStatistics,
        private readonly float $targetFitness = 0.0,
        private readonly int $maxGenerationsWithoutImprovement = 50,
        private readonly float $varianceThreshold = 0.0005,
        private readonly int $varianceWindowSize = 8,
        private readonly int $minGenerationsBeforeVarianceCheck = 20,
        private readonly ?float $minDiversity = 0.08,
        private readonly ?float $minEntropy = 0.10
    ) {}

    /**
     * @param  Cromossomo[]  $population
     */
    public function shouldTerminate(int $generation, array $population): bool
    {
        if ($generation >= $this->maxGenerations) {
            return true;
        }

        if ($population === []) {
            return true;
        }

        $stats = $this->populationStatistics->calculate($population);
        $bestCurrent = (float) $stats['best'];

        if ($bestCurrent > $this->bestFitnessSeen) {
            $this->bestFitnessSeen = $bestCurrent;
            $this->generationsWithoutImprovement = 0;
        } else {
            $this->generationsWithoutImprovement++;
        }

        if ($this->targetFitness > 0.0 && $bestCurrent >= $this->targetFitness) {
            return true;
        }

        if ($this->generationsWithoutImprovement >= $this->maxGenerationsWithoutImprovement) {
            return true;
        }

        if ($generation < $this->minGenerationsBeforeVarianceCheck) {
            return false;
        }

        $this->pushVariance((float) $stats['variance']);

        if (count($this->varianceWindow) < $this->varianceWindowSize) {
            return false;
        }

        $maxVariance = max($this->varianceWindow);

        if ($maxVariance > $this->varianceThreshold) {
            return false;
        }

        if ($this->minDiversity === null && $this->minEntropy === null) {
            return true;
        }

        $diversity = (float) $stats['diversity'];
        $entropy = (float) $stats['entropy'];

        $diversityCollapsed = $this->minDiversity === null || $diversity <= $this->minDiversity;
        $entropyCollapsed = $this->minEntropy === null || $entropy <= $this->minEntropy;

        return $diversityCollapsed && $entropyCollapsed;
    }

    public function getGenerationsWithoutImprovement(): int
    {
        return $this->generationsWithoutImprovement;
    }

    public function getMaxGenerations(): ?int
    {
        return $this->maxGenerations;
    }

    private function pushVariance(float $variance): void
    {
        $this->varianceWindow[] = max(0.0, $variance);

        if (count($this->varianceWindow) <= $this->varianceWindowSize) {
            return;
        }

        $this->varianceWindow = array_slice($this->varianceWindow, -$this->varianceWindowSize);
    }
}
