<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\ValueObjects;

final class CustomConstraintData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $type,
        public readonly string $level,
        public readonly int $weight,
        public readonly bool $isActive,
        public readonly array $payload,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'level' => $this->level,
            'weight' => $this->weight,
            'is_active' => $this->isActive,
            'payload' => $this->payload,
        ];
    }
}
