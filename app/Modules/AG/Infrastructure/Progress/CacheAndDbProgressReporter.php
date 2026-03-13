<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Progress;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use App\Modules\AG\Infrastructure\Logging\GATelemetryLogger;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Support\AGError;

final class CacheAndDbProgressReporter implements ProgressReporterInterface
{
    public function __construct(
        private CacheProgressReporter $cacheReporter,
        private ExecutionMetricsRecorder $dbRecorder,
        private GATelemetryLogger $telemetryLogger,
        private int $horarioId
    ) {
    }

    public function report(array $data): void
    {
        $this->cacheReporter->report($data);

        $isEvolvingPhase = isset($data['phase']) && in_array($data['phase'], ['evolving', 'alns_intensification'], true);

        if (!$isEvolvingPhase || !isset($data['generation'])) {
            return;
        }

        $metrics = new GenerationMetrics(
            generation: (int) $data['generation'],
            bestFitness: (float) $data['best_fitness'],
            avgFitness: (float) $data['avg_fitness'],
            variance: (float) ($data['variance'] ?? 0.0),
            diversity: (float) $data['diversity'],
            entropy: (float) $data['entropy'],
            mutationRate: (float) $data['mutation_rate'],
            stagnation: (int) $data['stagnation'],
            landscapeState: $data['landscape_state'] ?? null
        );

        $this->dbRecorder->recordGeneration($metrics);
        $this->telemetryLogger->generationMetrics(
            $this->horarioId,
            $data,
            $this->dbRecorder->getExecutionId()
        );
    }

    public function reportError(AGError $error): void
    {
        $this->cacheReporter->reportError($error);
        $this->dbRecorder->flush();
    }
}
