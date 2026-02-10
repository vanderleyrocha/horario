<?php

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Models\Horario;
use App\Models\Aula;
use App\Models\Alocacao;
use App\Models\Professor;
use App\Models\RestricaoTempo;
use App\Models\Turma;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Repositories\ConfiguracaoHorarioReadRepository;
use Carbon\CarbonImmutable;
use App\Services\GeneticAlgorithm\Genetico\PopulationGenerator;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessEvaluator;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;
use App\Services\GeneticAlgorithm\Genetico\Operators\SelectionOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Operators\CrossoverOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Operators\MutationOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Termination\TerminationCriterionInterface;
use App\Services\GeneticAlgorithm\Genetico\Metrics\MetricsRecorder;
use App\Services\GeneticAlgorithm\Genetico\Fitness\HardRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\SoftRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Termination\MaxGenerationsOrFitnessCriterion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log; // ✅ ADICIONADO para logs de depuração

class HorarioGeneticoOrchestrator {
    protected Horario $horario;
    protected GeneticAlgorithmConfigDTO $configAG;
    protected Collection $aulas;
    protected Collection $restricoes;

    protected ConfiguracaoHorarioReadRepository $configuracaoHorarioReadRepository;
    protected PopulationGenerator $populationGenerator;
    protected FitnessEvaluator $fitnessEvaluator;
    protected SelectionOperatorInterface $selectionOperator;
    protected CrossoverOperatorInterface $crossoverOperator;
    protected MutationOperatorInterface $mutationOperator;
    protected TerminationCriterionInterface $terminationCriterion;
    protected MetricsRecorder $metricsRecorder;

    protected string $currentStatusMessage = 'Iniciando geração...'; // ✅ ADICIONADO: Propriedade para mensagem de status

    public function __construct(
        ConfiguracaoHorarioReadRepository $configuracaoHorarioReadRepository,
        PopulationGenerator $populationGenerator,
        FitnessEvaluator $fitnessEvaluator,
        SelectionOperatorInterface $selectionOperator,
        CrossoverOperatorInterface $crossoverOperator,
        MutationOperatorInterface $mutationOperator,
        TerminationCriterionInterface $terminationCriterion,
        MetricsRecorder $metricsRecorder
    ) {
        $this->configuracaoHorarioReadRepository = $configuracaoHorarioReadRepository;
        $this->populationGenerator = $populationGenerator;
        $this->fitnessEvaluator = $fitnessEvaluator;
        $this->selectionOperator = $selectionOperator;
        $this->crossoverOperator = $crossoverOperator;
        $this->mutationOperator = $mutationOperator;
        $this->terminationCriterion = $terminationCriterion;
        $this->metricsRecorder = $metricsRecorder;
    }

    public function gerar(Horario $horario): array {
        $this->horario = $horario;
        $this->currentStatusMessage = 'Carregando configurações do horário...'; // ✅ ATUALIZADO
        $this->carregarConfiguracoes();
        $this->currentStatusMessage = 'Carregando dados de aulas e restrições...'; // ✅ ATUALIZADO
        $this->carregarDados();

        $this->populationGenerator->setContext($this->horario->configuracaoHorario, $this->aulas, $this->configAG);
        $this->fitnessEvaluator->setContext($this->horario->configuracaoHorario, $this->aulas, $this->restricoes, $this->configAG);
        if (method_exists($this->mutationOperator, 'setContext')) {
            $this->mutationOperator->setContext($this->configAG);
        }
        // Reinicia o recorder e o critério de terminação para cada nova geração
        $this->metricsRecorder = new MetricsRecorder(); // Garante que o recorder é novo para cada execução
        $this->terminationCriterion = new MaxGenerationsOrFitnessCriterion(); // Garante que o critério é novo
        if (method_exists($this->terminationCriterion, 'setContext')) {
            $this->terminationCriterion->setContext($this->configAG);
        }

        $this->iniciarGeracao(); // Define status inicial e limpa alocações

        $this->currentStatusMessage = 'Gerando população inicial...'; // ✅ ATUALIZADO
        /** @var Collection<int, Cromossomo> $population */
        $population = $this->populationGenerator->generate($this->configAG->tamanhoPopulacao);

        $generation = 0;
        while (!$this->terminationCriterion->shouldTerminate($population, $generation, $this->metricsRecorder->getBestCromossomoOverall())) {
            $generation++;
            $this->currentStatusMessage = "Avaliando geração {$generation}..."; // ✅ ATUALIZADO

            // 1. Avaliar fitness da população
            foreach ($population as $cromossomo) {
                $this->fitnessEvaluator->evaluate($cromossomo);
            }

            // Ordenar a população pelo fitness (do maior para o menor)
            $population = $population->sortByDesc(fn(Cromossomo $c) => $c->getFitnessScore())->values();

            // Registrar métricas da geração
            $this->metricsRecorder->recordGeneration($generation, $population);

            // Atualizar progresso para o cache e DB
            $this->atualizarProgresso($generation, $this->metricsRecorder->getBestFitnessOverall(), $population->first());

            // 2. Selecionar pais e aplicar elitismo
            $newPopulation = new Collection();

            // Elitismo: os melhores cromossomos passam diretamente para a próxima geração
            $elites = $this->selectionOperator->getElites($population, $this->configAG->elitismCount);
            foreach ($elites as $elite) {
                $newPopulation->add($elite->clone()); // Clona para evitar referências diretas
            }
            $newGeneration = $generation + 1;
            $this->currentStatusMessage = "Gerando nova população para a geração {$newGeneration}..."; // ✅ ATUALIZADO
            // 3. Gerar nova população através de cruzamento e mutação
            while ($newPopulation->count() < $this->configAG->tamanhoPopulacao) {
                $parents = $this->selectionOperator->select($population, 2); // Seleciona 2 pais
                $parent1 = $parents->first();
                $parent2 = $parents->last();

                if (mt_rand() / mt_getrandmax() <= $this->configAG->taxaCrossover) {
                    $offspring = $this->crossoverOperator->crossover($parent1, $parent2);
                } else {
                    // Se não houver crossover, os pais são clonados para a próxima geração
                    $offspring = new Collection([$parent1->clone(), $parent2->clone()]);
                }

                foreach ($offspring as $child) {
                    if (mt_rand() / mt_getrandmax() <= $this->configAG->taxaMutacao) {
                        $this->mutationOperator->mutate($child, $this->configAG->taxaMutacao); // Passa a taxa de mutação
                    }
                    $newPopulation->add($child);
                    if ($newPopulation->count() >= $this->configAG->tamanhoPopulacao) {
                        break;
                    }
                }
            }

            $population = $newPopulation;
        }

        $this->currentStatusMessage = 'Finalizando geração e salvando resultados...'; // ✅ ATUALIZADO
        // Avaliar a população final uma última vez para garantir que o fitness esteja atualizado
        foreach ($population as $cromossomo) {
            $this->fitnessEvaluator->evaluate($cromossomo);
        }
        $population = $population->sortByDesc(fn(Cromossomo $c) => $c->getFitnessScore())->values();

        $melhorSolucao = $this->metricsRecorder->getBestCromossomoOverall();
        if (!$melhorSolucao) {
            // Fallback caso não haja melhor cromossomo registrado (ex: população vazia ou erro)
            $melhorSolucao = $population->first();
            Log::warning("HorarioGeneticoOrchestrator: No best cromossomo from MetricsRecorder, falling back to current population's best.");
        }

        return $this->finalizarGeracao($melhorSolucao, $generation);
    }

    protected function carregarConfiguracoes(): void {
        $this->configAG = GeneticAlgorithmConfigDTO::fromModels($this->horario);

        $diasSemana = range(1, $this->configAG->diasSemana);
        $tempos = range(1, $this->configAG->aulasPorDia);
        $horariosDisponiveis = [];
        foreach ($diasSemana as $dia) {
            foreach ($tempos as $tempo) {
                $horariosDisponiveis[] = ['dia' => $dia, 'tempo' => $tempo];
            }
        }
        $this->configAG->horariosDisponiveis = $horariosDisponiveis;
    }

    protected function carregarDados(): void {
        $this->aulas = Aula::where('horario_id', $this->horario->id)
            ->where('ativa', true)
            ->with(['professor', 'disciplina', 'turma'])
            ->get();

        if ($this->aulas->isEmpty()) {
            throw new \Exception('Nenhuma aula configurada. Adicione aulas antes de gerar o horário.');
        }

        $this->restricoes = RestricaoTempo::where('horario_id', $this->horario->id)
            ->get()
            ->groupBy(function ($restricao) {
                return "{$restricao->entidade_type}_{$restricao->entidade_id}";
            });
    }

    protected function iniciarGeracao(): void {
        $this->horario->update([
            'status' => 'em_geracao',
            'fitness_score' => null,
            'geracoes_executadas' => 0,
            'melhor_fitness' => 0,
            'historico_fitness' => [],
            'conflitos_hard' => 0,
            'conflitos_soft' => 0,
        ]);

        Alocacao::where('horario_id', $this->horario->id)->delete();

        // Inicializa o cache de progresso
        Cache::put("horario_geracao_{$this->horario->id}", [
            'geracao_atual' => 0,
            'total_geracoes' => $this->configAG->numeroGeracoes,
            'melhor_fitness' => 0.0,
            'progresso' => 0,
            'mensagem_status' => 'Preparando para iniciar a geração...', // ✅ ADICIONADO
        ], now()->addHours(2));
    }

    protected function atualizarProgresso(int $geracao, float $fitness, Cromossomo $melhorIndividuo): void
    {
        Cache::put("horario_geracao_{$this->horario->id}", [
            'geracao_atual' => $geracao,
            'total_geracoes' => $this->configAG->numeroGeracoes,
            'melhor_fitness' => $fitness,
            'progresso' => round(($geracao / $this->configAG->numeroGeracoes) * 100, 2),
            'mensagem_status' => $this->currentStatusMessage, // ✅ ADICIONADO
        ], now()->addHours(2));

        if ($geracao % 10 === 0) {
            $this->horario->update([
                'geracoes_executadas' => $geracao,
                'melhor_fitness' => $fitness,
                'fitness_medio' => $this->metricsRecorder->getGenerationData()[$geracao]['average_fitness'] ?? $fitness,
            ]);
        }
    }

    protected function finalizarGeracao(Cromossomo $melhorSolucao, int $geracoesExecutadas): array
    {
        DB::beginTransaction();

        try {
            $this->fitnessEvaluator->evaluate($melhorSolucao);

            foreach ($melhorSolucao->genes as $gene) {
                if (!$gene->isEmpty()) {
                    Alocacao::create([
                        'horario_id' => $this->horario->id,
                        'aula_id' => $gene->aula->id,
                        'professor_id' => $gene->professor->id,
                        'disciplina_id' => $gene->disciplina->id,
                        'turma_id' => $gene->turma->id,
                        'dia_semana' => $gene->diaSemana,
                        'tempo' => $gene->periodoDia,
                        'horario_inicio' => $this->getHorarioTempo($gene->periodoDia),
                        'horario_fim' => $this->getHorarioTempoFim($gene->periodoDia),
                        'duracao_tempos' => $gene->duracaoTempos,
                        'eh_manual' => false,
                        'bloqueada' => false,
                    ]);
                }
            }

            $fitnessResult = $melhorSolucao->fitnessResult;
            $totalConflitosHard = $this->fitnessEvaluator->getTotalHardPenalties($melhorSolucao);
            $totalConflitosSoft = $this->fitnessEvaluator->getTotalSoftPenalties($melhorSolucao);

            $this->horario->update([
                'status' => 'concluido',
                'fitness_score' => $melhorSolucao->getFitnessScore(),
                'geracoes_executadas' => $geracoesExecutadas,
                'geracoes_sem_melhoria' => $this->terminationCriterion->getGenerationsWithoutImprovement(),
                'melhor_fitness' => $this->metricsRecorder->getBestFitnessOverall(),
                'fitness_medio' => collect($this->metricsRecorder->getGenerationData())->avg('average_fitness'),
                'historico_fitness' => $this->metricsRecorder->getGenerationData(),
                'conflitos_hard' => $totalConflitosHard,
                'conflitos_soft' => $totalConflitosSoft,
                'gerado_em' => now(),
            ]);

            DB::commit();

            Cache::forget("horario_geracao_{$this->horario->id}");

            return [
                'sucesso' => true,
                'fitness' => $melhorSolucao->getFitnessScore(),
                'geracoes' => $geracoesExecutadas,
                'conflitos_hard' => $totalConflitosHard,
                'alocacoes' => $melhorSolucao->genes->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            $this->horario->update([
                'status' => 'rascunho',
            ]);

            // ✅ ADICIONADO: Atualizar cache com status de erro
            Cache::put("horario_geracao_{$this->horario->id}", [
                'geracao_atual' => $geracoesExecutadas,
                'total_geracoes' => $this->configAG->numeroGeracoes,
                'melhor_fitness' => $this->metricsRecorder->getBestFitnessOverall(),
                'progresso' => round(($geracoesExecutadas / $this->configAG->numeroGeracoes) * 100, 2),
                'mensagem_status' => 'Erro durante a geração: ' . $e->getMessage(), // ✅ ADICIONADO
                'status_geracao' => 'erro', // ✅ ADICIONADO para o Livewire saber que houve um erro
            ], now()->addMinutes(10)); // Cache por um tempo menor para erro

            throw $e;
        }
    }

    protected function getHorarioTempo(int $tempo): string {
        $horarioInicio = CarbonImmutable::parse($this->configAG->horarioInicio);
        $duracaoAula = $this->configAG->duracaoAulaMinutos;
        $duracaoIntervalo = $this->configAG->duracaoIntervaloMinutos;
        $horariosIntervalos = $this->configAG->horariosIntervalos;
        $duracoesIntervalos = $this->configAG->duracoesIntervalos;

        $currentHorario = $horarioInicio;
        for ($i = 1; $i < $tempo; $i++) {
            $currentHorario = $currentHorario->addMinutes($duracaoAula);
            if (in_array($i, $horariosIntervalos)) {
                $intervaloIndex = array_search($i, $horariosIntervalos);
                $intervaloDuracao = $duracoesIntervalos[$intervaloIndex] ?? $duracaoIntervalo;
                $currentHorario = $currentHorario->addMinutes($intervaloDuracao);
            }
        }
        return $currentHorario->format('H:i');
    }

    protected function getHorarioTempoFim(int $tempo): string {
        $horarioInicioTempo = CarbonImmutable::parse($this->getHorarioTempo($tempo));
        return $horarioInicioTempo->addMinutes($this->configAG->duracaoAulaMinutos)->format('H:i');
    }
}
