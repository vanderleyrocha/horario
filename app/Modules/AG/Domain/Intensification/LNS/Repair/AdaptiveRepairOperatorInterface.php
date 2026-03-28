<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

interface AdaptiveRepairOperatorInterface
{
    public function configureRepairIntensity(float $intensity): void;
}
