<?php

namespace App\Modules\AG\Domain\ConflictGraph;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class ConflictGraphBuilder
{
    public function build(Cromossomo $c): ConflictGraph
    {
        $graph = new ConflictGraph;

        foreach ($c->genes() as $gene) {
            $graph->addNode($gene->id);
        }

        foreach ($c->genes() as $geneA) {

            foreach ($c->genes() as $geneB) {

                if ($geneA->id === $geneB->id) {
                    continue;
                }

                if (
                    $geneA->professor === $geneB->professor ||
                    $geneA->turma === $geneB->turma
                ) {

                    $graph->addEdge($geneA->id, $geneB->id);
                }
            }
        }

        return $graph;
    }
}
