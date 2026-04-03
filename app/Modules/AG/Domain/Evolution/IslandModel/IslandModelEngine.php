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

    private ?float $bestFitnessEver = null;

    private int $lastSignificantImprovementGeneration = 0;

    /** @var array<int, array<string, mixed>> */
    private array $stagnationWindow = [];

    private bool $stagnationBurstArmed = false;

    private ?int $stagnationBurstStartedAtGeneration = null;

    /** @var array<string, mixed>|null */
    private ?array $lastStagnationEvaluation = null;

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

                $avgAlnsImprovement = empty($alnsImprovements)
                    ? null
                    : (array_sum($alnsImprovements) / count($alnsImprovements));

                $stagnationDecision = $this->evaluateStagnationPolicy(
                    generation: $generation,
                    bestFitness: $metricsDto->bestFitness,
                    diversity: $metricsDto->diversity,
                    entropy: $metricsDto->entropy,
                    avgAlnsImprovement: $avgAlnsImprovement,
                );

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
                    'alns_improvement' => $avgAlnsImprovement,
                    'island_best_fitness' => $islandBestFitness,
                    'migration_due' => $generation % $this->migrationInterval === 0,
                    'stagnation_window_ready' => $stagnationDecision['window_ready'],
                    'stagnation_triggered' => $stagnationDecision['triggered'],
                    'stagnation_burst_armed' => $stagnationDecision['burst_armed'],
                    'stagnation_burst_phase' => $stagnationDecision['burst_phase'],
                    'stagnation_early_stop' => $stagnationDecision['early_stop'],
                    'stagnation_reason' => $stagnationDecision['reason'],
                ]);

                if ($stagnationDecision['early_stop']) {
                    Log::warning('ga.stagnation.early_stop', [
                        'execution_id' => $this->executionId,
                        'generation' => $generation,
                        'max_generations' => $generations,
                        'best_fitness' => $metricsDto->bestFitness,
                        'window_size' => $stagnationDecision['window_size'],
                        'patience_generations' => $stagnationDecision['patience_generations'],
                        'reason' => $stagnationDecision['reason'],
                    ]);

                    break;
                }
            }
        }

        return $globalBest;
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateStagnationPolicy(
        int $generation,
        float $bestFitness,
        float $diversity,
        float $entropy,
        ?float $avgAlnsImprovement,
    ): array {
        $enabled = (bool) config('ag.stagnation_policy.enabled', true);
        $windowSize = max(3, (int) config('ag.stagnation_policy.window_size', 5));
        $minGenerations = max(1, (int) config('ag.stagnation_policy.min_generations_before_detection', 8));
        $patience = max(1, (int) config('ag.stagnation_policy.patience_generations', 5));
        $epsilon = max(0.0, (float) config('ag.stagnation_policy.improvement_epsilon', 0.0005));
        $diversityHighThreshold = max(0.0, min(1.0, (float) config('ag.stagnation_policy.diversity_high_threshold', 0.95)));
        $diversityStabilityTolerance = max(0.0, min(1.0, (float) config('ag.stagnation_policy.diversity_stability_tolerance', 0.02)));
        $requireAlnsNoGain = (bool) config('ag.stagnation_policy.require_alns_no_gain', true);

        $burstEnabled = (bool) config('ag.stagnation_policy.burst.enabled', true);
        $burstGenerations = max(1, (int) config('ag.stagnation_policy.burst.generations', 3));
        $burstMutationMultiplier = max(1.0, (float) config('ag.stagnation_policy.burst.mutation_multiplier', 1.35));
        $burstSelectionPressureMultiplier = max(0.34, min(1.0, (float) config('ag.stagnation_policy.burst.selection_pressure_multiplier', 0.85)));
        $burstForceAlns = (bool) config('ag.stagnation_policy.burst.force_alns', true);

        if (! $enabled) {
            return [
                'window_ready' => false,
                'triggered' => false,
                'burst_armed' => false,
                'burst_phase' => 'disabled',
                'early_stop' => false,
                'window_size' => $windowSize,
                'patience_generations' => $patience,
                'reason' => 'stagnation_policy_disabled',
            ];
        }

        if ($this->bestFitnessEver === null || ($bestFitness - $this->bestFitnessEver) > $epsilon) {
            $this->bestFitnessEver = $bestFitness;
            $this->lastSignificantImprovementGeneration = $generation;

            if ($this->stagnationBurstArmed) {
                Log::info('ga.stagnation.burst_released_after_improvement', [
                    'execution_id' => $this->executionId,
                    'generation' => $generation,
                    'best_fitness' => $bestFitness,
                ]);
            }

            $this->stagnationBurstArmed = false;
            $this->stagnationBurstStartedAtGeneration = null;
        }

        $this->stagnationWindow[] = [
            'generation' => $generation,
            'best_fitness' => $bestFitness,
            'diversity' => $diversity,
            'entropy' => $entropy,
            'alns_improvement' => $avgAlnsImprovement,
        ];

        while (count($this->stagnationWindow) > $windowSize) {
            array_shift($this->stagnationWindow);
        }

        $windowReady = count($this->stagnationWindow) >= $windowSize;
        $patienceReached = ($generation - $this->lastSignificantImprovementGeneration) >= $patience;

        $bestValues = array_values(array_map(
            static fn (array $item): float => (float) $item['best_fitness'],
            $this->stagnationWindow,
        ));
        $bestDeltaWindow = $bestValues === [] ? 0.0 : max($bestValues) - min($bestValues);

        $diversityValues = array_values(array_map(
            static fn (array $item): float => (float) $item['diversity'],
            $this->stagnationWindow,
        ));
        $diversityMin = $diversityValues === [] ? 0.0 : min($diversityValues);
        $diversitySpan = $diversityValues === [] ? 0.0 : (max($diversityValues) - min($diversityValues));

        $alnsValues = array_values(array_map(
            static fn (array $item): ?float => is_numeric($item['alns_improvement']) ? (float) $item['alns_improvement'] : null,
            $this->stagnationWindow,
        ));

        $alnsNoGain = true;

        if ($requireAlnsNoGain) {
            foreach ($alnsValues as $value) {
                if ($value !== null && $value > $epsilon) {
                    $alnsNoGain = false;

                    break;
                }
            }
        }

        $triggered = $generation >= $minGenerations
            && $windowReady
            && $patienceReached
            && $bestDeltaWindow <= $epsilon
            && $diversityMin >= $diversityHighThreshold
            && $diversitySpan <= $diversityStabilityTolerance
            && (! $requireAlnsNoGain || $alnsNoGain);

        $burstPhase = 'idle';
        $earlyStop = false;
        $reason = 'insufficient_evidence';

        if ($triggered && $burstEnabled && ! $this->stagnationBurstArmed) {
            $reason = 'stagnation_triggered_starting_burst';
            $burstPhase = 'arming';
            $this->stagnationBurstArmed = true;
            $this->stagnationBurstStartedAtGeneration = $generation;

            foreach ($this->islands as $island) {
                $island->activateStagnationBurst(
                    generation: $generation,
                    durationGenerations: $burstGenerations,
                    mutationMultiplier: $burstMutationMultiplier,
                    selectionPressureMultiplier: $burstSelectionPressureMultiplier,
                    forceAlns: $burstForceAlns,
                    reason: 'global_stagnation_detected',
                );
            }

            Log::warning('ga.stagnation.burst_started', [
                'execution_id' => $this->executionId,
                'generation' => $generation,
                'burst_generations' => $burstGenerations,
                'mutation_multiplier' => $burstMutationMultiplier,
                'selection_pressure_multiplier' => $burstSelectionPressureMultiplier,
                'force_alns' => $burstForceAlns,
                'best_delta_window' => $bestDeltaWindow,
                'diversity_min' => $diversityMin,
                'diversity_span' => $diversitySpan,
            ]);
        } elseif ($this->stagnationBurstArmed) {
            $burstPhase = 'running';
            $elapsedBurstGenerations = max(0, $generation - (int) ($this->stagnationBurstStartedAtGeneration ?? $generation));

            if ($elapsedBurstGenerations >= $burstGenerations && $triggered) {
                $earlyStop = true;
                $burstPhase = 'completed_without_gain';
                $reason = 'stagnation_persisted_after_burst';
            } elseif ($elapsedBurstGenerations >= $burstGenerations && ! $triggered) {
                $burstPhase = 'completed_recovered';
                $reason = 'burst_recovered_search';
                $this->stagnationBurstArmed = false;
                $this->stagnationBurstStartedAtGeneration = null;
            } else {
                $reason = 'burst_running_waiting_reassessment';
            }
        } elseif ($triggered && ! $burstEnabled) {
            $earlyStop = true;
            $reason = 'stagnation_triggered_without_burst';
            $burstPhase = 'disabled';
        } elseif ($triggered) {
            $reason = 'stagnation_triggered';
        }

        $this->lastStagnationEvaluation = [
            'window_ready' => $windowReady,
            'triggered' => $triggered,
            'burst_armed' => $this->stagnationBurstArmed,
            'burst_phase' => $burstPhase,
            'early_stop' => $earlyStop,
            'reason' => $reason,
            'best_delta_window' => $bestDeltaWindow,
            'diversity_min' => $diversityMin,
            'diversity_span' => $diversitySpan,
            'window_size' => $windowSize,
            'patience_generations' => $patience,
        ];

        return $this->lastStagnationEvaluation;
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
