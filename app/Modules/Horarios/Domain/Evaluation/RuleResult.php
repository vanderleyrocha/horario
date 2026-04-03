<?php

namespace App\Modules\Horarios\Domain\Evaluation;

final class RuleResult
{
    public function __construct(
        private readonly float $penalty,
        private readonly string $rule,
        private readonly array $conflicts = []
    ) {}

    public function penalty(): float
    {
        return $this->penalty;
    }

    public function rule(): string
    {
        return $this->rule;
    }

    public function conflicts(): array
    {
        return $this->conflicts;
    }
}
