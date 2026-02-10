<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use Illuminate\Support\Collection;

class GeneSwapMutation implements MutationOperatorInterface
{
    private GeneticAlgorithmConfigDTO $configAG;

    public function __construct()
    {
        // O configAG será setado via setContext()
    }

    public function setContext(GeneticAlgorithmConfigDTO $configAG): void
    {
        $this->configAG = $configAG;
    }

    public function mutate(Cromossomo $cromossomo, float $mutationRate): void
    {
        if (!isset($this->configAG)) {
            throw new \Exception("GeneSwapMutation context not set. Call setContext() before mutate().");
        }

        if ($cromossomo->genes->isEmpty()) {
            return;
        }

        if (mt_rand() / mt_getrandmax() < $mutationRate) {
            // Seleciona um gene aleatoriamente para mutar
            $geneToMutate = $cromossomo->genes->random();

            // Encontra um novo slot de tempo aleatório
            $horariosDisponiveis = $this->configAG->horariosDisponiveis;
            if (empty($horariosDisponiveis)) {
                return;
            }
            $novoHorario = $horariosDisponiveis[array_rand($horariosDisponiveis)];

            // Aplica a mutação: altera o dia e tempo do gene
            $geneToMutate->diaSemana = $novoHorario['dia'];
            $geneToMutate->periodoDia = $novoHorario['tempo'];
        }
    }
}
