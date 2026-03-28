<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseSimulation
{
    public function __construct(
        public readonly string $policy,
        public readonly bool $simulatedOnly,
        public readonly bool $wouldEscalate,
        public readonly LandscapeState $targetState,
        public readonly float $mutationMultiplier,
        public readonly bool $activateALNS,
        public readonly float $selectionPressureMultiplier,
        public readonly float $diversificationBoost,
        public readonly string $reason
    ) {}

    public function toArray(): array
    {
        return [
            'policy' => $this->policy,
            'simulated_only' => $this->simulatedOnly,
            'would_escalate' => $this->wouldEscalate,
            'target_state' => $this->targetState->value,
            'mutation_multiplier' => round($this->mutationMultiplier, 6),
            'activate_alns' => $this->activateALNS,
            'selection_pressure_multiplier' => round($this->selectionPressureMultiplier, 6),
            'diversification_boost' => round($this->diversificationBoost, 6),
            'reason' => $this->reason,
        ];
    }
}
