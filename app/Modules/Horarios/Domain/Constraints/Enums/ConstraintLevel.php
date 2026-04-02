<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Enums;

enum ConstraintLevel: string
{
    case HARD = 'HARD';
    case SOFT = 'SOFT';

    public function requiresWeight(): bool
    {
        return $this === self::SOFT;
    }
}
