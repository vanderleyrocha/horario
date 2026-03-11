<?php

namespace App\Jobs;

use App\Models\Horario;
use App\Modules\AG\Infrastructure\Progress\CacheProgressReporter;
use App\Modules\AG\Infrastructure\Progress\CacheAndDbProgressReporter;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
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

    // Timeout expandido para garantir que execuções longas do GA não sejam cortadas pela fila
    public $timeout = 3600;

    public function __construct(public Horario $horario, public array $parametros // Recebe configurações do Livewire (ex: pop_size, geracoes)
    ) {
    }

    public function handle(): void
    {

        ini_set('memory_limit', '-1');
        set_time_limit(0);

        Log::info("Job de geração de horário iniciado [ID: {$this->horario->id}]");

        // 1. Instancia os gravadores individuais
        $cacheReporter = new CacheProgressReporter($this->horario->id);
        $dbRecorder = new ExecutionMetricsRecorder();

        try {
            // 2. Inicia a execução no banco de dados para gerar o execution_id
            $dbRecorder->startExecution($this->horario->id, $this->parametros['configuracao']['populacao'] ?? 120, // Default 120 (Island Model)
                $this->parametros['configuracao']['geracoes'] ?? 500, $this->parametros['configuracao'] ?? []);

            // 3. Cria a ponte unificada
            $progressBridge = new CacheAndDbProgressReporter($cacheReporter, $dbRecorder);

            // 4. Executa a Action injetando a nossa ponte
            $action = app(GenerateScheduleAction::class);
            $result = $action->execute($this->horario, $progressBridge);

            // 5. Finaliza as execuções com sucesso
            $bestFitness = $result['best_fitness'] ?? 0.0;
            $dbRecorder->finishExecution($bestFitness);
            $cacheReporter->reportCompleted($bestFitness);
            Log::info("Job de geração de horário finalizado com sucesso [ID: {$this->horario->id}]");

        } catch (\Throwable $e) {
            // Em caso de quebra (TimeOut, OutOfMemory, etc), forçamos o flush do que já evoluiu
            if (isset($dbRecorder)) {
                $dbRecorder->flush();
            }

            Log::error("Erro na geração de horário [ID: {$this->horario->id}]: " . $e->getMessage());
            throw $e;
        }
    }
}
