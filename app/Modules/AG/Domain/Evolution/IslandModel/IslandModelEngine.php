<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use Illuminate\Support\Facades\Log;

final class IslandModelEngine
{
    /** @var Island[] */
    private array $islands = [];

    private ?MetricsRecorder $globalMetrics = null;
    private ?ProgressReporterInterface $progress = null;
    private float $telemetryMutationRate = 0.05;

    public function __construct(
        private readonly MigrationPolicyInterface $migrationPolicy,
        private readonly int $migrationInterval = 20
    ) {
    }

    public function addIsland(Island $island): void
    {
        $this->islands[] = $island;
    }

    public function setTelemetry(
        MetricsRecorder $metrics,
        ProgressReporterInterface $progress,
        float $mutationRate = 0.05
    ): void {
        $this->globalMetrics = $metrics;
        $this->progress = $progress;
        $this->telemetryMutationRate = $mutationRate;
    }

    public function run(int $generations): Cromossomo
    {
        foreach ($this->islands as $island) {
            $island->initialize();
        }

        $globalBest = null;
        Log::info('Iniciando o motor de orquestracao sincronica das ilhas.');

        for ($generation = 1; $generation <= $generations; $generation++) {
            $globalPopulation = [];
            $telemetrySnapshots = [];

            foreach ($this->islands as $island) {
                $island->evolveGeneration();
                $bestInIsland = $island->best();
                $telemetrySnapshots[] = $island->telemetrySnapshot();

                if ($globalBest === null || $bestInIsland->fitness() > $globalBest->fitness()) {
                    $globalBest = $bestInIsland;
                }

                $globalPopulation = array_merge($globalPopulation, $island->population());
            }

            if ($generation % $this->migrationInterval === 0) {
                $this->migrationPolicy->migrate($this->islands);
            }

            if ($this->globalMetrics && $this->progress) {
                $mutationRates = array_values(array_filter(array_map(
                    static fn (array $snapshot) => $snapshot['mutation_rate'] ?? null,
                    $telemetrySnapshots
                ), static fn ($value) => $value !== null));

                $operatorRewards = array_values(array_filter(array_map(
                    static fn (array $snapshot) => $snapshot['operator_reward'] ?? null,
                    $telemetrySnapshots
                ), static fn ($value) => $value !== null));

                $operatorUsed = collect($telemetrySnapshots)
                    ->pluck('operator_used')
                    ->first(fn ($operator) => $operator !== null && $operator !== 'none');

                $metricsDto = $this->globalMetrics->recordExtended(
                    $generation,
                    $globalPopulation,
                    empty($mutationRates) ? $this->telemetryMutationRate : array_sum($mutationRates) / count($mutationRates),
                    0,
                    'Exploracao Intensiva'
                );

                $this->progress->report([
                    'phase' => 'evolving',
                    'generation' => $metricsDto->generation,
                    'max_generations' => $generations,
                    'best_fitness' => $metricsDto->bestFitness,
                    'avg_fitness' => $metricsDto->avgFitness,
                    'variance' => $metricsDto->variance,
                    'diversity' => $metricsDto->diversity,
                    'entropy' => $metricsDto->entropy,
                    'mutation_rate' => $metricsDto->mutationRate,
                    'stagnation' => $metricsDto->stagnation,
                    'landscape_state' => $metricsDto->landscapeState,
                    'operator_used' => $operatorUsed,
                    'operator_reward' => empty($operatorRewards) ? 0.0 : array_sum($operatorRewards) / count($operatorRewards),
                ]);
            }
        }

        return $globalBest;
    }
}
