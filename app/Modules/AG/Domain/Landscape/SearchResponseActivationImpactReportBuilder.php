<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class SearchResponseActivationImpactReportBuilder
{
    /**
     * @param  iterable<int, ScheduleExecution>  $executions
     */
    public function build(iterable $executions, int $windowSize = 2): SearchResponseActivationImpactReport
    {
        $events = [];
        $ignoredEdgeActivationEvents = 0;
        $trendBuckets = [
            'worsening' => $this->emptyTrendBucket(),
            'improving' => $this->emptyTrendBucket(),
        ];

        foreach ($executions as $execution) {
            $metrics = $this->resolveMetrics($execution);

            foreach ($metrics as $index => $metric) {
                $observation = is_array($metric->landscape_observation) ? $metric->landscape_observation : [];
                $realActivation = is_array($observation['alns_trigger']['real_activation'] ?? null)
                    ? $observation['alns_trigger']['real_activation']
                    : [];

                if (($realActivation['applied'] ?? false) !== true) {
                    continue;
                }

                $beforeWindow = array_slice($metrics, max(0, $index - $windowSize), min($windowSize, $index));
                $afterWindow = array_slice($metrics, $index + 1, $windowSize);

                if ($beforeWindow === [] || $afterWindow === []) {
                    $ignoredEdgeActivationEvents++;

                    continue;
                }

                $event = $this->buildEventRow($execution, $metric, $beforeWindow, $afterWindow);
                $events[] = $event;

                $direction = $event['trend_direction'] ?? null;

                if (is_string($direction) && isset($trendBuckets[$direction])) {
                    $this->mergeTrendBucket($trendBuckets[$direction], $event);
                }
            }
        }

        usort(
            $events,
            static fn (array $left, array $right): int => [$right['execution_id'], $right['activation_generation']]
                <=> [$left['execution_id'], $left['activation_generation']]
        );

        $positiveImpactEvents = count(array_filter(
            $events,
            static fn (array $event): bool => ($event['impact_label'] ?? null) === 'positive'
        ));
        $mixedImpactEvents = count(array_filter(
            $events,
            static fn (array $event): bool => ($event['impact_label'] ?? null) === 'mixed'
        ));
        $negativeImpactEvents = count(array_filter(
            $events,
            static fn (array $event): bool => ($event['impact_label'] ?? null) === 'negative'
        ));

        $trendComparison = [
            'worsening' => $this->finalizeTrendBucket($trendBuckets['worsening']),
            'improving' => $this->finalizeTrendBucket($trendBuckets['improving']),
        ];

        return new SearchResponseActivationImpactReport(
            status: $this->resolveStatus(count($events)),
            headline: $this->resolveHeadline(count($events), $ignoredEdgeActivationEvents, $windowSize),
            windowSize: $windowSize,
            analyzedActivationEvents: count($events),
            ignoredEdgeActivationEvents: $ignoredEdgeActivationEvents,
            positiveImpactEvents: $positiveImpactEvents,
            mixedImpactEvents: $mixedImpactEvents,
            negativeImpactEvents: $negativeImpactEvents,
            betterTrendContext: $this->resolveBetterTrendContext($trendComparison),
            trendComparison: $trendComparison,
            events: $events,
        );
    }

    /**
     * @return array<int, ScheduleGenerationMetric>
     */
    private function resolveMetrics(ScheduleExecution $execution): array
    {
        if ($execution->relationLoaded('metrics')) {
            $loaded = $execution->getRelation('metrics');

            if ($loaded instanceof EloquentCollection || $loaded instanceof Collection) {
                return $loaded
                    ->sortBy('generation')
                    ->values()
                    ->all();
            }
        }

        return $execution->metrics()
            ->select(['id', 'execution_id', 'generation', 'best_fitness', 'avg_fitness', 'landscape_observation'])
            ->orderBy('generation')
            ->get()
            ->all();
    }

    /**
     * @param  array<int, ScheduleGenerationMetric>  $beforeWindow
     * @param  array<int, ScheduleGenerationMetric>  $afterWindow
     * @return array<string, mixed>
     */
    private function buildEventRow(
        ScheduleExecution $execution,
        ScheduleGenerationMetric $activationMetric,
        array $beforeWindow,
        array $afterWindow,
    ): array {
        $activationObservation = is_array($activationMetric->landscape_observation) ? $activationMetric->landscape_observation : [];
        $episodeTrend = is_array($activationObservation['episode_trend'] ?? null)
            ? $activationObservation['episode_trend']
            : [];
        $realActivation = is_array($activationObservation['alns_trigger']['real_activation'] ?? null)
            ? $activationObservation['alns_trigger']['real_activation']
            : [];

        $beforeSummary = $this->summarizeWindow($beforeWindow);
        $afterSummary = $this->summarizeWindow($afterWindow);

        $bestFitnessDelta = round(($afterSummary['avg_best_fitness'] ?? 0.0) - ($beforeSummary['avg_best_fitness'] ?? 0.0), 4);
        $avgFitnessDelta = round(($afterSummary['avg_avg_fitness'] ?? 0.0) - ($beforeSummary['avg_avg_fitness'] ?? 0.0), 4);
        $basinLockDelta = round(($afterSummary['avg_basin_lock_confidence'] ?? 0.0) - ($beforeSummary['avg_basin_lock_confidence'] ?? 0.0), 4);
        $depthDelta = round(($afterSummary['avg_depth_score'] ?? 0.0) - ($beforeSummary['avg_depth_score'] ?? 0.0), 4);
        $bestDeltaWindowDelta = round(($afterSummary['avg_best_delta_window'] ?? 0.0) - ($beforeSummary['avg_best_delta_window'] ?? 0.0), 4);
        $populationTurnoverDelta = round(($afterSummary['avg_population_turnover'] ?? 0.0) - ($beforeSummary['avg_population_turnover'] ?? 0.0), 4);

        return [
            'execution_id' => $execution->id,
            'activation_generation' => $activationMetric->generation,
            'policy' => $this->nullableString($realActivation['policy'] ?? null) ?? 'alns_live',
            'trend_direction' => $this->nullableString($episodeTrend['direction'] ?? null) ?? 'indeterminate',
            'trend_headline' => $this->nullableString($episodeTrend['headline'] ?? null) ?? 'Tendencia indefinida',
            'before_generations' => $beforeSummary['generation_range'],
            'after_generations' => $afterSummary['generation_range'],
            'before_window_size' => count($beforeWindow),
            'after_window_size' => count($afterWindow),
            'best_fitness_delta' => $bestFitnessDelta,
            'avg_fitness_delta' => $avgFitnessDelta,
            'basin_lock_delta' => $basinLockDelta,
            'depth_delta' => $depthDelta,
            'best_delta_window_delta' => $bestDeltaWindowDelta,
            'population_turnover_delta' => $populationTurnoverDelta,
            'impact_label' => $this->resolveImpactLabel(
                bestFitnessDelta: $bestFitnessDelta,
                basinLockDelta: $basinLockDelta,
                depthDelta: $depthDelta,
                bestDeltaWindowDelta: $bestDeltaWindowDelta
            ),
        ];
    }

    /**
     * @param  array<int, ScheduleGenerationMetric>  $window
     * @return array<string, mixed>
     */
    private function summarizeWindow(array $window): array
    {
        $bestFitness = [];
        $avgFitness = [];
        $basinLock = [];
        $depthScore = [];
        $bestDeltaWindow = [];
        $populationTurnover = [];
        $generations = [];

        foreach ($window as $metric) {
            $observation = is_array($metric->landscape_observation) ? $metric->landscape_observation : [];
            $generations[] = (int) $metric->generation;
            $bestFitness[] = (float) $metric->best_fitness;
            $avgFitness[] = (float) $metric->avg_fitness;
            $this->collectNumeric($basinLock, $observation['basin_of_attraction_lock_confidence'] ?? null);
            $this->collectNumeric($depthScore, $observation['depth_score'] ?? null);
            $this->collectNumeric($bestDeltaWindow, $observation['best_delta_window'] ?? null);
            $this->collectNumeric($populationTurnover, $observation['population_turnover'] ?? null);
        }

        return [
            'generation_range' => [
                'from' => $generations !== [] ? min($generations) : null,
                'to' => $generations !== [] ? max($generations) : null,
            ],
            'avg_best_fitness' => $this->average($bestFitness),
            'avg_avg_fitness' => $this->average($avgFitness),
            'avg_basin_lock_confidence' => $this->average($basinLock),
            'avg_depth_score' => $this->average($depthScore),
            'avg_best_delta_window' => $this->average($bestDeltaWindow),
            'avg_population_turnover' => $this->average($populationTurnover),
        ];
    }

    /**
     * @param  array<int, float>  $target
     */
    private function collectNumeric(array &$target, mixed $value): void
    {
        if (is_numeric($value)) {
            $target[] = (float) $value;
        }
    }

    /**
     * @param  array<int, float>  $values
     */
    private function average(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return round(array_sum($values) / count($values), 4);
    }

    private function resolveImpactLabel(
        float $bestFitnessDelta,
        float $basinLockDelta,
        float $depthDelta,
        float $bestDeltaWindowDelta,
    ): string {
        $positiveSignals = 0;
        $negativeSignals = 0;

        if ($bestFitnessDelta > 0) {
            $positiveSignals++;
        } elseif ($bestFitnessDelta < 0) {
            $negativeSignals++;
        }

        if ($basinLockDelta < 0) {
            $positiveSignals++;
        } elseif ($basinLockDelta > 0) {
            $negativeSignals++;
        }

        if ($depthDelta < 0) {
            $positiveSignals++;
        } elseif ($depthDelta > 0) {
            $negativeSignals++;
        }

        if ($bestDeltaWindowDelta > 0) {
            $positiveSignals++;
        } elseif ($bestDeltaWindowDelta < 0) {
            $negativeSignals++;
        }

        if ($positiveSignals >= 3 && $negativeSignals === 0) {
            return 'positive';
        }

        if ($negativeSignals >= 3 && $positiveSignals === 0) {
            return 'negative';
        }

        return 'mixed';
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyTrendBucket(): array
    {
        return [
            'activation_events' => 0,
            'positive_impact_events' => 0,
            'mixed_impact_events' => 0,
            'negative_impact_events' => 0,
            'best_fitness_delta_sum' => 0.0,
            'avg_fitness_delta_sum' => 0.0,
            'basin_lock_delta_sum' => 0.0,
            'depth_delta_sum' => 0.0,
            'best_delta_window_delta_sum' => 0.0,
            'population_turnover_delta_sum' => 0.0,
        ];
    }

    /**
     * @param  array<string, float|int>  $bucket
     * @param  array<string, mixed>  $event
     */
    private function mergeTrendBucket(array &$bucket, array $event): void
    {
        $bucket['activation_events']++;
        $bucket['best_fitness_delta_sum'] += (float) ($event['best_fitness_delta'] ?? 0.0);
        $bucket['avg_fitness_delta_sum'] += (float) ($event['avg_fitness_delta'] ?? 0.0);
        $bucket['basin_lock_delta_sum'] += (float) ($event['basin_lock_delta'] ?? 0.0);
        $bucket['depth_delta_sum'] += (float) ($event['depth_delta'] ?? 0.0);
        $bucket['best_delta_window_delta_sum'] += (float) ($event['best_delta_window_delta'] ?? 0.0);
        $bucket['population_turnover_delta_sum'] += (float) ($event['population_turnover_delta'] ?? 0.0);

        $impactLabel = $event['impact_label'] ?? 'mixed';

        if ($impactLabel === 'positive') {
            $bucket['positive_impact_events']++;
        } elseif ($impactLabel === 'negative') {
            $bucket['negative_impact_events']++;
        } else {
            $bucket['mixed_impact_events']++;
        }
    }

    /**
     * @param  array<string, float|int>  $bucket
     * @return array<string, float|int>
     */
    private function finalizeTrendBucket(array $bucket): array
    {
        $events = (int) $bucket['activation_events'];

        return [
            'activation_events' => $events,
            'positive_impact_events' => (int) $bucket['positive_impact_events'],
            'mixed_impact_events' => (int) $bucket['mixed_impact_events'],
            'negative_impact_events' => (int) $bucket['negative_impact_events'],
            'avg_best_fitness_delta' => $events > 0 ? round(((float) $bucket['best_fitness_delta_sum']) / $events, 4) : 0.0,
            'avg_avg_fitness_delta' => $events > 0 ? round(((float) $bucket['avg_fitness_delta_sum']) / $events, 4) : 0.0,
            'avg_basin_lock_delta' => $events > 0 ? round(((float) $bucket['basin_lock_delta_sum']) / $events, 4) : 0.0,
            'avg_depth_delta' => $events > 0 ? round(((float) $bucket['depth_delta_sum']) / $events, 4) : 0.0,
            'avg_best_delta_window_delta' => $events > 0 ? round(((float) $bucket['best_delta_window_delta_sum']) / $events, 4) : 0.0,
            'avg_population_turnover_delta' => $events > 0 ? round(((float) $bucket['population_turnover_delta_sum']) / $events, 4) : 0.0,
            'positive_impact_rate' => $events > 0 ? round(((int) $bucket['positive_impact_events']) / $events, 4) : 0.0,
        ];
    }

    /**
     * @param  array<string, array<string, float|int>>  $trendComparison
     */
    private function resolveBetterTrendContext(array $trendComparison): ?string
    {
        $worsening = $trendComparison['worsening'] ?? null;
        $improving = $trendComparison['improving'] ?? null;

        if (! is_array($worsening) || ! is_array($improving)) {
            return null;
        }

        if (($improving['avg_best_fitness_delta'] ?? 0.0) > ($worsening['avg_best_fitness_delta'] ?? 0.0)) {
            return 'improving';
        }

        if (($improving['avg_best_fitness_delta'] ?? 0.0) < ($worsening['avg_best_fitness_delta'] ?? 0.0)) {
            return 'worsening';
        }

        if (($improving['avg_basin_lock_delta'] ?? 0.0) < ($worsening['avg_basin_lock_delta'] ?? 0.0)) {
            return 'improving';
        }

        if (($improving['avg_basin_lock_delta'] ?? 0.0) > ($worsening['avg_basin_lock_delta'] ?? 0.0)) {
            return 'worsening';
        }

        if (($improving['positive_impact_rate'] ?? 0.0) > ($worsening['positive_impact_rate'] ?? 0.0)) {
            return 'improving';
        }

        if (($improving['positive_impact_rate'] ?? 0.0) < ($worsening['positive_impact_rate'] ?? 0.0)) {
            return 'worsening';
        }

        return null;
    }

    private function resolveStatus(int $analyzedActivationEvents): string
    {
        if ($analyzedActivationEvents > 0) {
            return 'activation_impact_measured';
        }

        return 'insufficient_activation_history';
    }

    private function resolveHeadline(int $analyzedActivationEvents, int $ignoredEdgeActivationEvents, int $windowSize): string
    {
        if ($analyzedActivationEvents > 0) {
            return sprintf(
                'Impacto temporal medido em %d ativacao(oes) reais do ALNS com janela de %d geracoes antes/depois.',
                $analyzedActivationEvents,
                $windowSize
            );
        }

        if ($ignoredEdgeActivationEvents > 0) {
            return 'Houve ativacoes reais, mas elas ficaram perto demais do inicio ou fim da execucao para comparar janelas completas.';
        }

        return 'Nenhuma ativacao real do ALNS com contexto temporal suficiente foi encontrada na janela historica atual.';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
