<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Infrastructure\Constraints\Mappers;

use App\Models\ScheduleConstraint as ScheduleConstraintModel;
use App\Modules\Horarios\Domain\Constraints\DTO\ScheduleConstraintData;
use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;

final class ScheduleConstraintMapper
{
    public function toData(ScheduleConstraintModel $model): ScheduleConstraintData
    {
        $type = ConstraintType::tryFrom((string) $model->type)
            ?? throw InvalidScheduleConstraintException::single(sprintf('Tipo de constraint persistido invalido: %s.', (string) $model->type));

        $level = ConstraintLevel::tryFrom((string) $model->level)
            ?? throw InvalidScheduleConstraintException::single(sprintf('Nivel de constraint persistido invalido: %s.', (string) $model->level));

        return new ScheduleConstraintData(
            id: $model->getKey(),
            horarioId: (int) $model->horario_id,
            name: (string) $model->name,
            description: $model->description,
            type: $type,
            level: $level,
            weight: (int) $model->weight,
            isActive: (bool) $model->is_active,
            payload: is_array($model->payload_json) ? $model->payload_json : [],
            createdBy: $model->created_by !== null ? (int) $model->created_by : null,
            updatedBy: $model->updated_by !== null ? (int) $model->updated_by : null,
            createdAt: $model->created_at?->toISOString(),
            updatedAt: $model->updated_at?->toISOString(),
        );
    }

    public function toAttributes(ScheduleConstraint $constraint): array
    {
        return [
            'horario_id' => $constraint->horarioId(),
            'name' => $constraint->name(),
            'description' => $constraint->description(),
            'type' => $constraint->type()->value,
            'level' => $constraint->level()->value,
            'weight' => $constraint->weight(),
            'is_active' => $constraint->isActive(),
            'payload_json' => $constraint->payload(),
        ];
    }
}
