<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Results;

final class ConstraintEvaluationSummary
{
    /**
     * @var array<int, ConstraintViolationResult>
     */
    private array $violations = [];

    private float $hardPenalty = 0.0;

    private float $softPenalty = 0.0;

    public static function empty(): self
    {
        return new self();
    }

    public function addViolation(ConstraintViolationResult $violation): void
    {
        $this->violations[] = $violation;

        if ($this->isHardLevel($violation->constraintLevel())) {
            $this->hardPenalty += $violation->effectivePenalty();

            return;
        }

        $this->softPenalty += $violation->effectivePenalty();
    }

    public function merge(self $other): void
    {
        foreach ($other->violations() as $violation) {
            $this->addViolation($violation);
        }
    }

    public function hardPenalty(): float
    {
        return $this->hardPenalty;
    }

    public function softPenalty(): float
    {
        return $this->softPenalty;
    }

    public function totalPenalty(): float
    {
        return $this->hardPenalty + $this->softPenalty;
    }

    /**
     * @return array<int, ConstraintViolationResult>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @return array<int, ConstraintViolationResult>
     */
    public function violationsForConstraint(int $constraintId): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (ConstraintViolationResult $violation): bool => $violation->constraintId() === $constraintId,
        ));
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }

    public function groupedByConstraint(): array
    {
        $grouped = [];

        foreach ($this->violations as $violation) {
            $constraintId = $violation->constraintId();

            if (! isset($grouped[$constraintId])) {
                $grouped[$constraintId] = [
                    'constraint_id' => $constraintId,
                    'constraint_name' => $violation->constraintName(),
                    'constraint_type' => $violation->constraintType(),
                    'constraint_level' => $violation->constraintLevel(),
                    'effective_penalty' => 0.0,
                    'raw_penalty' => 0.0,
                    'violations' => [],
                ];
            }

            $grouped[$constraintId]['effective_penalty'] += $violation->effectivePenalty();
            $grouped[$constraintId]['raw_penalty'] += $violation->rawPenalty();
            $grouped[$constraintId]['violations'][] = $violation->toArray();
        }

        return $grouped;
    }

    public function conflictsForLevel(?string $level = null): array
    {
        $normalizedLevel = $level !== null ? strtoupper($level) : null;

        return array_values(array_map(
            static fn (ConstraintViolationResult $violation): array => $violation->toArray(),
            array_filter(
                $this->violations,
                static fn (ConstraintViolationResult $violation): bool => $normalizedLevel === null
                    || strtoupper($violation->constraintLevel()) === $normalizedLevel,
            ),
        ));
    }

    private function isHardLevel(string $level): bool
    {
        return strtoupper($level) === 'HARD';
    }
}