<?php

namespace App\Jobs;

use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use App\Modules\AG\Infrastructure\Logging\GATelemetryLogger;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Infrastructure\Progress\CacheAndDbProgressReporter;
use App\Modules\AG\Infrastructure\Progress\CacheProgressReporter;
use App\Modules\AG\Support\Exceptions\ExecutionCancelledException;
use App\Modules\Horarios\Application\GenerateScheduleAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GerarHorarioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3600;

    public function __construct(public Horario $horario, public ?int $executionId = null)
    {
    }

    public function handle(): void
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        Log::info("Job de geracao de horario iniciado [ID: {$this->horario->id}]");

        $configuracao = $this->horario->configuracao ?? [];
        $telemetryLogger = app(GATelemetryLogger::class);

        $cacheReporter = new CacheProgressReporter($this->horario->id);
        $dbRecorder = new ExecutionMetricsRecorder();

        try {
            $dbRecorder->startExecution(
                $this->horario->id,
                (int) ($configuracao['populacao'] ?? 100),
                (int) ($configuracao['geracoes'] ?? 500),
                $configuracao,
                $this->executionId,
            );

            $telemetryLogger->executionStarted(
                $this->horario->id,
                $configuracao,
                $dbRecorder->getExecutionId(),
            );

            $progressBridge = new CacheAndDbProgressReporter(
                $cacheReporter,
                $dbRecorder,
                $telemetryLogger,
                $this->horario->id,
            );

            $action = app(GenerateScheduleAction::class);
            $result = $action->execute(
                $this->horario,
                $dbRecorder->getExecutionId(),
                $progressBridge,
                $dbRecorder,
            );

            $bestFitness = (float) ($result['best_fitness'] ?? 0.0);
            $viable = (bool) ($result['viable'] ?? true);
            $statusContext = $this->buildExecutionStatusContext(
                status: 'finished',
                executionId: $dbRecorder->getExecutionId(),
                configuracao: $configuracao,
                bestFitness: $bestFitness,
                viable: $viable,
            );

            $dbRecorder->finishExecution($bestFitness, $statusContext);
            $cacheReporter->reportCompleted($bestFitness, $dbRecorder->getExecutionId(), $statusContext);

            $telemetryLogger->executionCompleted(
                $this->horario->id,
                $bestFitness,
                [
                    'generations_configured' => (int) ($configuracao['geracoes'] ?? 500),
                    'population_configured' => (int) ($configuracao['populacao'] ?? 100),
                ],
                $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
            );

            Log::info("Job de geracao de horario finalizado com sucesso [ID: {$this->horario->id}]");
        } catch (ExecutionCancelledException $e) {
            $statusContext = $this->buildExecutionStatusContext(
                status: 'cancelled',
                executionId: $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
                configuracao: $configuracao,
                exception: $e,
            );

            $dbRecorder->cancelExecution($statusContext);
            $cacheReporter->reportCancelled(
                $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
                $statusContext,
            );

            Log::warning("Execucao cancelada [ID: {$this->horario->id}]");
        } catch (Throwable $e) {
            $statusContext = $this->buildExecutionStatusContext(
                status: 'failed',
                executionId: $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
                configuracao: $configuracao,
                exception: $e,
            );

            $dbRecorder->failExecution($statusContext);
            $cacheReporter->reportFailed(
                $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
                $statusContext,
            );

            $telemetryLogger->executionFailed(
                $this->horario->id,
                $e,
                ['configuracao' => $configuracao, 'status_context' => $statusContext],
                $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
            );

            Log::error("Erro na geracao de horario [ID: {$this->horario->id}]: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $configuracao
     * @return array<string, mixed>
     */
    private function buildExecutionStatusContext(
        string $status,
        ?int $executionId,
        array $configuracao,
        ?Throwable $exception = null,
        ?float $bestFitness = null,
        bool $viable = true,
    ): array {
        $progress = $this->resolveProgressSnapshot($executionId);
        $bottlenecks = is_array($progress['initial_population_bottlenecks'] ?? null)
            ? $progress['initial_population_bottlenecks']
            : [];

        $phase = (string) ($progress['phase'] ?? '');
        $stage = (string) ($progress['stage'] ?? '');
        $attempt = isset($progress['attempt']) ? (int) $progress['attempt'] : null;
        $queueSize = isset($progress['queue_size']) ? (int) $progress['queue_size'] : null;
        $allocations = isset($progress['allocations']) ? (int) $progress['allocations'] : null;
        $forcedAllocations = isset($progress['forced_allocations']) ? (int) $progress['forced_allocations'] : null;
        $hardConflicts = isset($progress['hard_conflict_allocations']) ? (int) $progress['hard_conflict_allocations'] : null;
        $hardPenalty = isset($progress['hard_penalty']) ? (float) $progress['hard_penalty'] : null;
        $repairSummary = is_array($progress['repair_summary'] ?? null) ? $progress['repair_summary'] : null;

        $title = match ($status) {
            'finished' => $viable ? 'Execucao concluida' : 'Execucao concluida (resultado parcial)',
            'cancelled' => 'Execucao interrompida por cancelamento',
            'failed' => 'Execucao interrompida por falha',
            default => 'Execucao interrompida',
        };

        $reason = match (true) {
            $status === 'finished' && ! $viable => 'O solver concluiu todas as geracoes, mas o melhor individuo ainda possui violacoes de constraints hard que o reparo final nao conseguiu resolver. O horario foi persistido como resultado parcial para evitar perda total do trabalho evolutivo.',
            $status === 'finished' => 'O solver concluiu o processamento e encerrou a execucao normalmente.',
            $phase === 'initial_population' && str_contains($stage, 'quality_gate_fail_fast') => 'A populacao inicial acumulou conflitos hard demais antes de chegar a um candidato apto para o repair.',
            $phase === 'initial_population' && str_contains($stage, 'quality_gate_reject') => 'O quality gate rejeitou as melhores tentativas porque os limites operacionais continuaram fora da faixa aceitavel ou a semente permaneceu inviavel para a etapa seguinte.',
            $phase === 'initial_population' => 'A construcao da populacao inicial nao conseguiu formar um individuo valido dentro da janela de tentativas configurada.',
            $exception !== null => $exception->getMessage(),
            default => 'A execucao foi interrompida antes da etapa de evolucao gerar metricas consolidadas.',
        };

        $postExecutionReport = $this->buildPostExecutionReport(
            status: $status,
            executionId: $executionId,
            configuracao: $configuracao,
            progress: $progress,
            bottlenecks: $bottlenecks,
            repairSummary: $repairSummary,
        );

        $suggestions = $this->buildExecutionSuggestions($status, $bottlenecks, $phase, $stage);

        return [
            'execution_id' => $executionId,
            'execution_status' => $status,
            'title' => $title,
            'phase' => $phase !== '' ? $phase : 'terminal',
            'stage' => $stage !== '' ? $stage : 'finalizado',
            'reason' => $reason,
            'user_message' => $this->buildUserFacingMessage($status, $phase, $stage, $attempt),
            'exception_class' => $exception !== null ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
            'attempt' => $attempt,
            'queue_size' => $queueSize,
            'allocations' => $allocations,
            'forced_allocations' => $forcedAllocations,
            'hard_conflict_allocations' => $hardConflicts,
            'hard_penalty' => $hardPenalty,
            'repair_summary' => $repairSummary,
            'initial_population_bottlenecks' => $bottlenecks,
            'post_execution_report' => $postExecutionReport,
            'phase_timings_ms' => $postExecutionReport['timings_ms'],
            'dominant_bottleneck' => $postExecutionReport['dominant_bottleneck'],
            'operational_counters' => $postExecutionReport['operational_counters'],
            'suggestions' => $suggestions,
            'best_fitness' => $bestFitness,
                        'partial_solution' => ! $viable,
            'config_summary' => [
                'population_size' => (int) ($configuracao['populacao'] ?? 0),
                'generations' => (int) ($configuracao['geracoes'] ?? 0),
            ],
            'last_progress_timestamp' => $progress['timestamp'] ?? now()->toDateTimeString(),
            'captured_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProgressSnapshot(?int $executionId): array
    {
        if ($executionId !== null) {
            $executionScoped = Cache::get("ga_execution_progress_{$executionId}");

            if (is_array($executionScoped)) {
                return $executionScoped;
            }
        }

        $progress = Cache::get("horario_geracao_{$this->horario->id}");

        return is_array($progress) ? $progress : [];
    }

    /**
     * @param array<string, mixed> $bottlenecks
     * @return list<string>
     */
    private function buildExecutionSuggestions(string $status, array $bottlenecks, string $phase, string $stage): array
    {
        $suggestions = [];

        if ($status === 'cancelled') {
            $suggestions[] = 'Reinicie a execucao com menos ilhas ou populacao menor se o objetivo era apenas validar a viabilidade rapidamente.';
        }

        if ($phase === 'initial_population') {
            $suggestions[] = 'Priorize melhorar a construcao inicial antes de aumentar geracoes, porque o solver nem chegou na fase de evolucao.';
            $suggestions[] = 'Considere reduzir o limite de reprocessamento completo e introduzir seeds parciais viaveis com repair incremental.';
        }

        foreach (($bottlenecks['optimization_suggestions'] ?? []) as $suggestion) {
            if (is_string($suggestion) && trim($suggestion) !== '') {
                $suggestions[] = $suggestion;
            }
        }

        $suggestions[] = 'Abra os logs desta execucao para ver a sequencia completa de tentativas e confirmar se houve falha logica, timeout ou encerramento do worker.';

        return array_values(array_unique($suggestions));
    }

    /**
     * @param array<string, mixed> $configuracao
     * @param array<string, mixed> $progress
     * @param array<string, mixed> $bottlenecks
     * @param array<string, mixed>|null $repairSummary
     * @return array<string, mixed>
     */
    private function buildPostExecutionReport(
        string $status,
        ?int $executionId,
        array $configuracao,
        array $progress,
        array $bottlenecks,
        ?array $repairSummary,
    ): array {
        $capturedAt = now();
        $metricsSummary = $this->resolveExecutionMetricsSummary($executionId);

        $executionStartAt = null;

        if ($executionId !== null) {
            $executionStartAt = ScheduleExecution::query()
                ->whereKey($executionId)
                ->value('start_time');
        }

        $lastProgressAt = $this->parseTimestampToMillis($progress['timestamp'] ?? null);
        $capturedAtMs = (int) round($capturedAt->getPreciseTimestamp(3));
        $executionStartAtMs = $this->parseTimestampToMillis($executionStartAt);

        $initialPopulationMs = null;

        if (is_numeric($bottlenecks['attempts_recorded'] ?? null) && is_numeric($bottlenecks['avg_attempt_ms'] ?? null)) {
            $initialPopulationMs = max(0, (int) $bottlenecks['attempts_recorded']) * max(0, (int) $bottlenecks['avg_attempt_ms']);
        }

        $evolutionMs = $metricsSummary['window_ms'];
        $persistMs = null;

        if ($lastProgressAt !== null) {
            $persistMs = max(0, $capturedAtMs - $lastProgressAt);
        }

        $repairMs = null;

        if (is_numeric($progress['repair_elapsed_ms'] ?? null)) {
            $repairMs = max(0, (int) $progress['repair_elapsed_ms']);
        }

        $totalMs = null;

        if ($executionStartAtMs !== null) {
            $totalMs = max(0, $capturedAtMs - $executionStartAtMs);
        }

        $migrationInterval = max(1, (int) config('ag.migration_interval', 5));
        $generationsRecorded = max(0, (int) ($metricsSummary['generation_count'] ?? 0));
        $maxGeneration = max(0, (int) ($metricsSummary['max_generation'] ?? 0));
        $estimatedMigrationRounds = $maxGeneration > 0
            ? (int) floor($maxGeneration / $migrationInterval)
            : 0;

        $operationalCounters = [
            'quality_gate_rejections' => max(0, (int) ($bottlenecks['quality_gate_rejections'] ?? 0)),
            'quality_gate_fail_fast' => max(0, (int) ($bottlenecks['fail_fast_count'] ?? 0)),
            'repair_passes' => max(0, (int) ($repairSummary['pass_count'] ?? 0)),
            'alns_activations' => max(0, (int) ($metricsSummary['alns_activation_count'] ?? 0)),
            'migration_rounds' => $estimatedMigrationRounds,
            'generations_recorded' => $generationsRecorded,
        ];

        $dominantBottleneck = $this->resolveDominantBottleneck($status, $operationalCounters);

        return [
            'schema_version' => 1,
            'status' => $status,
            'timings_ms' => [
                'total' => $totalMs,
                'initial_population' => $initialPopulationMs,
                'evolution' => $evolutionMs,
                'alns' => null,
                'repair' => $repairMs,
                'persist' => $persistMs,
            ],
            'timing_sources' => [
                'total' => $totalMs !== null ? 'schedule_executions.start_time' : 'unavailable',
                'initial_population' => $initialPopulationMs !== null ? 'initial_population_bottlenecks' : 'unavailable',
                'evolution' => $evolutionMs !== null ? 'schedule_generation_metrics.created_at_window' : 'unavailable',
                'alns' => 'unavailable',
                'repair' => $repairMs !== null ? 'progress.repair_elapsed_ms' : 'unavailable',
                'persist' => $persistMs !== null ? 'progress.timestamp_vs_capture' : 'unavailable',
            ],
            'operational_counters' => $operationalCounters,
            'dominant_bottleneck' => $dominantBottleneck,
            'execution_window' => [
                'execution_started_at' => $executionStartAt,
                'last_progress_at' => $progress['timestamp'] ?? null,
                'captured_at' => $capturedAt->toDateTimeString(),
            ],
            'run_profile' => [
                'population_size' => (int) ($configuracao['populacao'] ?? 0),
                'generations_configured' => (int) ($configuracao['geracoes'] ?? 0),
                'migration_interval' => $migrationInterval,
            ],
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function resolveExecutionMetricsSummary(?int $executionId): array
    {
        if ($executionId === null) {
            return [
                'generation_count' => 0,
                'max_generation' => 0,
                'alns_activation_count' => 0,
                'window_ms' => null,
            ];
        }

        $query = ScheduleGenerationMetric::query()->where('execution_id', $executionId);

        $generationCount = (clone $query)->count();
        $maxGeneration = (int) ((clone $query)->max('generation') ?? 0);
        $firstMetricAt = (clone $query)->min('created_at');
        $lastMetricAt = (clone $query)->max('created_at');
        $alnsActivationCount = (clone $query)
            ->where(static function ($builder): void {
                $builder->whereNotNull('alns_destroy_operator')
                    ->orWhereNotNull('alns_repair_operator')
                    ->orWhere(static function ($innerBuilder): void {
                        $innerBuilder->whereNotNull('alns_improvement')
                            ->where('alns_improvement', '!=', 0);
                    });
            })
            ->count();

        $windowMs = null;
        $firstMetricAtMs = $this->parseTimestampToMillis($firstMetricAt);
        $lastMetricAtMs = $this->parseTimestampToMillis($lastMetricAt);

        if ($firstMetricAtMs !== null && $lastMetricAtMs !== null) {
            $windowMs = max(0, $lastMetricAtMs - $firstMetricAtMs);
        }

        return [
            'generation_count' => $generationCount,
            'max_generation' => $maxGeneration,
            'alns_activation_count' => $alnsActivationCount,
            'window_ms' => $windowMs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDominantBottleneck(string $status, array $operationalCounters): array
    {
        $qualityGateRejections = max(0, (int) ($operationalCounters['quality_gate_rejections'] ?? 0));
        $failFastCount = max(0, (int) ($operationalCounters['quality_gate_fail_fast'] ?? 0));
        $repairPasses = max(0, (int) ($operationalCounters['repair_passes'] ?? 0));
        $generationsRecorded = max(0, (int) ($operationalCounters['generations_recorded'] ?? 0));

        if ($failFastCount > 0) {
            return [
                'key' => 'initial_population_fail_fast_pressure',
                'summary' => 'Conflitos hard estouraram o fail-fast na populacao inicial.',
                'confidence' => 'high',
                'evidence' => [
                    'quality_gate_fail_fast' => $failFastCount,
                ],
            ];
        }

        if ($qualityGateRejections > 0) {
            return [
                'key' => 'initial_population_quality_gate_rejections',
                'summary' => 'Quality gate rejeitou repetidamente candidatos iniciais.',
                'confidence' => $qualityGateRejections >= 3 ? 'high' : 'medium',
                'evidence' => [
                    'quality_gate_rejections' => $qualityGateRejections,
                ],
            ];
        }

        if ($repairPasses >= 3) {
            return [
                'key' => 'repair_pressure',
                'summary' => 'A execucao exigiu varios passes de repair para estabilizar candidatos.',
                'confidence' => 'medium',
                'evidence' => [
                    'repair_passes' => $repairPasses,
                ],
            ];
        }

        if ($status !== 'finished' && $generationsRecorded === 0) {
            return [
                'key' => 'no_evolution_progress',
                'summary' => 'A execucao encerrou antes de registrar progresso de evolucao.',
                'confidence' => 'medium',
                'evidence' => [
                    'generations_recorded' => $generationsRecorded,
                ],
            ];
        }

        return [
            'key' => 'none',
            'summary' => 'Sem gargalo operacional dominante identificado nesta execucao.',
            'confidence' => 'low',
            'evidence' => [],
        ];
    }

    private function parseTimestampToMillis(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return (int) round(((float) $value->format('U.u')) * 1000);
        }

        if (is_numeric($value)) {
            $raw = (float) $value;

            // microtime(true) retorna segundos com fração.
            return (int) round($raw > 1000000000000 ? $raw : $raw * 1000);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return (int) round(((float) Carbon::parse($value)->format('U.u')) * 1000);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function buildUserFacingMessage(string $status, string $phase, string $stage, ?int $attempt): string
    {
        return match (true) {
            $status === 'finished' => 'O solver terminou a execucao e salvou o melhor estado disponivel.',
            $status === 'cancelled' => 'A execucao foi interrompida e o solver encerrou o processamento de forma segura.',
            $phase === 'initial_population' && $attempt !== null => sprintf(
                'A execucao foi interrompida durante a populacao inicial, na tentativa %d, sem conseguir formar um individuo inicial confiavel.',
                $attempt,
            ),
            $phase === 'initial_population' => 'A execucao foi interrompida durante a populacao inicial, antes de qualquer geracao ser registrada.',
            default => sprintf(
                'A execucao foi interrompida na etapa %s.',
                str_replace('_', ' ', $stage !== '' ? $stage : 'desconhecida'),
            ),
        };
    }
}
