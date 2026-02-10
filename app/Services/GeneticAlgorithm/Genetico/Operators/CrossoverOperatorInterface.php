<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use Illuminate\Support\Collection;

interface CrossoverOperatorInterface
{
    /**
     * Realiza o cruzamento entre dois cromossomos pais para gerar descendentes.
     * @return Collection<int, Cromossomo>
     */
    public function crossover(Cromossomo $parent1, Cromossomo $parent2): Collection;
}
