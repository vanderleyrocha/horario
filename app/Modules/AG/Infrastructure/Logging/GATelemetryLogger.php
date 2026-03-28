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
            'landscape_phenomenon' => $metrics['landscape_phenomenon'] ?? null,
            'landscape_confidence' => isset($metrics['landscape_observation']['confidence'])
                ? round((float) $metrics['landscape_observation']['confidence'], 6)
                : null,
            'landscape_depth_score' => isset($metrics['landscape_observation']['depth_score'])
                ? round((float) $metrics['landscape_observation']['depth_score'], 6)
                : null,
            'landscape_best_delta_window' => isset($metrics['landscape_observation']['best_delta_window'])
                ? round((float) $metrics['landscape_observation']['best_delta_window'], 6)
                : null,
            'landscape_avg_delta_window' => isset($metrics['landscape_observation']['avg_delta_window'])
                ? round((float) $metrics['landscape_observation']['avg_delta_window'], 6)
                : null,
            'landscape_improvement_acceptance_rate' => isset($metrics['landscape_observation']['improvement_acceptance_rate'])
                ? round((float) $metrics['landscape_observation']['improvement_acceptance_rate'], 6)
                : null,
            'landscape_worsening_acceptance_rate' => isset($metrics['landscape_observation']['worsening_acceptance_rate'])
                ? round((float) $metrics['landscape_observation']['worsening_acceptance_rate'], 6)
                : null,
            'landscape_population_turnover' => isset($metrics['landscape_observation']['population_turnover'])
                ? round((float) $metrics['landscape_observation']['population_turnover'], 6)
                : null,
            'landscape_best_signature_changed' => isset($metrics['landscape_observation']['best_signature_changed'])
                ? (bool) $metrics['landscape_observation']['best_signature_changed']
                : null,
            'landscape_elite_similarity' => isset($metrics['landscape_observation']['elite_similarity'])
                ? round((float) $metrics['landscape_observation']['elite_similarity'], 6)
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
