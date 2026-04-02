<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\ValueObjects;

use InvalidArgumentException;

final class TimePlacementWindow
{
    /**
     * @var list<int>
     */
    private array $days;

    /**
     * @var list<int>
     */
    private array $periods;

    /**
     * @param list<int> $days
     * @param list<int> $periods
     */
    public function __construct(array $days = [], array $periods = [])
    {
        $normalizedDays = array_values(array_unique(array_map(static fn (int $day): int => $day, $days)));
        $normalizedPeriods = array_values(array_unique(array_map(static fn (int $period): int => $period, $periods)));

        if ($normalizedDays === [] && $normalizedPeriods === []) {
            throw new InvalidArgumentException('TimePlacementWindow requer ao menos um dia ou periodo.');
        }

        foreach ($normalizedDays as $day) {
            if ($day <= 0) {
                throw new InvalidArgumentException('TimePlacementWindow recebeu dia invalido.');
            }
        }

        foreach ($normalizedPeriods as $period) {
            if ($period <= 0) {
                throw new InvalidArgumentException('TimePlacementWindow recebeu periodo invalido.');
            }
        }

        $this->days = $normalizedDays;
        $this->periods = $normalizedPeriods;
    }

    /**
     * @return list<int>
     */
    public function days(): array
    {
        return $this->days;
    }

    /**
     * @return list<int>
     */
    public function periods(): array
    {
        return $this->periods;
    }

    public function toArray(): array
    {
        return [
            'allowed_days' => $this->days,
            'allowed_periods' => $this->periods,
        ];
    }
}
