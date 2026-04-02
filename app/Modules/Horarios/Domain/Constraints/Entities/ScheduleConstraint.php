<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Entities;

use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use InvalidArgumentException;

abstract class ScheduleConstraint
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $horarioId,
        private readonly string $name,
        private readonly ?string $description,
        private readonly ConstraintLevel $level,
        private readonly int $weight,
        private readonly bool $isActive,
    ) {
        if ($this->horarioId <= 0) {
            throw new InvalidArgumentException('ScheduleConstraint requer horarioId valido.');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('ScheduleConstraint requer name nao vazio.');
        }

        if ($this->weight < 1) {
            throw new InvalidArgumentException('ScheduleConstraint requer weight maior ou igual a 1.');
        }
    }

    abstract public function type(): ConstraintType;

    abstract public function payload(): array;

    public function id(): ?int
    {
        return $this->id;
    }

    public function horarioId(): int
    {
        return $this->horarioId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function level(): ConstraintLevel
    {
        return $this->level;
    }

    public function weight(): int
    {
        return $this->weight;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function metadata(): array
    {
        return [
            'id' => $this->id,
            'horario_id' => $this->horarioId,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type()->value,
            'level' => $this->level->value,
            'weight' => $this->weight,
            'is_active' => $this->isActive,
        ];
    }

    public function toArray(): array
    {
        return $this->metadata() + [
            'payload' => $this->payload(),
        ];
    }
}
