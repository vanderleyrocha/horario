<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use App\Models\ScheduleExecution;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Support\Exceptions\ExecutionCancelledException;
use Illuminate\Support\Facades\Log;

final class IslandModelEngine
{
    /** @var Island[] */
    private array $islands = [];

    private ?MetricsRecorder $globalMetrics = null;

    private ?ProgressReporterInterface $progress = null;

    private float $telemetryMutationRate = 0.05;

    private ?int $executionId = null;

    // 🔧 PRIORIDADE 5: Rastreamento de terminação antecipada
    private float $previousBestFitness = -999999.0;

    private int $generationsSinceImprovement = 0;

    /** @var array<float> */
    private array $fitnessHistory = [];

    private const STAGNATION_THRESHOLD = 15;

    private const FITNESS_DEGRADATION_THRESHOLD = -5.0;

    private const VIABLE_FITNESS_THRESHOLD = 50.0;

    public function __construct(
        private readonly MigrationPolicyInterface $migrationPolicy,
        private readonly int $migrationInterval = 20,
    ) {
    }

    public function addIsland(Island $island): void
    {
        $this->islands[] = $island;
    }

    public function setTelemetry(
        MetricsRecorder $metrics,
        ProgressReporterInterface $progress,
        float $mutationRate = 0.05,
    ): void {
        $this->globalMetrics = $metrics;
        $this->progress = $progress;
        $this->telemetryMutationRate = $mutationRate;
    }

    public function setExecutionId(int $executionId): void
    {
        $this->executionId = $executionId;
    }

    public function run(int $generations): Cromossomo
    {
        $this->assertNotCancelled();

        foreach ($this->islands as $island) {
            $island->initialize();
        }

        $globalBest = null;
        Log::info('Iniciando o motor de orquestracao sincronica das ilhas.', [
            'execution_id' => $this->executionId,
            'island_count' => count($this->islands),
            'max_generations' => $generations,
            'migration_interval' => $this->migrationInterval,
        ]);

        for ($generation = 1; $generation <= $generations; $generation++) {
            $this->assertNotCancelled();
            $globalPopulation = [];
            $telemetrySnapshots = [];
            $islandBestFitness = [];

            foreach ($this->islands as $island) {
                $islandStartedAt = microtime(true);
                $island->evolveGeneration();
                $bestInIsland = $island->best();
                $snapshot = $island->telemetrySnapshot();
                $telemetrySnapshots[] = $snapshot;
                $islandBestFitness[$island->getislandNum()] = $bestInIsland->fitness();

                Log::info('ga.island.generation.completed', [
                    'execution_id' => $this->executionId,
                    'global_generation' => $generation,
                    'island_id' => $island->getislandNum(),
                    'local_generation' => $island->currentGeneration(),
                    'elapsed_ms' => (int) round((microtime(true) - $islandStartedAt) * 1000),
                    'best_fitness' => $bestInIsland->fitness(),
                    'mutation_rate' => $snapshot['mutation_rate'] ?? null,
                    'operator_used' => $snapshot['operator_used'] ?? null,
                    'operator_reward' => $snapshot['operator_reward'] ?? null,
                    'landscape_state' => $snapshot['landscape_state'] ?? null,
                    'landscape_phenomenon' => $snapshot['landscape_phenomenon'] ?? null,
                    'alns_destroy_operator' => $snapshot['alns_destroy_operator'] ?? null,
                    'alns_repair_operator' => $snapshot['alns_repair_operator'] ?? null,
                    'alns_improvement' => $snapshot['alns_improvement'] ?? null,
                    'population_turnover' => $snapshot['population_turnover'] ?? null,
                    'best_signature_changed' => $snapshot['best_signature_changed'] ?? null,
                ]);

                if ($globalBest === null || $bestInIsland->fitness() > $globalBest->fitness()) {
                    $globalBest = $bestInIsland;
                }

                $globalPopulation = array_merge($globalPopulation, $island->population());
            }

            // 🔧 PRIORIDADE 5: Detecção de terminação antecipada e restart
            if ($globalBest !== null) {
                $this->trackFitnessHistory($globalBest->fitness());

                // Verificar terminação antecipada se viável (score >= 50)
                if ($this->isViableSolution($globalBest)) {
                    Log::info('Solução viável detectada! Terminando gerações antecipadamente.', [
                        'generation' => $generation,
                        'fitness' => $globalBest->fitness(),
                    ]);
                    break;  // ← Sair do loop, solução viável encontrada
                }

                // Detectar estagnação (sem melhoria por N gerações)
                if ($this->detectStagnation($globalBest->fitness())) {
                    Log::warning('Estagnação detectada! Acionando restart com preservação de elite.', [
                        'generation' => $generation,
                        'generations_without_improvement' => $this->generationsSinceImprovement,
                    ]);

                    // Trigger restart preservando 10% de elite
                    $this->performRestartCycle($this->islands, $globalPopulation);
                    $this->generationsSinceImprovement = 0;  // Reset contador
                }

                // Monitorar degradação de fitness (fitness piorando)
                if ($this->detectFitnessDegradation()) {
                    Log::info('Degradação de fitness detectada. Considerando switch de operadores ALNS.', [
                        'generation' => $generation,
                        'degradation_delta' => $this->calculateFitnessDelta(),
                    ]);
                    // Nota: O switch de operadores é feito pelo LearningHyperHeuristicController
                }
            }

            if ($generation % $this->migrationInterval === 0) {
                $this->migrationPolicy->migrate($this->islands);
            }

            if ($this->globalMetrics && $this->progress) {
                $mutationRates = array_values(array_filter(array_map(
                    static fn (array $snapshot) => $snapshot['mutation_rate'] ?? null,
                    $telemetrySnapshots,
                ), static fn ($value) => $value !== null));

                $operatorRewards = array_values(array_filter(array_map(
                    static fn (array $snapshot) => $snapshot['operator_reward'] ?? null,
                    $telemetrySnapshots,
                ), static fn ($value) => $value !== null));

                $alnsImprovements = array_values(array_filter(array_map(
                    static fn (array $snapshot) => $snapshot['alns_improvement'] ?? null,
                    $telemetrySnapshots,
                ), static fn ($value) => $value !== null));

                $operatorUsed = collect($telemetrySnapshots)
                    ->pluck('operator_used')
                    ->first(fn ($operator) => $operator !== null && $operator !== 'none');

                $alnsDestroyOperator = collect($telemetrySnapshots)
                    ->pluck('alns_destroy_operator')
                    ->first(fn ($operator) => $operator !== null);

                $alnsRepairOperator = collect($telemetrySnapshots)
                    ->pluck('alns_repair_operator')
                    ->first(fn ($operator) => $operator !== null);

                $landscapeState = collect($telemetrySnapshots)
                    ->pluck('landscape_state')
                    ->first(fn ($state) => $state !== null);

                $landscapePhenomenon = collect($telemetrySnapshots)
                    ->pluck('landscape_phenomenon')
                    ->first(fn ($phenomenon) => $phenomenon !== null);

                $landscapeObservation = collect($telemetrySnapshots)
                    ->pluck('landscape_observation')
                    ->filter(fn ($observation) => is_array($observation))
                    ->sortByDesc(fn (array $observation) => (float) ($observation['confidence'] ?? 0.0))
                    ->first();

                $metricsDto = $this->globalMetrics->recordExtended(
                    $generation,
                    $globalPopulation,
                    empty($mutationRates) ? $this->telemetryMutationRate : array_sum($mutationRates) / count($mutationRates),
                    0,
                    $landscapeState ?? 'Exploracao Intensiva',
                );

                $this->progress->report([
                    'phase' => 'evolving',
                    'generation' => $metricsDto->generation,
                    'max_generations' => $generations,
                    'best_fitness' => $metricsDto->bestFitness,
                    'avg_fitness' => $metricsDto->avgFitness,
                    'variance' => $metricsDto->variance,
                    'diversity' => $metricsDto->diversity,
                    'entropy' => $metricsDto->entropy,
                    'mutation_rate' => $metricsDto->mutationRate,
                    'stagnation' => $metricsDto->stagnation,
                    'landscape_state' => $metricsDto->landscapeState,
                    'landscape_phenomenon' => $landscapePhenomenon,
                    'landscape_observation' => $landscapeObservation,
                    'operator_used' => $operatorUsed,
                    'operator_reward' => empty($operatorRewards) ? 0.0 : array_sum($operatorRewards) / count($operatorRewards),
                    'alns_destroy_operator' => $alnsDestroyOperator,
                    'alns_repair_operator' => $alnsRepairOperator,
                    'alns_improvement' => empty($alnsImprovements) ? null : array_sum($alnsImprovements) / count($alnsImprovements),
                ]);

                Log::info('ga.islands.generation.completed', [
                    'execution_id' => $this->executionId,
                    'global_generation' => $generation,
                    'max_generations' => $generations,
                    'best_fitness' => $metricsDto->bestFitness,
                    'avg_fitness' => $metricsDto->avgFitness,
                    'variance' => $metricsDto->variance,
                    'diversity' => $metricsDto->diversity,
                    'entropy' => $metricsDto->entropy,
                    'mutation_rate' => $metricsDto->mutationRate,
                    'landscape_state' => $metricsDto->landscapeState,
                    'operator_used' => $operatorUsed,
                    'operator_reward' => empty($operatorRewards) ? 0.0 : array_sum($operatorRewards) / count($operatorRewards),
                    'alns_destroy_operator' => $alnsDestroyOperator,
                    'alns_repair_operator' => $alnsRepairOperator,
                    'alns_improvement' => empty($alnsImprovements) ? null : array_sum($alnsImprovements) / count($alnsImprovements),
                    'island_best_fitness' => $islandBestFitness,
                    'migration_due' => $generation % $this->migrationInterval === 0,
                ]);
            }
        }

        return $globalBest;
    }

    // 🔧 PRIORIDADE 5: Métodos auxiliares para early termination

    /**
     * Verifica se a solução é viável (score >= 50.0).
     * Uma solução viável tem nenhuma ou poucas violações de restrições rígidas.
     */
    private function isViableSolution(Cromossomo $chromosome): bool
    {
        // Score >= 50.0 indica viabilidade (calculado pela FitnessEvaluator)
        return $chromosome->fitness() >= self::VIABLE_FITNESS_THRESHOLD;
    }

    /**
     * Detecta estagnação: sem melhoria no best fitness por N gerações.
     * Retorna true se deve pausar a busca e fazer restart.
     */
    private function detectStagnation(float $currentBestFitness): bool
    {
        if ($currentBestFitness > $this->previousBestFitness) {
            // Houve melhoria! Reset contador
            $this->previousBestFitness = $currentBestFitness;
            $this->generationsSinceImprovement = 0;

            return false;
        }

        // Sem melhoria
        $this->generationsSinceImprovement++;

        return $this->generationsSinceImprovement >= self::STAGNATION_THRESHOLD;
    }

    /**
     * Realiza ciclo de restart preservando 10% de elite.
     * - Mantém os 10% melhores indivíduos
     * - Gera 90% de nova população
     * - Reinicializa as ilhas
     *
     * @param Island[] $islands
     * @param Cromossomo[] $globalPopulation
     */
    private function performRestartCycle(array $islands, array $globalPopulation): void
    {
        if (empty($globalPopulation)) {
            return;
        }

        // Ordenar população por fitness (melhor primeiro)
        usort($globalPopulation, static function (Cromossomo $a, Cromossomo $b): int {
            return $b->fitness() <=> $a->fitness();
        });

        // Preservar elite (10% melhores)
        $eliteCount = max(1, (int) (count($globalPopulation) * 0.10));
        $elite = array_slice($globalPopulation, 0, $eliteCount);

        Log::info('Restart cycle: preservando elite', [
            'total_population' => count($globalPopulation),
            'elite_count' => $eliteCount,
            'elite_fitness' => array_map(fn (Cromossomo $c) => $c->fitness(), $elite),
        ]);

        // Redistribuir elite entre as ilhas, e gerar 90% nova população
        // Cada ilha mantém seus indivíduos mas some nova população aleatória
        foreach ($islands as $island) {
            $island->reinitializeWithElite($elite);
        }
    }

    /**
     * Detecta degradação de fitness (piora significativa).
     * Retorna true se a média de fitness recente piorou mais de 5.0 pontos.
     */
    private function detectFitnessDegradation(): bool
    {
        if (count($this->fitnessHistory) < 2) {
            return false;
        }

        $delta = $this->calculateFitnessDelta();

        return $delta < self::FITNESS_DEGRADATION_THRESHOLD;
    }

    /**
     * Calcula o delta de fitness entre a geração atual e a anterior.
     */
    private function calculateFitnessDelta(): float
    {
        if (count($this->fitnessHistory) < 2) {
            return 0.0;
        }

        $lastIndex = count($this->fitnessHistory) - 1;
        $current = $this->fitnessHistory[$lastIndex];
        $previous = $this->fitnessHistory[$lastIndex - 1];

        return round($current - $previous, 2);
    }

    /**
     * Rastreia o histórico de fitness para detecção de degradação.
     * Mantém um histórico das últimas 20 gerações.
     */
    private function trackFitnessHistory(float $fitness): void
    {
        $this->fitnessHistory[] = $fitness;

        // Manter apenas últimas 20 gerações
        if (count($this->fitnessHistory) > 20) {
            array_shift($this->fitnessHistory);
        }
    }

    private function assertNotCancelled(): void
    {
        if ($this->executionId === null) {
            return;
        }

        $status = ScheduleExecution::query()
            ->whereKey($this->executionId)
            ->value('status');

        if (in_array($status, ['cancel_requested', 'cancelled'], true)) {
            throw ExecutionCancelledException::forExecution($this->executionId);
        }
    }
}
