<?php

namespace App\Jobs;

use App\Models\Horario;
use App\Services\GeneticAlgorithm\HorarioGeneticoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GerarHorarioJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    public function __construct(public Horario $horario) {
    }

    public function handle(HorarioGeneticoService $service): void {
        try {
            Log::info("Iniciando Job de geração de horário", [
                'horario_id' => $this->horario->id,
                'horario_nome' => $this->horario->nome,
            ]);
            $resultado = $service->gerar($this->horario);

            if (!$resultado['sucesso']) {

                Cache::put("horario_geracao_{$this->horario->id}", [
                    'status' => 'erro',
                    'erro' => $resultado['erro'],
                ], now()->addMinutes(30));

                return;
            }

            Cache::put("horario_geracao_{$this->horario->id}", [
                'status' => 'concluido',
                'mensagem_status' => 'Horário gerado com sucesso.'
            ], now()->addMinutes(30));
        } catch (\Throwable $e) {

            Log::error("Erro crítico no Job", [
                'exception' => $e->getMessage()
            ]);

            Cache::put("horario_geracao_{$this->horario->id}", [
                'status' => 'erro',
                'erro' => [
                    'codigo' => 'AG-500',
                    'categoria' => 'ERRO_SISTEMA',
                    'mensagem' => 'Erro crítico durante execução do Job.',
                    'severidade' => 'CRITICA',
                    'dados' => ['exception' => $e->getMessage()],
                ]
            ], now()->addMinutes(30));
        }
    }
}
