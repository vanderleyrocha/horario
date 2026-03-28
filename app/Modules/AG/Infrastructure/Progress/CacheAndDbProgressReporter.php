<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Progress;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use App\Modules\AG\Infrastructure\Logging\GATelemetryLogger;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Support\AGError;
use Illuminate\Support\Facades\Cache;

final class CacheAndDbProgressReporter implements ProgressReporterInterface
{
    public function __construct(
        private CacheProgressReporter $cacheReporter,
        private ExecutionMetricsRecorder $dbRecorder,
        private GATelemetryLogger $telemetryLogger,
        private int $horarioId
    ) {}

    public function report(array $data): void
    {
        $this->cacheReporter->report($data);

        $isEvolvingPhase = isset($data['phase']) && in_array($data['phase'], ['evolving', 'alns_intensification'], true);

        if (! $isEvolvingPhase || ! isset($data['generation'])) {
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
            landscapeState: $data['landscape_state'] ?? null,
            operatorUsed: $data['operator_used'] ?? null,
            operatorReward: isset($data['operator_reward']) ? (float) $data['operator_reward'] : null,
            alnsDestroyOperator: $data['alns_destroy_operator'] ?? null,
            alnsRepairOperator: $data['alns_repair_operator'] ?? null,
            alnsImprovement: isset($data['alns_improvement']) ? (float) $data['alns_improvement'] : null
        );

        $this->dbRecorder->recordGeneration($metrics);

        $cachePayload = $metrics->toArray() + [
            'execution_id' => $this->dbRecorder->getExecutionId(),
            'landscape_state' => $metrics->landscapeState ?? 'unknown',
            'timestamp' => microtime(true),
        ];

        Cache::put(
            "ga_execution_metrics_{$this->dbRecorder->getExecutionId()}",
            $cachePayload,
            now()->addMinutes(10)
        );

        $this->telemetryLogger->generationMetrics(
            $this->horarioId,
            $cachePayload + ['phase' => $data['phase'] ?? null, 'max_generations' => $data['max_generations'] ?? 0],
            $this->dbRecorder->getExecutionId()
        );
    }

    public function reportError(AGError $error): void
    {
        $this->cacheReporter->reportError($error);
        $this->dbRecorder->flush();
    }
}
