<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;

final class StructuredSwapMutation implements MutationOperatorInterface
{
    public function mutate(Cromossomo $cromossomo): void
    {

        $this->swapByDay($cromossomo);
    }

    private function swapByDay(Cromossomo $cromossomo): void
    {
        if (count($cromossomo->genes) < 2) {
            return;
        }

        $dias = [];

        foreach ($cromossomo->genes as $gene) {
            $dias[$gene->diaSemana] = true;
        }

        $dias = array_keys($dias);

        if (empty($dias)) {
            return;
        }

        $diaEscolhido = $dias[array_rand($dias)];

        $indices = [];

        foreach ($cromossomo->genes as $index => $gene) {
            if ($gene->diaSemana === $diaEscolhido) {
                $indices[] = $index;
            }
        }

        if (count($indices) < 2) {
            return;
        }

        $i1 = $indices[array_rand($indices)];
        $i2 = $indices[array_rand($indices)];

        if ($i1 === $i2) {
            return;
        }

        $g1 = $cromossomo->genes[$i1];
        $g2 = $cromossomo->genes[$i2];

        // Criar NOVOS genes (imutabilidade preservada)
        $newGene1 = new Gene(
            aulaId: $g1->aulaId,
            professorId: $g1->professorId,
            turmaId: $g1->turmaId,
            disciplinaId: $g1->disciplinaId,
            diaSemana: $g2->diaSemana,
            periodoDia: $g2->periodoDia,
            duracaoTempos: $g1->duracaoTempos
        );

        $newGene2 = new Gene(
            aulaId: $g2->aulaId,
            professorId: $g2->professorId,
            turmaId: $g2->turmaId,
            disciplinaId: $g2->disciplinaId,
            diaSemana: $g1->diaSemana,
            periodoDia: $g1->periodoDia,
            duracaoTempos: $g2->duracaoTempos
        );

        $cromossomo->replaceGene($i1, $newGene1);
        $cromossomo->replaceGene($i2, $newGene2);
    }
}