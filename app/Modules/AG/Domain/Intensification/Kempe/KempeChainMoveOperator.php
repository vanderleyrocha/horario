<?php

namespace App\Modules\AG\Domain\Intensification\Kempe;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class KempeChainMoveOperator
{
    public function apply(Cromossomo $c, KempeChain $chain): Cromossomo
    {

        foreach ($chain->genes as $geneId) {

            $gene = $c->genes()[$geneId];

            if ($gene->slot === $chain->slotA) {
                $gene->slot = $chain->slotB;
            } else {
                $gene->slot = $chain->slotA;
            }
        }

        return $c;
    }
}
