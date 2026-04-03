<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Horario;
use App\Modules\AG\Application\RunGeneticAlgorithm;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class BenchmarkGeneticSolverAb extends Command
{
    protected $signature = 'solver:benchmark-ab
        {--small-id= : Horario ID para cenario pequeno}
        {--medium-id= : Horario ID para cenario medio com constraints}
        {--heavy-id= : Horario ID para cenario pesado}
        {--repeat=1 : Quantidade de repeticoes por variante em cada cenario}
        {--variant-a-custom-repair=1 : Habilita repair customizado na variante A (1/0)}
        {--variant-b-custom-repair=0 : Habilita repair customizado na variante B (1/0)}
        {--json : Exibe tambem o resultado em JSON}';

    protected $description = 'Executa benchmark A/B reproduzivel por cenario (pequeno, medio com constraints, pesado) e compara fases inicial/evolucao/ALNS/repair/persistencia.';

    public function handle(): int
    {
        $repeat = max(1, (int) $this->option('repeat'));
        $scenarioMap = $this->resolveScenarioMap();

        if ($scenarioMap === []) {
            $this->error('Nao foi possivel resolver cenarios para benchmark. Informe --small-id/--medium-id/--heavy-id ou cadastre horarios suficientes.');

            return self::FAILURE;
        }

        $variantMap = [
            'A' => [
                'ag.initial_population.custom_constraint_repair_extension_enabled' => $this->parseBoolOption('variant-a-custom-repair', true),
            ],
            'B' => [
                'ag.initial_population.custom_constraint_repair_extension_enabled' => $this->parseBoolOption('variant-b-custom-repair', false),
            ],
        ];

        $this->info('Benchmark A/B do solver iniciado.');
        $this->line('Variantes:');
        $this->line(sprintf('  A: custom_constraint_repair=%s', $variantMap['A']['ag.initial_population.custom_constraint_repair_extension_enabled'] ? 'on' : 'off'));
        $this->line(sprintf('  B: custom_constraint_repair=%s', $variantMap['B']['ag.initial_population.custom_constraint_repair_extension_enabled'] ? 'on' : 'off'));
        $this->newLine();

        $results = [];

        foreach ($scenarioMap as $scenarioKey => $horario) {
            $this->line(sprintf('Cenario %s (horario_id=%d, aulas=%d, constraints_ativas=%d)', $scenarioKey, $horario->id, (int) ($horario->aulas_count ?? 0), (int) ($horario->active_constraints_count ?? 0)));

            foreach ($variantMap as $variantKey => $overrides) {
                for ($run = 1; $run <= $repeat; $run++) {
                    $measurement = $this->runSingleBenchmark($horario, $overrides);
                    $results[] = [
                        'scenario' => $scenarioKey,
                        'variant' => $variantKey,
                        'run' => $run,
                        'horario_id' => $horario->id,
                        'metrics' => $measurement,
                    ];

                    $this->line(sprintf(
                        '  %s.%d total=%dms init=%dms evo=%dms alns=%dms repair=%dms persist=%dms best=%.4f',
                        $variantKey,
                        $run,
                        $measurement['total_ms'],
                        $measurement['initial_population_ms'],
                        $measurement['evolution_ms'],
                        $measurement['alns_ms'],
                        $measurement['repair_ms'],
                        $measurement['persist_ms'],
                        $measurement['best_fitness'],
                    ));
                }
            }

            $this->newLine();
        }

        $summary = $this->summarizeResults($results);
        $this->renderSummaryTable($summary);

        if ((bool) $this->option('json')) {
            $this->newLine();
            $this->line(json_encode([
                'variants' => $variantMap,
                'results' => $results,
                'summary' => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->info('Benchmark A/B finalizado.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, Horario>
     */
    private function resolveScenarioMap(): array
    {
        $query = Horario::query()
            ->withCount([
                'aulas',
                'scheduleConstraints as active_constraints_count' => static function ($builder): void {
                    $builder->where('is_active', true);
                },
            ]);

        $all = $query->get();

        if ($all->isEmpty()) {
            return [];
        }

        $byId = $all->keyBy('id');

        $smallId = $this->option('small-id');
        $mediumId = $this->option('medium-id');
        $heavyId = $this->option('heavy-id');

        $small = $smallId !== null ? $byId->get((int) $smallId) : null;
        $medium = $mediumId !== null ? $byId->get((int) $mediumId) : null;
        $heavy = $heavyId !== null ? $byId->get((int) $heavyId) : null;

        $small ??= $all->sortBy(fn (Horario $h) => [
            (int) ($h->aulas_count ?? 0),
            (int) ($h->active_constraints_count ?? 0),
        ])->first();

        $withConstraints = $all->filter(static fn (Horario $h): bool => (int) ($h->active_constraints_count ?? 0) > 0);

        $medium ??= $withConstraints
            ->sortBy(fn (Horario $h) => abs((int) ($h->aulas_count ?? 0) - 30))
            ->first();

        $medium ??= $all->sortBy(fn (Horario $h) => abs((int) ($h->aulas_count ?? 0) - 30))->first();

        $heavy ??= $all->sortByDesc(fn (Horario $h) => ((int) ($h->aulas_count ?? 0) * 10) + ((int) ($h->active_constraints_count ?? 0) * 5))->first();

        $scenarios = [];

        if ($small instanceof Horario) {
            $scenarios['small'] = $small;
        }

        if ($medium instanceof Horario) {
            $scenarios['medium_constraints'] = $medium;
        }

        if ($heavy instanceof Horario) {
            $scenarios['heavy'] = $heavy;
        }

        return $scenarios;
    }

    /**
     * @param array<string, mixed> $configOverrides
     * @return array<string, int|float|string|bool|null>
     */
    private function runSingleBenchmark(Horario $horario, array $configOverrides): array
    {
        $runner = app(RunGeneticAlgorithm::class);

        $collector = new class () implements ProgressReporterInterface {
            /** @var array<string, array{first:float,last:float,count:int}> */
            public array $phaseWindows = [];

            /** @var array<string, array{first:float,last:float,count:int}> */
            public array $channelWindows = [];

            public ?float $lastProgressAt = null;

            /** @var array<string, mixed> */
            public array $lastPayload = [];

            public function report(array $data): void
            {
                $now = microtime(true);
                $this->lastProgressAt = $now;
                $this->lastPayload = $data;

                $phase = (string) ($data['phase'] ?? 'unknown');
                $this->touchWindow($this->phaseWindows, $phase, $now);

                $stage = strtolower((string) ($data['stage'] ?? ''));

                if (str_contains($stage, 'repair')) {
                    $this->touchWindow($this->channelWindows, 'repair', $now);
                }

                if (
                    array_key_exists('alns_triggered', $data)
                    || array_key_exists('alns_destroy_operator', $data)
                    || array_key_exists('alns_repair_operator', $data)
                    || $phase === 'alns_intensification'
                ) {
                    $this->touchWindow($this->channelWindows, 'alns', $now);
                }
            }

            /**
             * @param array<string, array{first:float,last:float,count:int}> $windows
             */
            private function touchWindow(array &$windows, string $key, float $now): void
            {
                if (! isset($windows[$key])) {
                    $windows[$key] = ['first' => $now, 'last' => $now, 'count' => 1];

                    return;
                }

                $windows[$key]['last'] = $now;
                $windows[$key]['count']++;
            }
        };

        $nullMetricsRecorder = new class () extends ExecutionMetricsRecorder {
            private ?int $executionId = null;

            private ?int $horarioId = null;

            public function startExecution(
                int $horarioId,
                int $populationSize,
                int $generations,
                array $parameters,
                ?int $executionId = null,
                array $statusContext = [],
            ): int {
                $this->horarioId = $horarioId;
                $this->executionId = $executionId ?? 1;

                return $this->executionId;
            }

            public function recordGeneration(\App\Modules\AG\Domain\Metrics\DTO\GenerationMetrics $metrics): void
            {
            }

            public function flush(): void
            {
            }

            public function finishExecution(float $bestFitness, array $statusContext = []): void
            {
            }

            public function failExecution(array $statusContext = []): void
            {
            }

            public function cancelExecution(array $statusContext = []): void
            {
            }

            public function touchExecution(): void
            {
            }

            public function hasExecutionId(): bool
            {
                return $this->executionId !== null;
            }

            public function getExecutionId(): int
            {
                return $this->executionId ?? 1;
            }

            public function getHorarioId(): ?int
            {
                return $this->horarioId;
            }
        };

        $originalConfig = [];

        foreach ($configOverrides as $key => $value) {
            $originalConfig[$key] = config($key);
            config([$key => $value]);
        }

        $startedAt = microtime(true);

        try {
            $result = $runner->execute($horario, $collector, $nullMetricsRecorder);
        } finally {
            foreach ($originalConfig as $key => $value) {
                config([$key => $value]);
            }
        }

        $endedAt = microtime(true);

        $totalMs = (int) round(max(0, $endedAt - $startedAt) * 1000);
        $lastProgressAt = $collector->lastProgressAt ?? $endedAt;
        $persistMs = max(0, (int) round(($endedAt - $lastProgressAt) * 1000));

        $initialPopulationMs = $this->windowDurationMs($collector->phaseWindows, 'initial_population');
        $evolutionMs = $this->windowDurationMs($collector->phaseWindows, 'evolution')
            + $this->windowDurationMs($collector->phaseWindows, 'evolving');
        $alnsMs = $this->windowDurationMs($collector->channelWindows, 'alns');
        $repairMs = $this->windowDurationMs($collector->channelWindows, 'repair');

        $bottlenecks = is_array($collector->lastPayload['initial_population_bottlenecks'] ?? null)
            ? $collector->lastPayload['initial_population_bottlenecks']
            : [];

        return [
            'total_ms' => $totalMs,
            'initial_population_ms' => $initialPopulationMs,
            'evolution_ms' => $evolutionMs,
            'alns_ms' => $alnsMs,
            'repair_ms' => $repairMs,
            'persist_ms' => $persistMs,
            'best_fitness' => (float) ($result['best_fitness'] ?? 0.0),
            'quality_gate_rejections' => (int) ($bottlenecks['quality_gate_rejections'] ?? 0),
            'fail_fast_count' => (int) ($bottlenecks['fail_fast_count'] ?? 0),
            'phase_payload_count' => count($collector->phaseWindows),
            'custom_constraint_repair_enabled' => (bool) ($configOverrides['ag.initial_population.custom_constraint_repair_extension_enabled'] ?? true),
        ];
    }

    /**
     * @param array<string, array{first:float,last:float,count:int}> $windows
     */
    private function windowDurationMs(array $windows, string $key): int
    {
        $window = $windows[$key] ?? null;

        if (! is_array($window)) {
            return 0;
        }

        return (int) round(max(0, ($window['last'] - $window['first'])) * 1000);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function summarizeResults(array $results): array
    {
        $grouped = [];

        foreach ($results as $entry) {
            $key = $entry['scenario'] . '|' . $entry['variant'];
            $grouped[$key][] = $entry['metrics'];
        }

        $summary = [];

        foreach ($grouped as $groupKey => $metricsList) {
            [$scenario, $variant] = explode('|', $groupKey, 2);

            $summary[] = [
                'scenario' => $scenario,
                'variant' => $variant,
                'runs' => count($metricsList),
                'avg_total_ms' => $this->average($metricsList, 'total_ms'),
                'avg_initial_ms' => $this->average($metricsList, 'initial_population_ms'),
                'avg_evolution_ms' => $this->average($metricsList, 'evolution_ms'),
                'avg_alns_ms' => $this->average($metricsList, 'alns_ms'),
                'avg_repair_ms' => $this->average($metricsList, 'repair_ms'),
                'avg_persist_ms' => $this->average($metricsList, 'persist_ms'),
                'avg_best_fitness' => $this->average($metricsList, 'best_fitness'),
                'avg_quality_gate_rejections' => $this->average($metricsList, 'quality_gate_rejections'),
            ];
        }

        usort($summary, static fn (array $a, array $b): int => [$a['scenario'], $a['variant']] <=> [$b['scenario'], $b['variant']]);

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function average(array $rows, string $key): float
    {
        if ($rows === []) {
            return 0.0;
        }

        $values = array_map(static fn (array $row): float => (float) Arr::get($row, $key, 0.0), $rows);

        return round(array_sum($values) / count($values), 4);
    }

    /**
     * @param array<int, array<string, mixed>> $summary
     */
    private function renderSummaryTable(array $summary): void
    {
        $this->table(
            ['Scenario', 'Variant', 'Runs', 'Avg Total (ms)', 'Avg Init (ms)', 'Avg Evo (ms)', 'Avg ALNS (ms)', 'Avg Repair (ms)', 'Avg Persist (ms)', 'Avg Best Fitness', 'Avg QG Rejects'],
            array_map(static fn (array $row): array => [
                $row['scenario'],
                $row['variant'],
                $row['runs'],
                $row['avg_total_ms'],
                $row['avg_initial_ms'],
                $row['avg_evolution_ms'],
                $row['avg_alns_ms'],
                $row['avg_repair_ms'],
                $row['avg_persist_ms'],
                $row['avg_best_fitness'],
                $row['avg_quality_gate_rejections'],
            ], $summary),
        );
    }

    private function parseBoolOption(string $name, bool $default): bool
    {
        $value = $this->option($name);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true);
    }
}
