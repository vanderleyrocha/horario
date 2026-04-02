<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Validators;

use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;

final class MutualExclusionConstraintValidator extends AbstractConstraintValidator
{
    public function validate(array $payload): array
    {
        $this->requireKeys($payload, ['left_group']);

        $leftGroup = $this->requireTargetGroup($payload, 'left_group');
        $rightGroup = $this->optionalTargetGroup($payload, 'right_group');

        if ($rightGroup !== null && $leftGroup->intersects($rightGroup)) {
            throw InvalidScheduleConstraintException::single('MUTUAL_EXCLUSION nao permite interseccao entre left_group e right_group.');
        }

        if ($rightGroup === null && $leftGroup->count() < 2) {
            throw InvalidScheduleConstraintException::single('MUTUAL_EXCLUSION com grupo unico exige ao menos duas aulas no left_group.');
        }

        return [
            'left_group' => $leftGroup->toArray(),
            'right_group' => $rightGroup?->toArray(),
        ];
    }
}
