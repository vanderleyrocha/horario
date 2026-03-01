<?php

namespace App\Services\GeneticAlgorithm;

use App\Models\Alocacao;
use App\Models\Aula;
use App\Models\ExecucaoAlgoritmo;
use App\Models\Horario;
use App\Models\RestricaoTempo;
use App\Services\GeneticAlgorithm\Exceptions\InviableScheduleException;
use App\Services\GeneticAlgorithm\Support\AGErrorFactory;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class HorarioGeneticoService {
    protected HorarioGeneticoOrchestrator $orchestrator;

    private ?GeneticAlgorithmConfigDTO $config = null;
    private ?Horario $horario = null;
    private ?ExecucaoAlgoritmo $execucao = null;

    private array $aulas = [];
    private array $restricoesIndexadas = [];

    private float $iniTime = 0;

    public function __construct(HorarioGeneticoOrchestrator $orchestrator) {
        $this->orchestrator = $orchestrator;
    }

    public function gerar(Horario $horario): array {
        $this->iniTime = microtime(true);
        $this->horario = $horario;

        try {

            // 1️⃣ Config
            $this->config = GeneticAlgorithmConfigDTO::fromModels($horario);

            // 2️⃣ Execução
            $this->criarExecucao();

            // 3️⃣ Dataset
            $this->carregarDados();

            if (empty($this->aulas)) {
                throw new InviableScheduleException(
                    AGErrorFactory::populationFailure(['motivo' => 'nenhuma_aula_ativa'])
                );
            }

            $evaluationData = $this->buildEvaluationData();

            // 4️⃣ Componentes AG
            $populationGenerator = new PopulationGenerator($this->aulas, $this->config, fn($fase, $atual, $total) => $this->atualizarProgresso($fase, $atual, $total));

            $fitnessEvaluator = $this->buildFitnessEvaluator();

            $metrics = new MetricsRecorder();

            $termination = new MaxGenerationsOrFitnessCriterion(
                maxGenerations: $this->config->numeroGeracoes,
                targetFitness: $this->config->targetFitness,
                maxGenerationsWithoutImprovement: $this->config->maxGenerationsWithoutImprovement
            );

            // 5️⃣ Execução AG
            $result = $this->orchestrator->gerar(
                $this->config,
                $populationGenerator,
                $fitnessEvaluator,
                $termination,
                $metrics,
                $evaluationData,
                fn($fase, $atual, $total) =>
                $this->atualizarProgresso($fase, $atual, $total)
            );

            if (!$result || empty($result['cromossomo'])) {
                throw new InviableScheduleException(
                    AGErrorFactory::noSolution($result['generation'] ?? 0)
                );
            }

            return $this->finalizarGeracao($result['cromossomo'], $result['generation']);
        } catch (InviableScheduleException $e) {

            $this->marcarExecucaoComoFalha($e->getMessage());

            return [
                'sucesso' => false,
                'erro' => $e->getError()->toArray(),
            ];
        } catch (\Throwable $e) {

            $this->marcarExecucaoComoFalha($e->getMessage());

            return [
                'sucesso' => false,
                'erro' => AGErrorFactory::systemError($e->getMessage())->toArray(),
            ];
        }
    }

    private function criarExecucao(): void {
        $this->execucao = ExecucaoAlgoritmo::create([
            'horario_id' => $this->horario->id,
            'parametros_utilizados' => [
                'elitism' => $this->config->elitismCount,
                'target_fitness' => $this->config->targetFitness,
                'max_generations_without_improvement' => $this->config->maxGenerationsWithoutImprovement,
            ],
            'status' => 'em_execucao',
        ]);
    }

    private function carregarDados(): void {
        $this->aulas = Aula::where('horario_id', $this->horario->id)->where('ativa', true)->with(['professor', 'disciplina', 'turma'])->get()->all();

        $restricoes = RestricaoTempo::where('horario_id', $this->horario->id)->get();

        foreach ($restricoes as $r) {
            $this->restricoesIndexadas[$r->entidade_type][$r->entidade_id][$r->dia_semana][$r->tempo] = true;
        }
    }

    private function atualizarProgresso(string $fase, int $atual, int $total): void {
        $percentual = min(100, round(($atual / $total) * 100));

        Cache::put("horario_geracao_{$this->horario->id}", [
            'status' => 'executando',
            'fase' => $fase,
            'percentual' => $percentual,
            'mensagem' => $fase === 'populacao'
                ? "Gerando população inicial ({$percentual}%)"
                : "Evoluindo gerações ({$percentual}%)"
        ]);
    }

    private function buildEvaluationData(): array {
        return [
            'aulas' => $this->aulas,
            'restricoesIndexadas' => $this->restricoesIndexadas,
            'cargaEsperada' => $this->buildCargaEsperada(),
            'diasPreferidos' => $this->buildDiasPreferidos(),
            'temposPreferidos' => $this->buildTemposPreferidos(),
        ];
    }

    private function buildFitnessEvaluator(): FitnessEvaluator {
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

        return new FitnessEvaluator($weights, $rules);
    }

    private function finalizarGeracao(Cromossomo $melhor, int $geracoes): array {
        DB::transaction(function () use ($melhor, $geracoes) {

            foreach ($melhor->getGenes() as $gene) {

                if ($gene->isEmpty()) continue;

                Alocacao::create([
                    'execucao_algoritmo_id' => $this->execucao->id,
                    'aula_id' => $gene->getAulaId(),
                    'professor_id' => $gene->getProfessorId(),
                    'disciplina_id' => $gene->getDisciplinaId(),
                    'turma_id' => $gene->getTurmaId(),
                    'dia_semana' => $gene->getDiaSemana(),
                    'tempo' => $gene->getPeriodoDia(),
                    'horario_inicio' => $this->getHorarioTempo($gene->getPeriodoDia()),
                    'horario_fim' => $this->getHorarioTempoFim($gene->getPeriodoDia()),
                    'duracao_tempos' => $gene->getDuracaoTempos(),
                ]);
            }

            $this->horario->execucoes()->update(['ativa' => false]);

            $this->execucao->update([
                'fitness_score' => $melhor->getFitness(),
                'geracoes_executadas' => $geracoes,
                'tempo_execucao_ms' => (microtime(true) - $this->iniTime) * 1000,
                'status' => 'concluida',
                'ativa' => true,
            ]);

            $this->horario->update(['status' => 'concluido']);
        });

        return [
            'sucesso' => true,
            'fitness' => $melhor->getFitness(),
            'geracoes' => $geracoes,
            'alocacoes' => $melhor->count(),
        ];
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

    private function getHorarioTempo(int $tempo): string {
        $inicio = CarbonImmutable::parse($this->config->horarioInicio);

        return $inicio->addMinutes(($tempo - 1) * $this->config->duracaoAulaMinutos)->format('H:i');
    }

    private function getHorarioTempoFim(int $tempo): string {
        return CarbonImmutable::parse($this->getHorarioTempo($tempo))->addMinutes($this->config->duracaoAulaMinutos)->format('H:i');
    }

    private function marcarExecucaoComoFalha(string $erro): void {
        if ($this->execucao) {
            $this->execucao->update(['status' => 'falhou']);
        }

        Log::error("Falha AG", [
            'horario_id' => $this->horario?->id,
            'erro' => $erro,
        ]);
    }
}
