<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Risk;

final class RiskIndexCalculator
{
    public function calculate(
        float $globalSaturation,
        array $turmaOverloads,
        array $professorOverloads,
        array $doubleBlockIssues,
        float $structuralEntropy
    ): int {
        $saturationRisk = min(100, max(0, $globalSaturation));
        $classOverloadRisk = $this->normalizeOverloadRisk($turmaOverloads, 12.0);
        $professorOverloadRisk = $this->normalizeOverloadRisk($professorOverloads, 8.0);
        $blockDeficitRisk = $this->normalizeBlockRisk($doubleBlockIssues);
        $entropyRisk = min(100, max(0, 100 - $structuralEntropy));

        $weightedRisk =
            ($saturationRisk * 0.35) +
            ($classOverloadRisk * 0.25) +
            ($professorOverloadRisk * 0.15) +
            ($blockDeficitRisk * 0.15) +
            ($entropyRisk * 0.10);

        return (int) round(min(100, max(0, $weightedRisk)));
    }

    private function normalizeOverloadRisk(array $overloads, float $weightPerExcessUnit): float
    {
        if ($overloads === []) {
            return 0.0;
        }

        $totalExcess = array_sum(array_map(
            static fn (array $item): int => (int) ($item['excedente'] ?? 0),
            $overloads
        ));

        return min(100, $totalExcess * $weightPerExcessUnit);
    }

    private function normalizeBlockRisk(array $doubleBlockIssues): float
    {
        if ($doubleBlockIssues === []) {
            return 0.0;
        }

        $totalDeficit = array_sum(array_map(
            static fn (array $item): int => (int) ($item['deficit'] ?? 0),
            $doubleBlockIssues
        ));

        return min(100, $totalDeficit * 15.0);
    }
}
