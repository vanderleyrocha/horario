<?php

namespace App\Modules\AG\Domain\Intensification\Kempe;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\ConflictGraph\ConflictGraph;

class KempeChainBuilder {
    public function build(Cromossomo $c, ConflictGraph $graph, int $seedGeneId, int $targetSlot): KempeChain {

        $seed = $c->genes()[$seedGeneId];

        $slotA = $seed->slot;
        $slotB = $targetSlot;

        $chain = new KempeChain($slotA, $slotB);

        $queue = [$seedGeneId];

        $visited = [];

        while (!empty($queue)) {

            $geneId = array_pop($queue);

            if (isset($visited[$geneId])) {
                continue;
            }

            $visited[$geneId] = true;

            $gene = $c->genes()[$geneId];

            if ($gene->slot !== $slotA && $gene->slot !== $slotB) {
                continue;
            }

            $chain->addGene($geneId);

            foreach ($graph->getNeighbors($geneId) as $neighborId) {

                $neighbor = $c->genes()[$neighborId];

                if ($neighbor->slot === $slotA || $neighbor->slot === $slotB) {
                    $queue[] = $neighborId;
                }
            }
        }

        return $chain;
    }
}
