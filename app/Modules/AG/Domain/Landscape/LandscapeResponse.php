<?php

namespace App\Modules\AG\Domain\Landscape;

final class LandscapeResponse
{
    public function __construct(public readonly LandscapeState $state, public readonly float $mutationMultiplier, public readonly bool $activateALNS, public readonly float $selectionPressureMultiplier, public readonly float $diversificationBoost)
    {
    }
}
