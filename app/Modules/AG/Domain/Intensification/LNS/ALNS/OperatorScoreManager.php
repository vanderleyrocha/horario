<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

use App\Modules\AG\Domain\Operators\EvolutionaryOperatorInterface;

final class OperatorScoreManager
{
    /**
     * @var array<string, OperatorPerformance>
     */
    private array $destroyStats = [];

    /**
     * @var array<string, OperatorPerformance>
     */
    private array $repairStats = [];

    /**
     * @param  EvolutionaryOperatorInterface[]  $destroyOperators
     * @param  EvolutionaryOperatorInterface[]  $repairOperators
     */
    public function __construct(array $destroyOperators, array $repairOperators)
    {
        foreach ($destroyOperators as $operator) {
            $this->destroyStats[$this->nameOf($operator)] = new OperatorPerformance;
        }

        foreach ($repairOperators as $operator) {
            $this->repairStats[$this->nameOf($operator)] = new OperatorPerformance;
        }
    }

    public function registerSelection(EvolutionaryOperatorInterface $destroy, EvolutionaryOperatorInterface $repair): void
    {
        $this->destroyStats[$this->nameOf($destroy)]?->registerUse();
        $this->repairStats[$this->nameOf($repair)]?->registerUse();
    }

    public function reward(EvolutionaryOperatorInterface $destroy, EvolutionaryOperatorInterface $repair, float $improvement): void
    {
        if ($improvement <= 0.0) {
            return;
        }

        $this->destroyStats[$this->nameOf($destroy)]?->reward($improvement);
        $this->repairStats[$this->nameOf($repair)]?->reward($improvement);
    }

    public function destroyStats(): array
    {
        return $this->serializeStats($this->destroyStats);
    }

    public function repairStats(): array
    {
        return $this->serializeStats($this->repairStats);
    }

    private function nameOf(EvolutionaryOperatorInterface $operator): string
    {
        return $operator->getName();
    }

    /**
     * @param  array<string, OperatorPerformance>  $stats
     * @return array<string, array<string, float|int>>
     */
    private function serializeStats(array $stats): array
    {
        $serialized = [];

        foreach ($stats as $operator => $performance) {
            $serialized[$operator] = $performance->toArray();
        }

        return $serialized;
    }
}
