<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

interface AdaptiveDestroyOperatorInterface
{
    public function configureDestroyIntensity(float $intensity): void;
}
