<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Results;

final class ConstraintViolationResult
{
    public function __construct(
        private readonly int $constraintId,
        private readonly string $constraintName,
        private readonly string $constraintType,
        private readonly string $constraintLevel,
        private readonly float $rawPenalty,
        private readonly float $effectivePenalty,
        private readonly string $message,
        private readonly array $details = [],
    ) {
    }

    public function constraintId(): int
    {
        return $this->constraintId;
    }

    public function constraintName(): string
    {
        return $this->constraintName;
    }

    public function constraintType(): string
    {
        return $this->constraintType;
    }

    public function constraintLevel(): string
    {
        return $this->constraintLevel;
    }

    public function rawPenalty(): float
    {
        return $this->rawPenalty;
    }

    public function effectivePenalty(): float
    {
        return $this->effectivePenalty;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function details(): array
    {
        return $this->details;
    }

    public function toArray(): array
    {
        return [
            'constraint_id' => $this->constraintId,
            'constraint_name' => $this->constraintName,
            'constraint_type' => $this->constraintType,
            'constraint_level' => $this->constraintLevel,
            'raw_penalty' => $this->rawPenalty,
            'effective_penalty' => $this->effectivePenalty,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}