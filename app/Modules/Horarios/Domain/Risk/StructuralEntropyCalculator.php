<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Risk;

final class StructuralEntropyCalculator
{
    public function calculate(array $loads): float
    {
        $positiveLoads = array_values(array_filter(
            array_map(static fn ($value) => (float) $value, $loads),
            static fn (float $value) => $value > 0
        ));

        $count = count($positiveLoads);

        if ($count <= 1) {
            return 0.0;
        }

        $total = array_sum($positiveLoads);

        if ($total <= 0.0) {
            return 0.0;
        }

        $entropy = 0.0;

        foreach ($positiveLoads as $load) {
            $probability = $load / $total;
            $entropy -= $probability * log($probability, 2);
        }

        $maxEntropy = log($count, 2);

        if ($maxEntropy <= 0.0) {
            return 0.0;
        }

        return round(($entropy / $maxEntropy) * 100, 2);
    }
}
