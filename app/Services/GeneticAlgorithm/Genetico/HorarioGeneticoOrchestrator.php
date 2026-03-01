<?php

declare(strict_types=1);

namespace App\Services\GeneticAlgorithm\Genetico;

use App\Services\GeneticAlgorithm\Genetico\Repair\GreedyRepairOperator;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;
use App\Services\GeneticAlgorithm\Genetico\Fitness\FitnessEvaluator;
use App\Services\GeneticAlgorithm\Genetico\Operators\SelectionOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Operators\CrossoverOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Operators\MutationOperatorInterface;
use App\Services\GeneticAlgorithm\Genetico\Termination\TerminationCriterionInterface;
use App\Services\GeneticAlgorithm\Genetico\Metrics\MetricsRecorder;
use Closure;
use Illuminate\Support\Facades\Log;

final class HorarioGeneticoOrchestrator {
    public function __construct(
        private SelectionOperatorInterface $selectionOperator,
        private CrossoverOperatorInterface $crossoverOperator,
        private MutationOperatorInterface $mutationOperator,
        private GreedyRepairOperator $repairOperator
    ) {
    }

    public function gerar(
        GeneticAlgorithmConfigDTO $config,
        PopulationGenerator $populationGenerator,
        FitnessEvaluator $fitnessEvaluator,
        TerminationCriterionInterface $terminationCriterion,
        MetricsRecorder $metricsRecorder,
        array $evaluationData,
        ?Closure $progressCallback = null
    ): array {

        $population = $populationGenerator->generate();
        $generation = 0;

        while (true) {

            if ($progressCallback) {
                $progressCallback(fase: 'evolucao', atual: $generation, total: $config->numeroGeracoes);
            }

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

            $generation++;

            $population = $this->evoluir($population, $config);
        }

        return [
            "cromossomo" => $metricsRecorder->getBestCromossomoOverall() ?? $population[0] ?? null,
            "generation" => $generation
        ];
    }

    private function evoluir(array $population, GeneticAlgorithmConfigDTO $config): array {
        $newPopulation = [];
        $elites = $this->selectionOperator->getElites($population, $config->elitismCount);

        foreach ($elites as $elite) {
            $newPopulation[] = $elite->copy();
        }

        while (count($newPopulation) < $config->tamanhoPopulacao) {

            [$parent1, $parent2] = $this->selectionOperator->select($population, 2);

            if (mt_rand() / mt_getrandmax() < $config->taxaCrossover) {
                [$child1, $child2] = $this->crossoverOperator->crossover($parent1, $parent2);
            } else {
                $child1 = $parent1->copy();
                $child2 = $parent2->copy();
            }

            if (mt_rand() / mt_getrandmax() < $config->taxaMutacao) {
                $this->mutationOperator->mutate($child1);
            }

            if (mt_rand() / mt_getrandmax() < $config->taxaMutacao) {
                $this->mutationOperator->mutate($child2);
            }

            // 🔥 Repair imediato
            $this->repairOperator->repair($child1, $config->horariosDisponiveis, $config->aulasPorDia);

            $this->repairOperator->repair($child2, $config->horariosDisponiveis, $config->aulasPorDia);

            $newPopulation[] = $child1;

            if (count($newPopulation) < $config->tamanhoPopulacao) {
                $newPopulation[] = $child2;
            }
        }

        return $newPopulation;
    }
}
