<?php

declare(strict_types=1);

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessEvaluator;
use App\Services\GeneticAlgorithm\Genetico\Operators\SelectionOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Operators\CrossoverOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Operators\MutationOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Termination\TerminationCriterionInterface;
use App\Services\GeneticAlgorithm\Genetico\Metrics\MetricsRecorder;

final class HorarioGeneticoOrchestrator {
    public function __construct(
        private SelectionOperatorInterface $selectionOperator,
        private CrossoverOperatorInterface $crossoverOperator,
        private MutationOperatorInterface $mutationOperator,
    ) {
    }

    public function gerar(
        GeneticAlgorithmConfigDTO $config,
        PopulationGenerator $populationGenerator,
        FitnessEvaluator $fitnessEvaluator,
        TerminationCriterionInterface $terminationCriterion,
        MetricsRecorder $metricsRecorder,
        array $evaluationData
    ): array {

 
        $population = $populationGenerator->generate();

        $generation = 0;

        while (true) {

            foreach ($population as $cromossomo) {

                $context = new EvaluationContext(
                    genes: $cromossomo->getGenes(),
                    restricoesIndexadas: $evaluationData['restricoesIndexadas'],
                    cargaEsperada: $evaluationData['cargaEsperada'],
                    diasPreferidos: $evaluationData['diasPreferidos'],
                    temposPreferidos: $evaluationData['temposPreferidos']
                );

                $fitnessEvaluator->evaluate($cromossomo, $context);
            }

            usort($population, fn(Cromossomo $a, Cromossomo $b) => $a->getFitness() <=> $b->getFitness());

            $metricsRecorder->recordGeneration($generation, $population);

            if ($terminationCriterion->shouldTerminate($population, $generation, $metricsRecorder->getBestCromossomoOverall())) {
                break;
            }

            $population = $this->evoluir($population, $config);

            $generation++;
        }

        return ["cromossomo" => $metricsRecorder->getBestCromossomoOverall() ?? $population[0], "generation" => $generation];
    }


    private function evoluir(array $population, GeneticAlgorithmConfigDTO $config): array {
        $newPopulation = [];

        // ✅ CORRETO: usa getElites()
        $elites = $this->selectionOperator->getElites($population, $config->elitismCount);

        foreach ($elites as $elite) {
            $newPopulation[] = $elite->copy();
        }

        while (count($newPopulation) < $config->tamanhoPopulacao) {

            $parents = $this->selectionOperator->select($population, 2);

            $parent1 = $parents[0];
            $parent2 = $parents[1];

            if ($this->randomFloat() < $config->taxaCrossover) {
                [$child1, $child2] =  $this->crossoverOperator->crossover($parent1, $parent2);
            } else {

                $child1 = $parent1->copy();
                $child2 = $parent2->copy();
            }

            if ($this->randomFloat() < $config->taxaMutacao) {
                $this->mutationOperator->mutate($child1);
            }

            if ($this->randomFloat() < $config->taxaMutacao) {
                $this->mutationOperator->mutate($child2);
            }

            $newPopulation[] = $child1;

            if (count($newPopulation) < $config->tamanhoPopulacao) {
                $newPopulation[] = $child2;
            }
        }

        return $newPopulation;
    }

    private function randomFloat(): float {
        return mt_rand() / mt_getrandmax();
    }
}
