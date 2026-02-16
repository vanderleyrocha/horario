<?php

namespace App\Services\GeneticAlgorithm;

use App\Models\Horario;
use App\Services\GeneticAlgorithm\Genetico\HorarioGeneticoOrchestrator;
use Illuminate\Support\Facades\Log;

class HorarioGeneticoService {
    protected HorarioGeneticoOrchestrator $orchestrator;

    public function __construct(HorarioGeneticoOrchestrator $orchestrator) {
        $this->orchestrator = $orchestrator;
        Log::info("__construct HorarioGeneticoService");
    }

    public function gerar(Horario $horario): array {
        Log::info("HorarioGeneticoService@gerar");
        return $this->orchestrator->gerar($horario);
        Log::info("Processamento concluido");
    }
}
