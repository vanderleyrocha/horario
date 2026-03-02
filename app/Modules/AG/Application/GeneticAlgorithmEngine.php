<?php

namespace App\Modules\AG\Application;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Operators\SelectionOperatorInterface;
use App\Modules\AG\Domain\Operators\CrossoverOperatorInterface;
use App\Modules\AG\Domain\Operators\MutationOperatorInterface;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Termination\TerminationCriterionInterface;
use App\Modules\AG\Domain\Metrics\MetricsRecorder;
use App\Modules\AG\Support\DTO\GeneticAlgorithmConfigDTO;

final class GeneticAlgorithmEngine {
    private int $generationsWithoutImprovement = 0;
    private float $bestFitnessEver = -INF;
    private float $currentMutationRate;

    public function __construct(
        private readonly FitnessEvaluator $fitnessEvaluator,
        private readonly SelectionOperatorInterface $selectionOperator,
        private readonly CrossoverOperatorInterface $crossoverOperator,
        private readonly MutationOperatorInterface $mutationOperator,
        private readonly TerminationCriterionInterface $terminationCriterion,
        private readonly ?GreedyRepairOperator $repairOperator = null,
        private readonly ?MetricsRecorder $metricsRecorder = null,
        private readonly ?ProgressReporterInterface $progressReporter = null,
    ) {
    }

    /**
     * @param Cromossomo[] $initialPopulation
     */
    public function run(
        array $initialPopulation,
        GeneticAlgorithmConfigDTO $config
    ): Cromossomo {

        if (empty($initialPopulation)) {
            throw new \RuntimeException('População inicial vazia.');
        }

        $population = $initialPopulation;
        $generation = 0;
        $this->currentMutationRate = $config->taxaMutacao;

        $this->reportProgress('executando', 0, 0, 0.0);

        while (true) {

            /* ============================================================
             | 1️⃣ Avaliação (MAXIMIZAÇÃO)
             ============================================================ */

            foreach ($population as $individual) {
                $this->fitnessEvaluator->evaluate($individual);
            }

            usort(
                $population,
                fn($a, $b) => $b->fitness() <=> $a->fitness()
            );

            $bestCurrent = $population[0];

            /* ============================================================
             | 2️⃣ Métricas
             ============================================================ */

            $this->metricsRecorder?->record(
                $generation,
                $population
            );

            /* ============================================================
             | 3️⃣ Critério de parada (ALINHADO À INTERFACE)
             ============================================================ */

            if ($this->terminationCriterion->shouldTerminate(
                $generation,
                $population
            )) {
                break;
            }

            /* ============================================================
             | 4️⃣ Mutação adaptativa
             ============================================================ */

            $this->updateAdaptiveMutation(
                $bestCurrent->fitness(),
                $config
            );

            /* ============================================================
             | 5️⃣ Progresso
             ============================================================ */

            $percentual = min(
                100,
                (int) round(
                    ($generation / max(1, $config->numeroGeracoes)) * 100
                )
            );

            $this->reportProgress(
                'executando',
                $generation,
                $percentual,
                $bestCurrent->fitness()
            );

            /* ============================================================
             | 6️⃣ Elitismo
             ============================================================ */

            $eliteCount = $config->eliteCount();

            $newPopulation = array_slice(
                $population,
                0,
                $eliteCount
            );

            /* ============================================================
             | 7️⃣ Evolução
             ============================================================ */

            while (count($newPopulation) < $config->tamanhoPopulacao) {

                $parents = $this->selectionOperator->select(
                    $population,
                    2
                );

                [$childA, $childB] =
                    $this->crossoverOperator->crossover(
                        $parents[0],
                        $parents[1]
                    );

                foreach ([$childA, $childB] as $child) {

                    if (
                        mt_rand() / mt_getrandmax()
                        <= $this->currentMutationRate
                    ) {
                        $child = $this->mutationOperator->mutate($child);
                    }

                    if ($this->repairOperator) {
                        $child = $this->repairOperator->repair($child);
                    }

                    $newPopulation[] = $child;

                    if (
                        count($newPopulation)
                        >= $config->tamanhoPopulacao
                    ) {
                        break;
                    }
                }
            }

            $population = $newPopulation;
            $generation++;
        }

        usort(
            $population,
            fn($a, $b) => $b->fitness() <=> $a->fitness()
        );

        $best = $population[0];

        $this->reportProgress(
            'concluido',
            $generation,
            100,
            $best->fitness()
        );

        return $best;
    }

    /* ============================================================
     |  MUTAÇÃO ADAPTATIVA (MAXIMIZAÇÃO)
     ============================================================ */

    private function updateAdaptiveMutation(
        float $currentFitness,
        GeneticAlgorithmConfigDTO $config
    ): void {

        if ($currentFitness > $this->bestFitnessEver) {

            $this->bestFitnessEver = $currentFitness;
            $this->generationsWithoutImprovement = 0;
        } else {

            $this->generationsWithoutImprovement++;
        }

        if (
            $this->generationsWithoutImprovement
            > $config->limiteEstagnacao
        ) {

            $this->currentMutationRate = min(
                $config->taxaMutacaoMax,
                $this->currentMutationRate * 1.5
            );
        } else {

            $this->currentMutationRate = max(
                $config->taxaMutacaoMin,
                $this->currentMutationRate * 0.98
            );
        }
    }

    /* ============================================================
     |  PROGRESSO
     ============================================================ */

    private function reportProgress(
        string $status,
        int $generation,
        int $percentual,
        float $bestFitness
    ): void {

        $this->progressReporter?->report([
            'status' => $status,
            'geracao' => $generation,
            'percentual' => $percentual,
            'melhor_fitness' => round($bestFitness, 4),
        ]);
    }
}
