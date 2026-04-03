<?php

namespace App\Modules\AG\Domain\Intensification\Kempe;

use App\Modules\AG\Domain\ConflictGraph\ConflictGraphBuilder;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class KempeNeighborhoodSearch
{
    private ConflictGraphBuilder $graphBuilder;

    public function __construct()
    {
        $this->graphBuilder = new ConflictGraphBuilder;
    }

    public function improve(Cromossomo $c): Cromossomo
    {
        $graph = $this->graphBuilder->build($c);

        $seed = array_rand($c->genes());

        $targetSlot = rand(0, 30);

        $builder = new KempeChainBuilder;

        $chain = $builder->build(
            $c,
            $graph,
            $seed,
            $targetSlot
        );

        $move = new KempeChainMoveOperator;

        return $move->apply($c, $chain);
    }
}
