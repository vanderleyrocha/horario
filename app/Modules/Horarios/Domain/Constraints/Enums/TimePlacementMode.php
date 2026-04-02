<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Enums;

enum TimePlacementMode: string
{
    case REQUIRED = 'REQUIRED';
    case PREFERRED = 'PREFERRED';
    case FORBIDDEN = 'FORBIDDEN';
}
