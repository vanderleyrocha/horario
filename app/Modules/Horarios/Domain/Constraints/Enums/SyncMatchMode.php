<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Enums;

enum SyncMatchMode: string
{
    case ALL_TO_ALL = 'ALL_TO_ALL';
    case FIRST_WITH_FIRST = 'FIRST_WITH_FIRST';
}
