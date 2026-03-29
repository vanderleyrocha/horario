<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Selection;

use App\Modules\AG\Domain\Operators\Selection\FitnessSharing\FitnessSharingCalculator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class TournamentSelection implements AdaptiveSelectionPressureInterface, SelectionOperatorInterface
{
    private readonly int $baseTournamentSize;

    private int $effectiveTournamentSize;

    private float $selectionPressureMultiplier = 1.0;

    public function __construct(private int $k, private FitnessSharingCalculator $sharing)
    {
        $this->baseTournamentSize = max(1, $k);
        $this->effectiveTournamentSize = $this->baseTournamentSize;
    }

    public function select(array $population): Cromossomo
    {
        $candidates = [];
        $tournamentSize = max(1, min(count($population), $this->effectiveTournamentSize));

        for ($i = 0; $i < $tournamentSize; $i++) {
            $candidates[] = $population[array_rand($population)];
        }

        usort($candidates, function (Cromossomo $a, Cromossomo $b) use ($population) {

            $fa = $this->sharing
                ->sharedFitness($a, $population);

            $fb = $this->sharing
                ->sharedFitness($b, $population);

            return $fb <=> $fa;
        });

        return $candidates[0];
    }

    public function applySelectionPressureMultiplier(float $multiplier): void
    {
        $normalizedMultiplier = max(0.34, min($multiplier, 3.0));

        $this->selectionPressureMultiplier = $normalizedMultiplier;
        $this->effectiveTournamentSize = $this->resolveTournamentSize($normalizedMultiplier);
    }

    public function selectionPressureTelemetry(): array
    {
        return [
            'selection_pressure_supported' => true,
            'selection_pressure_multiplier' => round($this->selectionPressureMultiplier, 6),
            'selection_pressure_base_tournament_size' => $this->baseTournamentSize,
            'selection_pressure_effective_tournament_size' => $this->effectiveTournamentSize,
            'selection_pressure_state' => match (true) {
                $this->effectiveTournamentSize < $this->baseTournamentSize => 'reduced',
                $this->effectiveTournamentSize > $this->baseTournamentSize => 'elevated',
                default => 'nominal',
            },
        ];
    }

    private function resolveTournamentSize(float $multiplier): int
    {
        if ($multiplier === 1.0) {
            return $this->baseTournamentSize;
        }

        $scaledSize = $this->baseTournamentSize * $multiplier;

        if ($multiplier < 1.0) {
            return max($this->minimumTournamentSize(), (int) floor($scaledSize));
        }

        return max($this->baseTournamentSize, (int) ceil($scaledSize));
    }

    private function minimumTournamentSize(): int
    {
        return $this->baseTournamentSize > 1 ? 2 : 1;
    }
}
