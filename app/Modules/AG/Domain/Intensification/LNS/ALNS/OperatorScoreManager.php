<?php

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

class OperatorScoreManager {
    private array $destroyStats = [];

    private array $repairStats = [];

    public function __construct(
        private array $destroyOperators,
        private array $repairOperators
    ) {

        foreach ($destroyOperators as $op) {
            $this->destroyStats[spl_object_id($op)]
                = new OperatorPerformance();
        }

        foreach ($repairOperators as $op) {
            $this->repairStats[spl_object_id($op)]
                = new OperatorPerformance();
        }
    }

    public function reward($destroy, $repair, float $improvement): void {
        if ($improvement <= 0) {
            return;
        }

        $this->destroyStats[spl_object_id($destroy)]
            ->reward($improvement);

        $this->repairStats[spl_object_id($repair)]
            ->reward($improvement);
    }

    public function destroyStats(): array {
        return $this->destroyStats;
    }

    public function repairStats(): array {
        return $this->repairStats;
    }
}
