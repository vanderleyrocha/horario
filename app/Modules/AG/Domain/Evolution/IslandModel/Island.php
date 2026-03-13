<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Evolution\IslandModel;

use App\Helpers\DateTimeHelper;
use App\Modules\AG\Application\GeneticAlgorithmEngine;
use App\Modules\AG\Domain\Operators\Replacement\ReplacementStrategyInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use Illuminate\Support\Facades\Log;

final class Island
{
    private array $population = [];

    public function __construct(private int $islandNum, private readonly GeneticAlgorithmEngine $engine, private readonly int $populationSize, private readonly ReplacementStrategyInterface $replacement)
    {
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

    public function injectIndividual(Cromossomo $individual): void
    {
        $this->replacement->replace($this->population, $individual);
    }

    public function getislandNum(): int
    {
        return $this->islandNum;
    }
}
