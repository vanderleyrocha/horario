<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use App\Modules\AG\Application\GeneticAlgorithmEngine;
use App\Modules\AG\Domain\Operators\Replacement\ReplacementStrategyInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Support\DateTimeHelper;
use Illuminate\Support\Facades\Log;

final class Island
{
    private array $population = [];

    public function __construct(private int $islandNum, private readonly GeneticAlgorithmEngine $engine, private readonly int $populationSize, private readonly ReplacementStrategyInterface $replacement)
    {
        $this->engine->setIslandContext($this->islandNum);
    }

    public function initialize(): void
    {
        $this->population = [];

        $elapsed = DateTimeHelper::formatElapsedTime(app('app.start_time'));

        Log::info("Iniciando população da ilha {$this->islandNum} com {$this->populationSize} indivíduos... Tempo decorrido = {$elapsed}");

        for ($i = 0; $i < $this->populationSize; $i++) {

            $individual = $this->engine->createIndividual();

            $this->population[] = $individual;
        }

        $elapsed = DateTimeHelper::formatElapsedTime(app('app.start_time'));

        Log::info("População da ilha {$this->islandNum} criada com sucesso! Tempo decorrido =  = {$elapsed}");
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
}
