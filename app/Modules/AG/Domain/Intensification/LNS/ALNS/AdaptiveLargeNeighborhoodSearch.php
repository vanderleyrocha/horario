<?php

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class AdaptiveLargeNeighborhoodSearch {
    private OperatorScoreManager $scores;

    private RouletteWheelSelector $selector;

    public function __construct(
        private array $destroyOperators,
        private array $repairOperators
    ) {

        $this->scores = new OperatorScoreManager(
            $destroyOperators,
            $repairOperators
        );

        $this->selector = new RouletteWheelSelector();
    }

    public function improve(Cromossomo $solution): Cromossomo {
        $destroy = $this->selector->select(
            $this->destroyOperators,
            $this->scores->destroyStats()
        );

        $repair = $this->selector->select(
            $this->repairOperators,
            $this->scores->repairStats()
        );

        $partial = $destroy->destroy($solution);

        $candidate = $repair->repair($partial);

        $improvement =
            $candidate->fitness() - $solution->fitness();

        $this->scores->reward($destroy, $repair, $improvement);

        return $candidate;
    }
}
