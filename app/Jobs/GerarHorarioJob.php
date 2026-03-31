<?php

namespace App\Jobs;

use App\Models\Horario;
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
                $this->executionId
            );

            $telemetryLogger->executionStarted(
                $this->horario->id,
                $configuracao,
                $dbRecorder->getExecutionId()
            );

            $progressBridge = new CacheAndDbProgressReporter(
                $cacheReporter,
                $dbRecorder,
                $telemetryLogger,
                $this->horario->id
            );

            $action = app(GenerateScheduleAction::class);
            $result = $action->execute(
                $this->horario,
                $dbRecorder->getExecutionId(),
                $progressBridge,
                $dbRecorder
            );

            $bestFitness = (float) ($result['best_fitness'] ?? 0.0);
            $statusContext = $this->buildExecutionStatusContext(
                status: 'finished',
                executionId: $dbRecorder->getExecutionId(),
                configuracao: $configuracao,
                bestFitness: $bestFitness
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
                $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId
            );

            Log::info("Job de geracao de horario finalizado com sucesso [ID: {$this->horario->id}]");
        } catch (ExecutionCancelledException $e) {
            $statusContext = $this->buildExecutionStatusContext(
                status: 'cancelled',
                executionId: $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
                configuracao: $configuracao,
                exception: $e
            );

            $dbRecorder->cancelExecution($statusContext);
            $cacheReporter->reportCancelled(
                $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
                $statusContext
            );

            Log::warning("Execucao cancelada [ID: {$this->horario->id}]");
        } catch (\Throwable $e) {
            $statusContext = $this->buildExecutionStatusContext(
                status: 'failed',
                executionId: $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
                configuracao: $configuracao,
                exception: $e
            );

            $dbRecorder->failExecution($statusContext);
            $cacheReporter->reportFailed(
                $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId,
                $statusContext
            );

            $telemetryLogger->executionFailed(
                $this->horario->id,
                $e,
                ['configuracao' => $configuracao, 'status_context' => $statusContext],
                $dbRecorder->hasExecutionId() ? $dbRecorder->getExecutionId() : $this->executionId
            );

            Log::error("Erro na geracao de horario [ID: {$this->horario->id}]: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $configuracao
     * @return array<string, mixed>
     */
    private function buildExecutionStatusContext(
        string $status,
        ?int $executionId,
        array $configuracao,
        ?Throwable $exception = null,
        ?float $bestFitness = null
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
            'finished' => 'Execucao concluida',
            'cancelled' => 'Execucao interrompida por cancelamento',
            'failed' => 'Execucao interrompida por falha',
            default => 'Execucao interrompida',
        };

        $reason = match (true) {
            $status === 'finished' => 'O solver concluiu o processamento e encerrou a execucao normalmente.',
            $phase === 'initial_population' && str_contains($stage, 'quality_gate_fail_fast') => 'A populacao inicial acumulou conflitos hard demais antes de chegar a um candidato apto para o repair.',
            $phase === 'initial_population' && str_contains($stage, 'quality_gate_reject') => 'O quality gate rejeitou as melhores tentativas porque os limites operacionais continuaram fora da faixa aceitavel ou a semente permaneceu inviavel para a etapa seguinte.',
            $phase === 'initial_population' => 'A construcao da populacao inicial nao conseguiu formar um individuo valido dentro da janela de tentativas configurada.',
            $exception !== null => $exception->getMessage(),
            default => 'A execucao foi interrompida antes da etapa de evolucao gerar metricas consolidadas.',
        };

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
            'suggestions' => $suggestions,
            'best_fitness' => $bestFitness,
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
     * @param  array<string, mixed>  $bottlenecks
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

    private function buildUserFacingMessage(string $status, string $phase, string $stage, ?int $attempt): string
    {
        return match (true) {
            $status === 'finished' => 'O solver terminou a execucao e salvou o melhor estado disponivel.',
            $status === 'cancelled' => 'A execucao foi interrompida e o solver encerrou o processamento de forma segura.',
            $phase === 'initial_population' && $attempt !== null => sprintf(
                'A execucao foi interrompida durante a populacao inicial, na tentativa %d, sem conseguir formar um individuo inicial confiavel.',
                $attempt
            ),
            $phase === 'initial_population' => 'A execucao foi interrompida durante a populacao inicial, antes de qualquer geracao ser registrada.',
            default => sprintf(
                'A execucao foi interrompida na etapa %s.',
                str_replace('_', ' ', $stage !== '' ? $stage : 'desconhecida')
            ),
        };
    }
}
