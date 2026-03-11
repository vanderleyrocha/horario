<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Progress;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use App\Modules\AG\Support\AGError;

final class CacheAndDbProgressReporter implements ProgressReporterInterface
{
    public function __construct(private CacheProgressReporter $cacheReporter, private ExecutionMetricsRecorder $dbRecorder)
    {
    }

    public function report(array $data): void
    {
        // 1. Atualiza o Cache em tempo real para o Livewire
        $this->cacheReporter->report($data);

        // 2. Filtra apenas os eventos de evolução para persistência científica
        $isEvolvingPhase = isset($data['phase']) && in_array($data['phase'], ['evolving', 'alns_intensification']);

        if ($isEvolvingPhase && isset($data['generation'])) {
            $metrics = new GenerationMetrics(generation: (int) $data['generation'], bestFitness: (float) $data['best_fitness'], avgFitness: (float) $data['avg_fitness'], variance: (float) ($data['variance'] ?? 0.0), // Se não existir no DTO ainda, usamos fallback
                diversity: (float) $data['diversity'], entropy: (float) $data['entropy'], mutationRate: (float) $data['mutation_rate'], stagnation: (int) $data['stagnation'], landscapeState: $data['landscape_state'] ?? null);

            // O dbRecorder gerencia o buffer internamente (salvando a cada 25 gerações)
            $this->dbRecorder->recordGeneration($metrics);
        }
    }

    // Como a interface pode prever erros, garantimos que o buffer do DB seja salvo em caso de falha
    public function reportError(AGError $error): void
    {
        $this->cacheReporter->reportError($error);
        $this->dbRecorder->flush();
    }
}
