<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Risk;

final class SaturationCalculator
{
    public function global(int $globalLoad, int $totalClasses, int $days, int $periodsPerDay): float
    {
        $globalCapacity = $totalClasses * $days * $periodsPerDay;

        if ($globalCapacity <= 0) {
            return 0.0;
        }

        return ($globalLoad / $globalCapacity) * 100;
    }

    public function overloads(array $loadsByEntity, int $capacityPerEntity): array
    {
        $overloads = [];

        foreach ($loadsByEntity as $entityId => $load) {
            if ($load <= $capacityPerEntity) {
                continue;
            }

            $excess = $load - $capacityPerEntity;

            $overloads[$entityId] = [
                'carga_total' => $load,
                'capacidade_maxima' => $capacityPerEntity,
                'excedente' => $excess,
                'percentual' => round(($load / $capacityPerEntity) * 100, 2),
            ];
        }

        return $overloads;
    }
}
