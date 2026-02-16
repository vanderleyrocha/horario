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
    ): Cromossomo {
        // ✅ CORRETO: passa tamanho da população
        $population = $this->populationGenerator->generate(
            $this->config->tamanhoPopulacao
        );

        $generation = 0;

        while (true) {

            $this->avaliarPopulacao($population);

            usort(
                $population,
                fn(Cromossomo $a, Cromossomo $b)
                => $a->getFitness() <=> $b->getFitness()
            );

            // ✅ Compatível com MetricsRecorder
            $this->metricsRecorder->recordGeneration(
                $generation,
                $population
            );

            // ✅ Compatível com TerminationCriterion
            if ($this->terminationCriterion->shouldTerminate(
                $population,
                $generation,
                $this->metricsRecorder->getBestCromossomoOverall()
            )) {
                break;
            }

            $population = $this->evoluir($population);

            $generation++;
        }

        return $this->metricsRecorder->getBestCromossomoOverall()
            ?? $population[0];
    }

    /*
    |--------------------------------------------------------------------------
    | Avaliação
    |--------------------------------------------------------------------------
    */

    private function avaliarPopulacao(array $population): void {
        foreach ($population as $chromosome) {

            $context = new EvaluationContext(
                $chromosome,
                ...$this->evaluationData
            );

            // ✅ CORRETO: passa cromossomo e contexto
            $this->fitnessEvaluator->evaluate(
                $chromosome,
                $context
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Evolução
    |--------------------------------------------------------------------------
    */

    private function evoluir(array $population): array {
        $newPopulation = [];

        // ✅ CORRETO: usa getElites()
        $elites = $this->selectionOperator->getElites(
            $population,
            $this->config->elitismCount
        );

        foreach ($elites as $elite) {
            $newPopulation[] = $elite->copy();
        }

        while (count($newPopulation) < $this->config->tamanhoPopulacao) {

            // ✅ CORRETO: select retorna array
            $parents = $this->selectionOperator->select($population, 2);

            $parent1 = $parents[0];
            $parent2 = $parents[1];

            if ($this->randomFloat() < $this->config->taxaCrossover) {

                [$child1, $child2] =
                    $this->crossoverOperator->crossover($parent1, $parent2);
            } else {

                $child1 = $parent1->copy();
                $child2 = $parent2->copy();
            }

            if ($this->randomFloat() < $this->config->taxaMutacao) {
                $this->mutationOperator->mutate($child1);
            }

            if ($this->randomFloat() < $this->config->taxaMutacao) {
                $this->mutationOperator->mutate($child2);
            }

            $newPopulation[] = $child1;

            if (count($newPopulation) < $this->config->tamanhoPopulacao) {
                $newPopulation[] = $child2;
            }
        }

        return $newPopulation;
    }

    private function randomFloat(): float {
        return mt_rand() / mt_getrandmax();
    }
}
