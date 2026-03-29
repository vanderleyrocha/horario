<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Selection;

interface AdaptiveSelectionPressureInterface
{
    public function applySelectionPressureMultiplier(float $multiplier): void;

    /**
     * @return array<string, mixed>
     */
    public function selectionPressureTelemetry(): array;
}
