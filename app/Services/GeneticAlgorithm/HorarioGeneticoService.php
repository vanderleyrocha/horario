<?php

namespace App\Services\GeneticAlgorithm;

use App\Models\Horario;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessEvaluator;
use App\Services\GeneticAlgorithm\Genetico\HorarioGeneticoOrchestrator;
use App\Services\GeneticAlgorithm\Genetico\Metrics\MetricsRecorder;
use App\Services\GeneticAlgorithm\Genetico\PopulationGenerator;
use App\Services\GeneticAlgorithm\Genetico\Termination\MaxGenerationsOrFitnessCriterion;
use Illuminate\Support\Facades\Log;

class HorarioGeneticoService {
    protected HorarioGeneticoOrchestrator $orchestrator;

    public function __construct(HorarioGeneticoOrchestrator $orchestrator) {
        $this->orchestrator = $orchestrator;
        Log::info("__construct HorarioGeneticoService");
    }

    public function gerar(Horario $horario): array {
        $config = GeneticAlgorithmConfigDTO::fromModels($horario);

        $populationGenerator = new PopulationGenerator($aulas, $config);

        $metrics = new MetricsRecorder();

        $termination = new MaxGenerationsOrFitnessCriterion($config);

        $fitnessEvaluator = new FitnessEvaluator($weights, $rules);
        Log::info("HorarioGeneticoService@gerar");
        return $this->orchestrator->gerar(
            $config,
            $populationGenerator,
            $fitnessEvaluator,
            $termination,
            $metrics,
            $evaluationData
        );
        Log::info("Processamento concluido");
    }
}
