<?php
// app/Jobs/GerarHorarioJob.php

namespace App\Jobs;

use App\Models\Horario;
use App\Services\GeneticAlgorithm\HorarioGeneticoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GerarHorarioJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos
    public $tries = 1;

    public Horario $horario;

    public function __construct(Horario $horario) {
        $this->horario = $horario;
        Log::info("__construct GerarHorarioJob");
    }

    public function handle(HorarioGeneticoService $horarioGeneticoService) {
        Log::info("Iniciando geração do horário #{$this->horario->id}");

        try {
            $resultado = $horarioGeneticoService->gerar($this->horario);

            Log::info("Geração do horário #{$this->horario->id} concluída.", $resultado);
            
        } catch (\Exception $e) {
            Log::error("Job falhou para horário #{$this->horario->id}", [
                'exception' => $e->getMessage(),
                'stacktrace' => $e->getTraceAsString(), // Adicionar stacktrace para depuração
            ]);
            // Opcional: Re-lançar a exceção se você quiser que o Job falhe e seja retentado
            // throw $e;
        }
    }


    public function failed(\Throwable $exception): void {
        Log::error("Job falhou para horário #{$this->horario->id}", [
            'exception' => $exception->getMessage(),
        ]);

        $this->horario->update([
            'status' => 'rascunho',
        ]);
    }
}
