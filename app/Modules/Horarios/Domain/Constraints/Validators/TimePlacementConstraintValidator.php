<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Validators;

use App\Modules\Horarios\Domain\Constraints\Enums\TimePlacementMode;
use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\TimePlacementWindow;

final class TimePlacementConstraintValidator extends AbstractConstraintValidator
{
    public function validate(array $payload): array
    {
        $this->requireKeys($payload, ['target_group', 'mode']);

        $targetGroup = $this->requireTargetGroup($payload, 'target_group');
        $mode = $this->requireEnumValue($payload, 'mode', TimePlacementMode::class);
        $days = $this->requireOptionalIntList($payload, 'allowed_days');
        $periods = $this->requireOptionalIntList($payload, 'allowed_periods');

        try {
            $window = new TimePlacementWindow($days, $periods);
        } catch (\InvalidArgumentException $exception) {
            throw InvalidScheduleConstraintException::single($exception->getMessage());
        }

        return [
            'target_group' => $targetGroup->toArray(),
            'mode' => $mode->value,
            ...$window->toArray(),
        ];
    }
}
