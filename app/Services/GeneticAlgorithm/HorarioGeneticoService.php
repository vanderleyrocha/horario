<?php

namespace App\Services\GeneticAlgorithm;

use App\Models\Alocacao;
use App\Models\Aula;
use App\Models\Horario;
use App\Models\RestricaoTempo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessEvaluator;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessWeights;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard\BloqueiosHardRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard\CargaHorariaExcedidaRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard\ConflitoProfessorRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard\ConflitoTurmaRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft\AulasNaoConsecutivasRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft\BloqueiosPreferenciaisRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft\DistribuicaoRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft\JanelasRule;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft\MaxAulasDiaRule;
use App\Services\GeneticAlgorithm\Genetico\HorarioGeneticoOrchestrator;
use App\Services\GeneticAlgorithm\Genetico\Metrics\MetricsRecorder;
use App\Services\GeneticAlgorithm\Genetico\PopulationGenerator;
use App\Services\GeneticAlgorithm\Genetico\Termination\MaxGenerationsOrFitnessCriterion;
use Illuminate\Support\Facades\Log;

class HorarioGeneticoService {
    protected HorarioGeneticoOrchestrator $orchestrator;

    private array $aulas = [];
    private array $restricoesIndexadas = [];


    public function __construct(HorarioGeneticoOrchestrator $orchestrator) {
        $this->orchestrator = $orchestrator;
        Log::info("__construct HorarioGeneticoService");
    }

    public function gerar(Horario $horario): array {

        $this->apagarAlocacoesHorario($horario->id);

        $this->carregarDados($horario->id);


        $config = GeneticAlgorithmConfigDTO::fromModels($horario);

        $populationGenerator = new PopulationGenerator($this->aulas, $config);

        $metrics = new MetricsRecorder();

        $weights = new FitnessWeights([
            ConflitoProfessorRule::class => 100.0,
            ConflitoTurmaRule::class => 100.0,
            CargaHorariaExcedidaRule::class => 50.0,
            BloqueiosHardRule::class => 100.0,
            JanelasRule::class => 0.6,
            MaxAulasDiaRule::class => 0.9,
            DistribuicaoRule::class => 0.7,
            AulasNaoConsecutivasRule::class => 0.8,
            BloqueiosPreferenciaisRule::class => 0.5,
        ]);

        $rules = [
            new ConflitoProfessorRule(),
            new ConflitoTurmaRule(),
            new CargaHorariaExcedidaRule(),
            new BloqueiosHardRule(),
            new JanelasRule(),
            new MaxAulasDiaRule(),
            new DistribuicaoRule(),
            new AulasNaoConsecutivasRule(),
            new BloqueiosPreferenciaisRule(),
        ];

        $fitnessEvaluator = new FitnessEvaluator($weights, $rules);

        $termination = new MaxGenerationsOrFitnessCriterion(
            maxGenerations: $config->numeroGeracoes,
            targetFitness: $config->targetFitness,
            maxGenerationsWithoutImprovement: $config->maxGenerationsWithoutImprovement
        );


        Log::info("HorarioGeneticoService@gerar");
        $cromossomo = $this->orchestrator->gerar(
            $config,
            $populationGenerator,
            $fitnessEvaluator,
            $termination,
            $metrics,
            $evaluationData
        );
        Log::info("Processamento concluido");

        return [];
    }

    private function apagarAlocacoesHorario($horario_id): void {
        Alocacao::where('horario_id', $horario_id)->delete();
    }


    private function carregarDados($horario_id): void {
        $this->aulas = Aula::where('horario_id', $horario_id)->where('ativa', true)->with(['professor', 'disciplina', 'turma'])->get()->all();

        $restricoes = RestricaoTempo::where('horario_id', $horario_id)->get()->all();

        foreach ($restricoes as $r) {
            $this->restricoesIndexadas[$r->entidade_type][$r->entidade_id][$r->dia_semana][$r->tempo] = true;
        }
    }
}
