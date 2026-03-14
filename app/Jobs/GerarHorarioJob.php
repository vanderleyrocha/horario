<?php

namespace App\Jobs;

use App\Models\Horario;
use App\Modules\AG\Infrastructure\Logging\GATelemetryLogger;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Infrastructure\Progress\CacheAndDbProgressReporter;
use App\Modules\AG\Infrastructure\Progress\CacheProgressReporter;
use App\Modules\Horarios\Application\GenerateScheduleAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GerarHorarioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3600;

    public function __construct(public Horario $horario)
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
                $configuracao
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
                $progressBridge
            );

            $bestFitness = (float) ($result['best_fitness'] ?? 0.0);

            $dbRecorder->finishExecution($bestFitness);
            $cacheReporter->reportCompleted($bestFitness);

            $telemetryLogger->executionCompleted(
                $this->horario->id,
                $bestFitness,
                [
                    'generations_configured' => (int) ($configuracao['geracoes'] ?? 500),
                    'population_configured' => (int) ($configuracao['populacao'] ?? 100),
                ],
                $dbRecorder->getExecutionId()
            );

            Log::info("Job de geracao de horario finalizado com sucesso [ID: {$this->horario->id}]");
        } catch (\Throwable $e) {
            $dbRecorder->flush();

            $telemetryLogger->executionFailed(
                $this->horario->id,
                $e,
                ['configuracao' => $configuracao],
                $dbRecorder->getExecutionId()
            );

            Log::error("Erro na geracao de horario [ID: {$this->horario->id}]: " . $e->getMessage());
            throw $e;
        }
    }
}
