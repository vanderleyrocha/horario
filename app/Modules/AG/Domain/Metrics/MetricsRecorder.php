<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use Illuminate\Support\Facades\Cache;

final class MetricsRecorder
{
    private array $generationMetrics = [];

    private float $bestFitnessOverall = 0.0;

    private ?int $executionId = null;

    private ?DiversityCalculatorInterface $diversityCalculator = null;

    private ?PopulationEntropyCalculator $entropyCalculator = null;

    private ?PopulationStatistics $populationStatistics = null;

    public function setDiversityCalculator(DiversityCalculatorInterface $calculator): void
    {
        $this->diversityCalculator = $calculator;
        $this->rebuildPopulationStatistics();
    }

    public function setEntropyCalculator(PopulationEntropyCalculator $calculator): void
    {
        $this->entropyCalculator = $calculator;
        $this->rebuildPopulationStatistics();
    }

    public function setPopulationStatistics(PopulationStatistics $populationStatistics): void
    {
        $this->populationStatistics = $populationStatistics;
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
            throw new \RuntimeException('Population cannot be empty');
        }

        $metrics = $this->resolvePopulationStatistics()->toGenerationMetrics(
            generation: $generation,
            population: $population,
            mutationRate: $mutationRate,
            stagnation: $stagnation,
            landscapeState: $landscapeState
        );

        if ($metrics->bestFitness > $this->bestFitnessOverall) {
            $this->bestFitnessOverall = $metrics->bestFitness;
        }

        $this->generationMetrics[] = $metrics->toArray();

        return $metrics;
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

    private function rebuildPopulationStatistics(): void
    {
        $this->populationStatistics = new PopulationStatistics(
            diversityCalculator: $this->diversityCalculator,
            entropyCalculator: $this->entropyCalculator,
            diversitySamplingInterval: 5,
            diversityCollapseThreshold: 0.05
        );
    }

    private function resolvePopulationStatistics(): PopulationStatistics
    {
        if ($this->populationStatistics === null) {
            $this->rebuildPopulationStatistics();
        }

        return $this->populationStatistics
            ?? throw new \LogicException('PopulationStatistics could not be initialized.');
    }
}
