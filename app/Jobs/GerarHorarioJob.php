<?php

namespace App\Jobs;

use App\Models\Horario;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Support\AGError;
use App\Modules\AG\Support\DTO\GeneticAlgorithmConfigDTO;
use App\Modules\AG\Support\Exceptions\InviableScheduleException;
use App\Modules\AG\Infrastructure\Population\PopulationGenerator;
use App\Modules\AG\Application\GeneticAlgorithmEngine;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Operators\TournamentSelection;
use App\Modules\AG\Domain\Operators\BlockPreservingCrossover;
use App\Modules\AG\Domain\Operators\StructuredSwapMutation;
use App\Modules\AG\Domain\Termination\MaxGenerationsOrFitnessCriterion;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Infrastructure\Progress\CacheProgressReporter;

class GerarHorarioJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(public Horario $horario) {
    }

    public function handle(): void {
        $progressReporter = new CacheProgressReporter($this->horario->id);

        try {

            Log::info('Iniciando geração de horário', [
                'horario_id' => $this->horario->id,
            ]);

            $progressReporter->clear();

            $this->horario->update(['status' => 'em_geracao']);

            $progressReporter->report([
                'status' => 'iniciando',
                'percentual' => 0,
            ]);

            /* ================= CONFIG ================= */

            $config = GeneticAlgorithmConfigDTO::fromModels($this->horario);

            /* ================= POPULAÇÃO ================= */

            $populationGenerator = new PopulationGenerator(
                aulas: $this->horario->aulas()->with(['professor', 'turma', 'disciplina'])->get()->all(),
                config: $config,
                progressReporter: $progressReporter
            );

            $initialPopulation = $populationGenerator->generate();

            /* ================= COMPONENTES ================= */

            $fitnessEvaluator = new FitnessEvaluator(
                weights: new FitnessWeights([]),
                rules: []
            );

            $engine = new GeneticAlgorithmEngine(
                fitnessEvaluator: $fitnessEvaluator,
                selectionOperator: new TournamentSelection(),
                crossoverOperator: new BlockPreservingCrossover(),
                mutationOperator: new StructuredSwapMutation(),
                terminationCriterion: new MaxGenerationsOrFitnessCriterion(
                    maxGenerations: $config->numeroGeracoes,
                    targetFitness: 100.0,
                    maxGenerationsWithoutImprovement: $config->limiteEstagnacao
                ),
                repairOperator: null,
                metricsRecorder: new MetricsRecorder(),
                progressReporter: $progressReporter
            );

            /* ================= EXECUÇÃO ================= */

            $best = $engine->run(
                initialPopulation: $initialPopulation,
                config: $config
            );

            /* ================= PERSISTÊNCIA ================= */

            $this->persistSolution($best);

            $this->horario->update([
                'status' => 'concluido',
                'fitness_score' => $best->fitness(),
                'gerado_em' => now(),
            ]);

            $progressReporter->reportCompleted($best->fitness());
        } catch (InviableScheduleException $e) {

            Log::warning('Horário inviável', [
                'horario_id' => $this->horario->id,
                'erro' => $e->getMessage(),
            ]);

            $this->horario->update(['status' => 'rascunho']);

            $progressReporter->reportError($e->getError());
        } catch (\Throwable $e) {

            Log::error('Erro crítico no Job AG', [
                'horario_id' => $this->horario->id,
                'exception' => $e->getMessage(),
            ]);

            $this->horario->update(['status' => 'rascunho']);

            $progressReporter->reportError(
                new AGError(
                    codigo: 'AG-500',
                    categoria: 'ERRO_SISTEMA',
                    mensagem: 'Erro crítico na execução do algoritmo.',
                    severidade: 'CRITICA',
                    dados: ['exception' => $e->getMessage()]
                )
            );
        }
    }

    /* ============================================================
     |  PERSISTÊNCIA COM TRANSAÇÃO
     ============================================================ */

    private function persistSolution(Cromossomo $cromossomo): void {
        DB::transaction(function () use ($cromossomo) {

            $this->horario->alocacoes()->delete();

            foreach ($cromossomo->genes() as $gene) {

                $this->horario->alocacoes()->create([
                    'aula_id' => $gene->aulaId(),
                    'professor_id' => $gene->professorId(),
                    'turma_id' => $gene->turmaId(),
                    'disciplina_id' => $gene->disciplinaId(),
                    'dia_semana' => $gene->diaSemana(),
                    'periodo' => $gene->periodoDia(),
                    'duracao' => $gene->duracaoTempos(),
                ]);
            }
        });
    }
}
