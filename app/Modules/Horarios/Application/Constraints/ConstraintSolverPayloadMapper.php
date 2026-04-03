<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application\Constraints;

use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;

final class ConstraintSolverPayloadMapper
{
    /**
     * @param  array<int, ScheduleConstraint>  $constraints
     * @return array<int, CustomConstraintData>
     */
    public function mapCollection(array $constraints): array
    {
        return array_map(
            fn (ScheduleConstraint $constraint): CustomConstraintData => $this->map($constraint),
            $constraints,
        );
    }

    public function map(ScheduleConstraint $constraint): CustomConstraintData
    {
        return new CustomConstraintData(
            id: $constraint->id() ?? 0,
            name: $constraint->name(),
            description: $constraint->description(),
            type: $constraint->type()->value,
            level: $constraint->level()->value,
            weight: $constraint->weight(),
            isActive: $constraint->isActive(),
            payload: $constraint->payload(),
        );
    }
}
