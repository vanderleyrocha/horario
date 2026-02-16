<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;

final class BlockPreservingCrossover implements CrossoverOperatorInterface
{
    public function crossover(Cromossomo $parent1, Cromossomo $parent2): array
    {
        $child1 = $this->createChild($parent1, $parent2);
        $child2 = $this->createChild($parent2, $parent1);

        return [$child1, $child2];
    }

    private function createChild(Cromossomo $primary, Cromossomo $secondary): Cromossomo
    {
        $genes = [];

        $selectedDays = $this->selectRandomDays($primary);

        // 1️⃣ Preserva blocos inteiros do primary
        foreach ($primary->genes as $gene) {
            if (in_array($gene->diaSemana, $selectedDays)) {
                $genes[] = $gene->copy();
            }
        }

        // 2️⃣ Completa com genes do secondary
        foreach ($secondary->genes as $gene) {

            if (in_array($gene->diaSemana, $selectedDays)) {
                continue;
            }

            $genes[] = $gene->copy();
        }

        return new Cromossomo($genes);
    }

    private function selectRandomDays(Cromossomo $cromossomo): array
    {
        $dias = [];

        foreach ($cromossomo->genes as $gene) {
            $dias[$gene->diaSemana] = true;
        }

        $dias = array_keys($dias);

        shuffle($dias);

        $half = max(1, (int)(count($dias) / 2));

        return array_slice($dias, 0, $half);
    }
}