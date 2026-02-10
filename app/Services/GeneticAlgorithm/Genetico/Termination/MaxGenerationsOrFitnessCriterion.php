<?php

namespace App\Services\GeneticAlgorithm\Genetico\Termination;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use Illuminate\Support\Collection;

class MaxGenerationsOrFitnessCriterion implements TerminationCriterionInterface {
    private GeneticAlgorithmConfigDTO $configAG;
    private int $generationsWithoutImprovement = 0;
    private float $lastBestFitness = -INF; // Usar -INF para garantir que qualquer score seja maior

    public function __construct() {
        // O configAG será setado via setContext()
    }

    public function setContext(GeneticAlgorithmConfigDTO $configAG): void {
        $this->configAG = $configAG;
        // Resetar estado ao definir novo contexto
        $this->generationsWithoutImprovement = 0;
        $this->lastBestFitness = -INF;
    }

    public function shouldTerminate(Collection $population, int $currentGeneration, ?Cromossomo $bestCromossomoOverall): bool {
        if (!isset($this->configAG)) {
            throw new \Exception("MaxGenerationsOrFitnessCriterion context not set. Call setContext() before shouldTerminate().");
        }

        // Critério 1: Número máximo de gerações
        if ($currentGeneration >= $this->configAG->numeroGeracoes) {
            return true;
        }

        // Critério 2: Fitness satisfatório
        if ($bestCromossomoOverall && $bestCromossomoOverall->getFitnessScore() >= $this->configAG->targetFitness) {
            return true;
        }

        // Critério 3: Convergência (gerações sem melhoria)
        if ($bestCromossomoOverall) {
            if ($bestCromossomoOverall->getFitnessScore() > $this->lastBestFitness) {
                $this->lastBestFitness = $bestCromossomoOverall->getFitnessScore();
                $this->generationsWithoutImprovement = 0;
            } else {
                $this->generationsWithoutImprovement++;
            }
        }

        if ($this->generationsWithoutImprovement >= $this->configAG->maxGenerationsWithoutImprovement) {
            return true;
        }

        return false;
    }

    public function getGenerationsWithoutImprovement(): int {
        return $this->generationsWithoutImprovement;
    }
}
