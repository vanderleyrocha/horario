<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use App\Helpers\DateTimeHelper;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use Illuminate\Support\Facades\Log;

final class IslandModelEngine
{
    /** @var Island[] */
    private array $islands = [];

    private ?MetricsRecorder $globalMetrics = null;
    private ?ProgressReporterInterface $progress = null;

    public function __construct(private readonly MigrationPolicyInterface $migrationPolicy, private readonly int $migrationInterval = 20)
    {
    }

    public function addIsland(Island $island): void
    {
        $this->islands[] = $island;
    }

    // Método injetor para ligar o Motor ao Frontend
    public function setTelemetry(MetricsRecorder $metrics, ProgressReporterInterface $progress): void
    {
        $this->globalMetrics = $metrics;
        $this->progress = $progress;
    }

    public function run(int $generations): Cromossomo
    {
        foreach ($this->islands as $island) {
            $island->initialize();
        }

        $globalBest = null;
        Log::info("Iniciando o motor de orquestração síncrona das Ilhas!");

        for ($generation = 1; $generation <= $generations; $generation++) {

            $globalPopulation = [];

            // 1. Evolução Sequencial (Garante a mutação real do estado)
            foreach ($this->islands as $index => $island) {
                $island->evolveGeneration();
                $bestInIsland = $island->best();

                if ($globalBest === null || $bestInIsland->fitness() > $globalBest->fitness()) {
                    $globalBest = $bestInIsland;
                }

                // Agrupa as populações para cálculo estatístico científico
                $globalPopulation = array_merge($globalPopulation, $island->population());
            }

            // 2. Migração
            if ($generation % $this->migrationInterval === 0) {
                $this->migrationPolicy->migrate($this->islands);
            }

            // 3. TELEMETRIA: Atualização em Tempo Real do Frontend
            if ($this->globalMetrics && $this->progress) {
                // Registra métricas globais (Entropia e Diversidade da meta-população)
                $metricsDto = $this->globalMetrics->recordExtended($generation, $globalPopulation, 0.05, 0, 'Exploração Intensiva');

                $this->progress->report([
                    'phase' => 'evolving',
                    'generation' => $metricsDto->generation,
                    'best_fitness' => $metricsDto->bestFitness,
                    'avg_fitness' => $metricsDto->avgFitness,
                    'variance' => $metricsDto->variance,
                    'diversity' => $metricsDto->diversity,
                    'entropy' => $metricsDto->entropy,
                    'mutation_rate' => $metricsDto->mutationRate,
                    'stagnation' => $metricsDto->stagnation,
                    'landscape_state' => $metricsDto->landscapeState
                ]);
            }
        }

        return $globalBest;
    }
}
