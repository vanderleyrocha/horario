<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use Illuminate\Support\Collection;

interface SelectionOperatorInterface
{
    /**
     * Seleciona os pais da população para a próxima geração.
     * @param Collection<int, Cromossomo> $population
     * @param int $selectionSize
     * @return Collection<int, Cromossomo>
     */
    public function select(Collection $population, int $selectionSize): Collection;

    /**
     * Retorna os melhores cromossomos da população (elitismo).
     * @param Collection<int, Cromossomo> $population
     * @param int $elitismCount
     * @return Collection<int, Cromossomo>
     */
    public function getElites(Collection $population, int $elitismCount): Collection;
}
