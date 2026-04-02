<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Enums;

enum ConstraintType: string
{
    case SYNC_SAME_TIMESLOT = 'SYNC_SAME_TIMESLOT';
    case MUTUAL_EXCLUSION = 'MUTUAL_EXCLUSION';
    case TIME_PLACEMENT = 'TIME_PLACEMENT';
}
