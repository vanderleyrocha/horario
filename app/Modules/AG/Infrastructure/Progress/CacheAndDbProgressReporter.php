<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Progress;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics;
use App\Modules\AG\Infrastructure\Logging\GATelemetryLogger;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Support\AGError;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class CacheAndDbProgressReporter implements ProgressReporterInterface
{
    public function __construct(
        private CacheProgressReporter $cacheReporter,
        private ExecutionMetricsRecorder $dbRecorder,
        private GATelemetryLogger $telemetryLogger,
        private int $horarioId,
    ) {
    }

    public function report(array $data): void
    {
        if ($this->dbRecorder->hasExecutionId() && ! isset($data['execution_id'])) {
            $data['execution_id'] = $this->dbRecorder->getExecutionId();
        }

        $isEvolvingPhase = isset($data['phase']) && in_array($data['phase'], ['evolving', 'evolution', 'alns_intensification'], true);

        if ($isEvolvingPhase) {
            $knownIncompleteCount = $this->getIncompleteSnapshotCounter();

            if ($knownIncompleteCount > 0) {
                $data['incomplete_generation_snapshot_count'] = $knownIncompleteCount;
            }
        }

        $this->cacheReporter->report($data);
        $this->touchExecutionHeartbeat();

        if (! $isEvolvingPhase || ! isset($data['generation'])) {
            return;
        }

        $missingKeys = $this->missingGenerationSnapshotKeys($data);

        if ($missingKeys !== []) {
            $incompleteCount = $this->incrementIncompleteSnapshotCounter();
            $data['incomplete_generation_snapshot_count'] = $incompleteCount;

            $this->cacheReporter->report($data);

            if ((bool) config('ag.progress.log_incomplete_generation_snapshot', false)) {
                if ($this->shouldLogIncompleteSnapshot($data)) {
                    Log::debug('ga.progress.incomplete_generation_snapshot', [
                        'execution_id' => $this->dbRecorder->hasExecutionId() ? $this->dbRecorder->getExecutionId() : null,
                        'phase' => $data['phase'] ?? null,
                        'stage' => $data['stage'] ?? null,
                        'generation' => (int) $data['generation'],
                        'incomplete_snapshot_count' => $incompleteCount,
                        'missing_keys' => $missingKeys,
                        'available_keys' => array_keys($data),
                    ]);
                }
            }

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
            alnsImprovement: isset($data['alns_improvement']) ? (float) $data['alns_improvement'] : null,
            landscapePhenomenon: $data['landscape_phenomenon'] ?? null,
            landscapeObservation: isset($data['landscape_observation']) && is_array($data['landscape_observation'])
                ? $data['landscape_observation']
                : null,
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
            now()->addMinutes(10),
        );

        $this->telemetryLogger->generationMetrics(
            $this->horarioId,
            $cachePayload + ['phase' => $data['phase'] ?? null, 'max_generations' => $data['max_generations'] ?? 0],
            $this->dbRecorder->getExecutionId(),
        );
    }

    /**
     * Heartbeats operacionais da fase de evolução (ex.: generation_started,
     * evaluating_population, building_offspring) podem chegar sem snapshot
     * completo de métricas. Nesse caso, o payload ainda deve ser cacheado
     * pela camada de progresso, mas não deve virar GenerationMetrics no DB.
     */
    /**
     * @return array<int, string>
     */
    private function missingGenerationSnapshotKeys(array $data): array
    {
        $requiredKeys = [
            'best_fitness',
            'avg_fitness',
            'diversity',
            'entropy',
            'mutation_rate',
            'stagnation',
        ];

        $missingKeys = [];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $data)) {
                $missingKeys[] = $key;
            }
        }

        return $missingKeys;
    }

    /**
     * Throttle: no máximo um log por combinação execution_id + stage + generation
     * dentro da janela configurável.
     */
    private function shouldLogIncompleteSnapshot(array $data): bool
    {
        $executionId = $this->dbRecorder->hasExecutionId()
            ? (string) $this->dbRecorder->getExecutionId()
            : 'unknown';

        $stage = is_string($data['stage'] ?? null) && trim((string) $data['stage']) !== ''
            ? (string) $data['stage']
            : 'unknown';

        $generation = (int) ($data['generation'] ?? -1);
        $cacheKey = "ga_incomplete_snapshot_log_{$executionId}_{$stage}_{$generation}";

        if (Cache::has($cacheKey)) {
            return false;
        }

        $ttlSeconds = max(1, (int) config('ag.progress.incomplete_generation_snapshot_log_ttl_seconds', 300));
        Cache::put($cacheKey, true, now()->addSeconds($ttlSeconds));

        return true;
    }

    private function incrementIncompleteSnapshotCounter(): int
    {
        $executionId = $this->dbRecorder->hasExecutionId()
            ? (string) $this->dbRecorder->getExecutionId()
            : 'unknown';

        $counterKey = "ga_incomplete_snapshot_counter_{$executionId}";
        $nextCount = (int) Cache::increment($counterKey);

        $ttlSeconds = max(60, (int) config('ag.progress.incomplete_generation_snapshot_counter_ttl_seconds', 43200));
        Cache::put($counterKey, $nextCount, now()->addSeconds($ttlSeconds));

        return $nextCount;
    }

    private function getIncompleteSnapshotCounter(): int
    {
        $executionId = $this->dbRecorder->hasExecutionId()
            ? (string) $this->dbRecorder->getExecutionId()
            : 'unknown';

        return (int) Cache::get("ga_incomplete_snapshot_counter_{$executionId}", 0);
    }

    public function reportError(AGError $error): void
    {
        $this->cacheReporter->reportError($error, $this->dbRecorder->hasExecutionId() ? $this->dbRecorder->getExecutionId() : null);
        $this->dbRecorder->flush();
    }

    private function touchExecutionHeartbeat(): void
    {
        if (! $this->dbRecorder->hasExecutionId()) {
            return;
        }

        $cacheKey = "ga_execution_touch_{$this->dbRecorder->getExecutionId()}";

        if (Cache::has($cacheKey)) {
            return;
        }

        $this->dbRecorder->touchExecution();

        Cache::put($cacheKey, true, now()->addSeconds(10));
    }
}
