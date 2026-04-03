<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\DTO;

use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;

final class ScheduleConstraintData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $horarioId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ConstraintType $type,
        public readonly ConstraintLevel $level,
        public readonly int $weight,
        public readonly bool $isActive,
        public readonly array $payload,
        public readonly ?int $createdBy = null,
        public readonly ?int $updatedBy = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'horario_id' => $this->horarioId,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'level' => $this->level->value,
            'weight' => $this->weight,
            'is_active' => $this->isActive,
            'payload' => $this->payload,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
