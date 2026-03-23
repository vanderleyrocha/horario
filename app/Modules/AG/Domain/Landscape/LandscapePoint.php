<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class LandscapePoint
{
    public function __construct(public readonly int $generation, public readonly float $fitness, public readonly float $diversity, public readonly float $entropy)
    {
    }
}
