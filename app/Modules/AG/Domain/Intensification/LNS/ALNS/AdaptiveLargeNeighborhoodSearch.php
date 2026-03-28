<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

use App\Modules\AG\Domain\Intensification\LNS\Destroy\DestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RepairOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class AdaptiveLargeNeighborhoodSearch
{
    private OperatorScoreManager $scores;

    private array $lastTelemetry = [];

    /**
     * @param  DestroyOperatorInterface[]  $destroyOperators
     * @param  RepairOperatorInterface[]  $repairOperators
     */
    public function __construct(
        private array $destroyOperators,
        private array $repairOperators,
        private readonly ?OperatorSelectionStrategy $selector = null
    ) {
        $this->scores = new OperatorScoreManager(
            $destroyOperators,
            $repairOperators
        );
    }

    public function improve(Cromossomo $solution): Cromossomo
    {
        $selector = $this->selector ?? new RouletteWheelSelector;

        /** @var DestroyOperatorInterface $destroy */
        $destroy = $selector->select(
            $this->destroyOperators,
            $this->scores->destroyStats()
        );

        /** @var RepairOperatorInterface $repair */
        $repair = $selector->select(
            $this->repairOperators,
            $this->scores->repairStats()
        );

        $this->scores->registerSelection($destroy, $repair);

        $partial = $destroy->destroy($solution);
        $candidate = $repair->repair($partial);
        $improvement = $candidate->fitness() - $solution->fitness();

        $this->scores->reward($destroy, $repair, $improvement);

        $this->lastTelemetry = [
            'alns_destroy_operator' => $destroy->getName(),
            'alns_repair_operator' => $repair->getName(),
            'alns_improvement' => $improvement,
            'alns_destroy_stats' => $this->scores->destroyStats()[$destroy->getName()] ?? [],
            'alns_repair_stats' => $this->scores->repairStats()[$repair->getName()] ?? [],
        ];

        return $candidate;
    }

    public function lastTelemetry(): array
    {
        return $this->lastTelemetry;
    }
}
