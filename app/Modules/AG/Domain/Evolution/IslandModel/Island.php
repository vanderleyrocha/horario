<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use App\Modules\AG\Application\GeneticAlgorithmEngine;
use App\Modules\AG\Domain\Operators\Replacement\ReplacementStrategyInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Support\DateTimeHelper;
use Illuminate\Support\Facades\Log;
use Spatie\Async\Pool;

final class Island
{
    private array $population = [];

    public function __construct(
        private int $islandNum,
        private readonly GeneticAlgorithmEngine $engine,
        private readonly int $populationSize,
        private readonly ReplacementStrategyInterface $replacement,
        private readonly IslandProfile $profile = IslandProfile::Balanced,
    ) {
        $this->engine->setIslandContext($this->islandNum);
    }

    public function initialize(): void
    {
        $this->population = [];

        $elapsed = DateTimeHelper::formatElapsedTime(app('app.start_time'));

        Log::info("Iniciando população da ilha {$this->islandNum} com {$this->populationSize} indivíduos... Tempo decorrido = {$elapsed}");

        $parallelEnabled = (bool) config('ag.initial_population.parallel_construction_enabled', false);

        if ($parallelEnabled && $this->populationSize > 1) {
            $this->initializeParallel();
        } else {
            $this->initializeSerial();
        }

        $elapsed = DateTimeHelper::formatElapsedTime(app('app.start_time'));

        Log::info("População da ilha {$this->islandNum} criada com sucesso! Tempo decorrido =  = {$elapsed}");
    }

    /**
     * Construção serial padrão (preserva aprendizado de nogoods e qualidade gate adaptativo).
     */
    private function initializeSerial(): void
    {
        for ($i = 0; $i < $this->populationSize; $i++) {
            $this->population[] = $this->engine->createIndividual();
        }
    }

    /**
     * Piloto de construção paralela.
     *
     * Estratégia:
     * - Indivíduo 0: construído serialmente para acumular nogoods/aprendizado.
     * - Demais: construídos em paralelo com instâncias isoladas (sem estado compartilhado).
     * - Fallback automático para serial em caso de falha de serialização ou pool.
     */
    private function initializeParallel(): void
    {
        // Primeiro indivíduo sempre serial (preserva nogoods e histórico adaptativo).
        $first = $this->engine->createIndividual();
        $this->population[] = $first;

        $remaining = $this->populationSize - 1;

        if ($remaining <= 0) {
            return;
        }

        $serializedEngine = $this->trySerialize($this->engine);

        if ($serializedEngine === null) {
            Log::warning('ag.island.parallel_construction.fallback_serial', [
                'island' => $this->islandNum,
                'reason' => 'engine_not_serializable',
            ]);

            for ($i = 0; $i < $remaining; $i++) {
                $this->population[] = $this->engine->createIndividual();
            }

            return;
        }

        $concurrency = max(1, (int) config('ag.initial_population.parallel_construction_workers', 4));
        $timeout = max(10, (int) config('ag.initial_population.parallel_construction_timeout_seconds', 60));

        /** @var array<int, Cromossomo> $built */
        $built = [];
        $failedCount = 0;

        try {
            $pool = Pool::create()
                ->concurrency($concurrency)
                ->timeout($timeout)
                ->autoload(base_path('vendor/autoload.php'));

            for ($i = 0; $i < $remaining; $i++) {
                $slot = $i;

                $pool->add(static function () use ($serializedEngine, $slot): array {
                    /** @var GeneticAlgorithmEngine $engine */
                    $engine = unserialize(base64_decode($serializedEngine), ['allowed_classes' => true]);
                    $individual = $engine->createIndividual();

                    return [
                        'slot' => $slot,
                        'individual' => base64_encode(serialize($individual)),
                    ];
                })->then(static function (array $result) use (&$built): void {
                    $individual = unserialize(base64_decode($result['individual']), ['allowed_classes' => true]);

                    if ($individual instanceof Cromossomo) {
                        $built[(int) $result['slot']] = $individual;
                    }
                })->catch(static function () use (&$failedCount): void {
                    $failedCount++;
                });
            }

            $pool->wait();
        } catch (\Throwable $e) {
            Log::warning('ag.island.parallel_construction.pool_error', [
                'island' => $this->islandNum,
                'error' => $e->getMessage(),
            ]);
        }

        // Adiciona os indivíduos construídos em paralelo (slots bem-sucedidos).
        ksort($built);

        foreach ($built as $individual) {
            $this->population[] = $individual;
        }

        // Preenche slots falhos com construção serial de fallback.
        $shortfall = $this->populationSize - count($this->population);

        if ($shortfall > 0) {
            Log::info('ag.island.parallel_construction.fallback_fill', [
                'island' => $this->islandNum,
                'shortfall' => $shortfall,
                'failed_parallel' => $failedCount,
            ]);

            for ($i = 0; $i < $shortfall; $i++) {
                $this->population[] = $this->engine->createIndividual();
            }
        }

        Log::info('ag.island.parallel_construction.completed', [
            'island' => $this->islandNum,
            'population_size' => count($this->population),
            'parallel_built' => count($built),
            'failed_parallel' => $failedCount,
            'serial_fallback' => $shortfall,
        ]);
    }

    private function trySerialize(mixed $value): ?string
    {
        try {
            $serialized = serialize($value);

            return base64_encode($serialized);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 🔧 PRIORIDADE 5: Reinicializa a ilha com presunção de elite.
     * - Mantém os indivíduos de elite (tipicamente 10% da população)
     * - Gera novos indivíduos para completar o tamanho da população (90%)
     *
     * @param Cromossomo[] $elite - Indivíduos a serem preservados
     */
    public function reinitializeWithElite(array $elite): void
    {
        $eliteCount = min(count($elite), $this->populationSize);
        $newIndividualsNeeded = $this->populationSize - $eliteCount;

        Log::info("Reinicializando ilha {$this->islandNum} com elite + novos indivíduos", [
            'elite_count' => $eliteCount,
            'new_individuals' => $newIndividualsNeeded,
            'total_population' => $this->populationSize,
        ]);

        // Começar com a elite
        $this->population = array_slice($elite, 0, $eliteCount);

        // Gerar novos indivíduos para completar a população
        for ($i = 0; $i < $newIndividualsNeeded; $i++) {
            $individual = $this->engine->createIndividual();
            $this->population[] = $individual;
        }

        Log::info("Ilha {$this->islandNum} reinicializada com sucesso", [
            'population_size' => count($this->population),
        ]);
    }

    public function evolveGeneration(): Cromossomo
    {
        $this->population = $this->engine->evolveGeneration($this->population, $this->populationSize);

        return $this->best();
    }

    public function activateStagnationBurst(
        int $generation,
        int $durationGenerations,
        float $mutationMultiplier,
        float $selectionPressureMultiplier,
        bool $forceAlns,
        string $reason,
    ): void {
        $this->engine->activateStagnationBurst(
            generation: $generation,
            durationGenerations: $durationGenerations,
            mutationMultiplier: $mutationMultiplier,
            selectionPressureMultiplier: $selectionPressureMultiplier,
            forceAlns: $forceAlns,
            reason: $reason,
        );
    }

    public function best(): Cromossomo
    {
        usort($this->population, fn ($a, $b) => $b->fitness() <=> $a->fitness());

        return $this->population[0];
    }

    public function population(): array
    {
        return $this->population;
    }

    public function telemetrySnapshot(): array
    {
        return $this->engine->lastEvolutionTelemetry();
    }

    public function currentGeneration(): int
    {
        return $this->engine->currentEvolutionGeneration();
    }

    public function injectIndividual(Cromossomo $individual): void
    {
        $this->replacement->replace($this->population, $individual);
    }

    public function getislandNum(): int
    {
        return $this->islandNum;
    }

    public function profile(): IslandProfile
    {
        return $this->profile;
    }
}
