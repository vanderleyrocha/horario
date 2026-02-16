<?php

namespace App\Services\GeneticAlgorithm;

use App\Models\Alocacao;
use App\Models\Aula;
use App\Models\Horario;
use App\Models\RestricaoTempo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonImmutable;

class HorarioGeneticoService {
    protected HorarioGeneticoOrchestrator $orchestrator;

    private array $aulas = [];
    private array $restricoesIndexadas = [];

    private GeneticAlgorithmConfigDTO $config;
    private Horario $horario;

    public function __construct(HorarioGeneticoOrchestrator $orchestrator) {
        $this->orchestrator = $orchestrator;
        Log::info("__construct HorarioGeneticoService");
    }

    public function gerar(Horario $horario): array {
        $this->horario = $horario;
        $this->apagarAlocacoesHorario($horario->id);

        $this->carregarDados($horario->id);

        $cargaEsperada = $this->buildCargaEsperada();
        $diasPreferidos = $this->buildDiasPreferidos();
        $temposPreferidos = $this->buildTemposPreferidos();

        $evaluationData = [
            'aulas' => $this->aulas,
            'restricoesIndexadas' => $this->restricoesIndexadas,
            'cargaEsperada' => $cargaEsperada,
            'diasPreferidos' => $diasPreferidos,
            'temposPreferidos' => $temposPreferidos,
        ];

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
        $result = $this->orchestrator->gerar($config, $populationGenerator, $fitnessEvaluator, $termination, $metrics, $evaluationData);
        return $this->finalizarGeracao($result['cromossomo'], $result['generation']);
        Log::info("Processamento concluido");

        return [];
    }

    private function apagarAlocacoesHorario(): void {
        Alocacao::where('horario_id', $this->horario->id)->delete();
    }


    private function carregarDados(): void {
        $this->aulas = Aula::where('horario_id', $this->horario->id)->where('ativa', true)->with(['professor', 'disciplina', 'turma'])->get()->all();

        $restricoes = RestricaoTempo::where('horario_id', $this->horario->id)->get()->all();

        foreach ($restricoes as $r) {
            $this->restricoesIndexadas[$r->entidade_type][$r->entidade_id][$r->dia_semana][$r->tempo] = true;
        }
    }

    private function buildCargaEsperada(): array {
        $map = [];
        foreach ($this->aulas as $aula) {
            $map[$aula->id] = $aula->aulas_semana;
        }
        return $map;
    }

    private function buildDiasPreferidos(): array {
        $map = [];
        foreach ($this->aulas as $aula) {
            $map[$aula->id] = $aula->dias_preferidos ?? [];
        }
        return $map;
    }

    private function buildTemposPreferidos(): array {
        $map = [];
        foreach ($this->aulas as $aula) {
            $map[$aula->id] = $aula->tempos_preferidos ?? [];
        }
        return $map;
    }

    private function finalizarGeracao(?Cromossomo $melhor, int $geracoes): array {
        if (!$melhor) {
            throw new \RuntimeException('Nenhum cromossomo encontrado.');
        }

        DB::transaction(function () use ($melhor, $geracoes) {

            foreach ($melhor->getGenes() as $gene) {

                if ($gene->isEmpty()) continue;

                Alocacao::create([
                    'horario_id' => $this->horario->id,
                    'aula_id' => $gene->aulaId,
                    'professor_id' => $gene->professorId,
                    'disciplina_id' => $gene->disciplinaId,
                    'turma_id' => $gene->turmaId,
                    'dia_semana' => $gene->diaSemana,
                    'tempo' => $gene->periodoDia,
                    'horario_inicio' => $this->getHorarioTempo($gene->periodoDia),
                    'horario_fim' => $this->getHorarioTempoFim($gene->periodoDia),
                    'duracao_tempos' => $gene->duracaoTempos,
                ]);
            }

            $this->horario->update([
                'status' => 'concluido',
                'fitness_score' => $melhor->getFitness(),
                'geracoes_executadas' => $geracoes,
            ]);
        });

        Cache::put("horario_geracao_{$this->horario->id}", [
            'status' => 'concluido',
            'geracao_atual' => $geracoes,
            'melhor_fitness' => $melhor->getFitness(),
            'progresso' => 100,
        ], now()->addHours(1));

        return [
            'sucesso' => true,
            'fitness' => $melhor->getFitness(),
            'geracoes' => $geracoes,
            'alocacoes' => $melhor->count(),
        ];
    }

    private function getHorarioTempo(int $tempo): string {
        $inicio = CarbonImmutable::parse($this->config->horarioInicio);

        return $inicio->addMinutes(($tempo - 1) * $this->config->duracaoAulaMinutos)->format('H:i');
    }

    private function getHorarioTempoFim(int $tempo): string {
        return CarbonImmutable::parse($this->getHorarioTempo($tempo))->addMinutes($this->config->duracaoAulaMinutos)->format('H:i');
    }
}
