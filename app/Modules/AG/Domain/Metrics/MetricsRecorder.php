<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Metrics;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class MetricsRecorder {
    /**
     * @var array<int,array<string,mixed>>
     */
    private array $generationMetrics = [];

    private float $bestFitnessOverall = 0.0;

    private ?DiversityCalculatorInterface $diversityCalculator = null;

    /**
     * Permite injetar cálculo de diversidade genética
     */
    public function setDiversityCalculator(
        DiversityCalculatorInterface $calculator
    ): void {
        $this->diversityCalculator = $calculator;
    }

    /**
     * Registra métricas de uma geração
     *
     * @param Cromossomo[] $population
     */
    public function record(int $generation, array $population): void {
        if (empty($population)) {
            return;
        }

        $fitnessValues = array_map(
            fn(Cromossomo $c) => $c->fitness(),
            $population
        );

        $best = max($fitnessValues);

        if ($best > $this->bestFitnessOverall) {
            $this->bestFitnessOverall = $best;
        }

        $average = $this->calculateAverage($fitnessValues);

        $variance = $this->calculateVariance($fitnessValues, $average);

        $diversity = 0.0;

        if ($this->diversityCalculator !== null) {
            $diversity = $this->diversityCalculator->calculate($population);
        }

        $this->generationMetrics[] = [
            'generation' => $generation,
            'best_fitness' => $best,
            'average_fitness' => $average,
            'variance' => $variance,
            'diversity' => $diversity,
        ];
    }

    /**
     * Média do fitness
     *
     * @param float[] $values
     */
    private function calculateAverage(array $values): float {
        if (empty($values)) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * Variância do fitness
     *
     * @param float[] $values
     */
    private function calculateVariance(array $values, float $mean): float {
        $sum = 0.0;

        foreach ($values as $value) {
            $sum += ($value - $mean) ** 2;
        }

        return $sum / count($values);
    }

    /**
     * Melhor fitness de toda execução
     */
    public function bestFitnessOverall(): float {
        return $this->bestFitnessOverall;
    }

    /**
     * Dados completos por geração
     *
     * @return array<int,array<string,mixed>>
     */
    public function generationData(): array {
        return $this->generationMetrics;
    }

    /**
     * Última geração registrada
     */
    public function lastGeneration(): ?array {
        if (empty($this->generationMetrics)) {
            return null;
        }

        return $this->generationMetrics[array_key_last($this->generationMetrics)];
    }

    /**
     * Número total de gerações registradas
     */
    public function generationCount(): int {
        return count($this->generationMetrics);
    }

    /**
     * Limpa histórico
     */
    public function reset(): void {
        $this->generationMetrics = [];
        $this->bestFitnessOverall = 0.0;
    }
}
