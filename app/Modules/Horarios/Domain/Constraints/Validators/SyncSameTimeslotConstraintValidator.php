<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Validators;

use App\Modules\Horarios\Domain\Constraints\Enums\SyncMatchMode;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncOccurrenceMode;
use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;

final class SyncSameTimeslotConstraintValidator extends AbstractConstraintValidator
{
    public function validate(array $payload): array
    {
        $this->requireKeys($payload, ['left_group', 'occurrence_mode', 'match_mode']);

        $leftGroup = $this->requireTargetGroup($payload, 'left_group');
        $rightGroup = $this->optionalTargetGroup($payload, 'right_group');
        $occurrenceMode = $this->requireEnumValue($payload, 'occurrence_mode', SyncOccurrenceMode::class);
        $matchMode = $this->requireEnumValue($payload, 'match_mode', SyncMatchMode::class);

        if ($rightGroup !== null && $leftGroup->intersects($rightGroup)) {
            throw InvalidScheduleConstraintException::single('SYNC_SAME_TIMESLOT nao permite interseccao entre left_group e right_group.');
        }

        if ($rightGroup === null && $leftGroup->count() < 2) {
            throw InvalidScheduleConstraintException::single('SYNC_SAME_TIMESLOT com grupo unico exige ao menos duas aulas no left_group.');
        }

        return [
            'left_group' => $leftGroup->toArray(),
            'right_group' => $rightGroup?->toArray(),
            'occurrence_mode' => $occurrenceMode->value,
            'match_mode' => $matchMode->value,
        ];
    }
}
