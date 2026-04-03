<?php

namespace App\Modules\AG\Domain\ConflictGraph;

use App\Modules\AG\Domain\Representation\Entities\Gene;

class ConflictGraphUpdater
{
    public function indexGene(ConflictGraph $graph, int $geneIndex, Gene $gene, array $genes): void
    {

        foreach ($genes as $otherIndex => $other) {

            if ($otherIndex === $geneIndex) {
                continue;
            }

            if (
                $gene->professorId() === $other->professorId() ||
                $gene->turmaId() === $other->turmaId()
            ) {

                $graph->addEdge($geneIndex, $otherIndex);
            }
        }
    }

    public function removeGene(ConflictGraph $graph, int $geneIndex): void
    {

        foreach ($graph->neighbors($geneIndex) as $neighbor) {

            $graph->removeEdge($geneIndex, $neighbor);
        }
    }
}
