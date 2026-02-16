<?php

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Models\Horario;
use App\Models\Aula;
use App\Models\Alocacao;
use App\Models\RestricaoTempo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessEvaluator;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessWeights;
use App\Services\GeneticAlgorithm\Genetico\Metrics\MetricsRecorder;
use App\Services\GeneticAlgorithm\Genetico\PopulationGenerator;
use App\Services\GeneticAlgorithm\Genetico\Termination\MaxGenerationsOrFitnessCriterion;

use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Hard\{
    ConflitoProfessorRule,
    ConflitoTurmaRule,
    CargaHorariaExcedidaRule,
    BloqueiosHardRule
};

use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\Soft\{
    JanelasRule,
    MaxAulasDiaRule,
    DistribuicaoRule,
    AulasNaoConsecutivasRule,
    BloqueiosPreferenciaisRule
};

use App\Services\GeneticAlgorithm\Genetico\Operators\{
    SelectionOperatorInterface,
    CrossoverOperatorInterface,
    MutationOperatorInterface
};
use Illuminate\Support\Facades\Log;

final class HorarioGeneticoOrchestrator
{
    private Horario $horario;
    private GeneticAlgorithmConfigDTO $configAG;

    private array $aulas = [];
    private array $restricoesIndexadas = [];

    public function __construct(
        private readonly SelectionOperatorInterface $selectionOperator,
        private readonly CrossoverOperatorInterface $crossoverOperator,
        private readonly MutationOperatorInterface $mutationOperator
    ) {}

    public function gerar(Horario $horario): array
    {
        $this->horario = $horario;

        $this->apagarAlocacoesHorario();

        $this->configAG = GeneticAlgorithmConfigDTO::fromModels($horario);

        Log::info("HorarioGeneticoOrchestrator@gerar: Carregando dados...");

        $this->carregarDados();

        Cache::put("horario_geracao_{$horario->id}", [
            'status' => 'em_execucao',
            'geracao_atual' => 0,
            'melhor_fitness' => INF,
        ], now()->addHours(2));

        Log::info("HorarioGeneticoOrchestrator@gerar: Configurando ambiente...");

        $populationGenerator = new PopulationGenerator(aulas: $this->aulas, configAG: $this->configAG);

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

        $fitnessEvaluator = new FitnessEvaluator(weights: $weights,  rules: $rules);

        $metrics = new MetricsRecorder();

        $termination = new MaxGenerationsOrFitnessCriterion(
            maxGenerations: $this->configAG->numeroGeracoes,
            targetFitness: $this->configAG->targetFitness,
            maxGenerationsWithoutImprovement: $this->configAG->maxGenerationsWithoutImprovement
        );

        Log::info("HorarioGeneticoOrchestrator@gerar: Gerando população inicial...");
        $population = $populationGenerator->generate($this->configAG->tamanhoPopulacao);

        $generation = 0;

        $cargaEsperada = $this->buildCargaEsperada();
        $diasPreferidos = $this->buildDiasPreferidos();
        $temposPreferidos = $this->buildTemposPreferidos();

        Log::info("HorarioGeneticoOrchestrator@gerar: Iniciando loop evolutivo...");
        while (!$termination->shouldTerminate($population, $generation, $metrics->getBestCromossomoOverall())) {
            $generation++;

            if (($generation % 10) == 1) {
                Log::info("HorarioGeneticoOrchestrator@gerar: Evolução na geração {$generation}...");
            }
            foreach ($population as $cromossomo) {

                $context = new EvaluationContext(
                    genes: $cromossomo->genes,
                    restricoesIndexadas: $this->restricoesIndexadas,
                    cargaEsperada: $cargaEsperada,
                    diasPreferidos: $diasPreferidos,
                    temposPreferidos: $temposPreferidos
                );

                $fitnessEvaluator->evaluate($cromossomo, $context);
            }

            usort($population, fn($a, $b) => $a->getFitness() <=> $b->getFitness());

            $metrics->recordGeneration($generation, $population);

            $this->atualizarCache($generation, $metrics);

            $population = $this->evolve($population);
        }

        Log::info("HorarioGeneticoOrchestrator@gerar: Processo evolutivo concluído...");

        return $this->finalizarGeracao($metrics->getBestCromossomoOverall(), $generation);

        Log::info("HorarioGeneticoOrchestrator@gerar: Processo concluído...");
    }

    /*
    |--------------------------------------------------------------------------
    | Evolução
    |--------------------------------------------------------------------------
    */

    private function evolve(array $population): array
    {
        $newPopulation = [];

        $elites = $this->selectionOperator->getElites($population, $this->configAG->elitismCount);

        foreach ($elites as $elite) {
            $newPopulation[] = $elite->copy();
        }

        while (count($newPopulation) < $this->configAG->tamanhoPopulacao) {

            [$p1, $p2] = $this->selectionOperator->select($population, 2);

            $offspring = (mt_rand() / mt_getrandmax() <= $this->configAG->taxaCrossover)
                ? $this->crossoverOperator->crossover($p1, $p2)
                : [$p1->copy(), $p2->copy()];

            foreach ($offspring as $child) {

                $this->mutationOperator->mutate($child);

                $newPopulation[] = $child;

                if (count($newPopulation) >= $this->configAG->tamanhoPopulacao) {
                    break;
                }
            }
        }

        return $newPopulation;
    }

    /*
    |--------------------------------------------------------------------------
    | Cache de progresso
    |--------------------------------------------------------------------------
    */

    private function atualizarCache(int $generation, MetricsRecorder $metrics): void
    {
        Cache::put("horario_geracao_{$this->horario->id}", [
            'status' => 'em_execucao',
            'geracao_atual' => $generation,
            'total_geracoes' => $this->configAG->numeroGeracoes,
            'melhor_fitness' => $metrics->getBestFitnessOverall(),
            'progresso' => round(
                ($generation / $this->configAG->numeroGeracoes) * 100,
                2
            ),
        ], now()->addHours(2));
    }

    /*
    |--------------------------------------------------------------------------
    | Finalização
    |--------------------------------------------------------------------------
    */

    private function finalizarGeracao(?Cromossomo $melhor, int $geracoes): array
    {
        if (!$melhor) {
            throw new \RuntimeException('Nenhum cromossomo encontrado.');
        }

        DB::transaction(function () use ($melhor, $geracoes) {

            foreach ($melhor->genes as $gene) {

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
            'alocacoes' => count($melhor->genes),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Dados auxiliares
    |--------------------------------------------------------------------------
    */

    private function apagarAlocacoesHorario() : void {
        Alocacao::where('horario_id', $this->horario->id)->delete();
    }

    private function carregarDados(): void
    {
        $this->aulas = Aula::where('horario_id', $this->horario->id)->where('ativa', true)->with(['professor', 'disciplina', 'turma'])->get()->all();

        $restricoes = RestricaoTempo::where('horario_id', $this->horario->id)->get()->all();

        foreach ($restricoes as $r) {
            $this->restricoesIndexadas[$r->entidade_type][$r->entidade_id][$r->dia_semana][$r->tempo] = true;
        }
    }

    private function buildCargaEsperada(): array
    {
        $map = [];
        foreach ($this->aulas as $aula) {
            $map[$aula->id] = $aula->aulas_semana;
        }
        return $map;
    }

    private function buildDiasPreferidos(): array
    {
        $map = [];
        foreach ($this->aulas as $aula) {
            $map[$aula->id] = $aula->dias_preferidos ?? [];
        }
        return $map;
    }

    private function buildTemposPreferidos(): array
    {
        $map = [];
        foreach ($this->aulas as $aula) {
            $map[$aula->id] = $aula->tempos_preferidos ?? [];
        }
        return $map;
    }

    private function getHorarioTempo(int $tempo): string
    {
        $inicio = CarbonImmutable::parse($this->configAG->horarioInicio);

        return $inicio
            ->addMinutes(($tempo - 1) * $this->configAG->duracaoAulaMinutos)
            ->format('H:i');
    }

    private function getHorarioTempoFim(int $tempo): string
    {
        return CarbonImmutable::parse($this->getHorarioTempo($tempo))->addMinutes($this->configAG->duracaoAulaMinutos)->format('H:i');
    }
}