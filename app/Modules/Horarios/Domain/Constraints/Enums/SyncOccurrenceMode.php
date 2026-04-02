<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Enums;

enum SyncOccurrenceMode: string
{
    case ALL = 'ALL';
    case AT_LEAST_ONE = 'AT_LEAST_ONE';
}
