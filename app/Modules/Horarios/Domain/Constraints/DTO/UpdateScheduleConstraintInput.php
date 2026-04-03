<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\DTO;

use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;

final class UpdateScheduleConstraintInput
{
    public function __construct(
        public readonly int $id,
        public readonly int $horarioId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ConstraintLevel $level,
        public readonly int $weight,
        public readonly bool $isActive,
        public readonly array $payload,
        public readonly ?int $actorId = null,
    ) {}
}
