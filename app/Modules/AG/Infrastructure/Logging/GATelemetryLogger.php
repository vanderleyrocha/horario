<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Logging;

use Illuminate\Support\Facades\Log;
use Throwable;

final class GATelemetryLogger
{
    public function executionStarted(int $horarioId, array $configuracao, ?int $executionId = null): void
    {
        Log::channel('ga')->info('ga.execution.started', [
            'horario_id' => $horarioId,
            'execution_id' => $executionId,
            'configuracao' => $configuracao,
            'started_at' => now()->toIso8601String(),
        ]);
    }

    public function generationMetrics(int $horarioId, array $metrics, ?int $executionId = null): void
    {
        Log::channel('ga')->info('ga.generation.metrics', [
            'horario_id' => $horarioId,
            'execution_id' => $executionId,
            'generation' => (int) ($metrics['generation'] ?? 0),
            'max_generations' => (int) ($metrics['max_generations'] ?? 0),
            'phase' => $metrics['phase'] ?? null,
            'best_fitness' => round((float) ($metrics['best_fitness'] ?? 0), 6),
            'avg_fitness' => round((float) ($metrics['avg_fitness'] ?? 0), 6),
            'variance' => round((float) ($metrics['variance'] ?? 0), 6),
            'diversity' => round((float) ($metrics['diversity'] ?? 0), 6),
            'entropy' => round((float) ($metrics['entropy'] ?? 0), 6),
            'mutation_rate' => round((float) ($metrics['mutation_rate'] ?? 0), 6),
            'stagnation' => (int) ($metrics['stagnation'] ?? 0),
            'landscape_state' => $metrics['landscape_state'] ?? null,
            'operator_used' => $metrics['operator_used'] ?? null,
            'operator_reward' => round((float) ($metrics['operator_reward'] ?? 0), 6),
            'alns_destroy_operator' => $metrics['alns_destroy_operator'] ?? null,
            'alns_repair_operator' => $metrics['alns_repair_operator'] ?? null,
            'alns_improvement' => isset($metrics['alns_improvement'])
                ? round((float) $metrics['alns_improvement'], 6)
                : null,
            'recorded_at' => now()->toIso8601String(),
        ]);
    }

    public function executionCompleted(int $horarioId, float $bestFitness, array $extra = [], ?int $executionId = null): void
    {
        Log::channel('ga')->info('ga.execution.completed', array_merge([
            'horario_id' => $horarioId,
            'execution_id' => $executionId,
            'best_fitness' => round($bestFitness, 6),
            'completed_at' => now()->toIso8601String(),
        ], $extra));
    }

    public function executionFailed(int $horarioId, Throwable $exception, array $extra = [], ?int $executionId = null): void
    {
        Log::channel('ga')->error('ga.execution.failed', array_merge([
            'horario_id' => $horarioId,
            'execution_id' => $executionId,
            'error_class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'failed_at' => now()->toIso8601String(),
        ], $extra));
    }
}
