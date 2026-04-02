<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Entities;

use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\ConstraintTargetGroup;
use InvalidArgumentException;

final class MutualExclusionConstraint extends ScheduleConstraint
{
    public function __construct(
        ?int $id,
        int $horarioId,
        string $name,
        ?string $description,
        ConstraintLevel $level,
        int $weight,
        bool $isActive,
        private readonly ConstraintTargetGroup $leftGroup,
        private readonly ?ConstraintTargetGroup $rightGroup,
    ) {
        parent::__construct($id, $horarioId, $name, $description, $level, $weight, $isActive);

        if ($this->rightGroup !== null && $this->leftGroup->intersects($this->rightGroup)) {
            throw new InvalidArgumentException('MutualExclusionConstraint nao permite interseccao entre grupos.');
        }

        if ($this->rightGroup === null && $this->leftGroup->count() < 2) {
            throw new InvalidArgumentException('MutualExclusionConstraint com grupo unico requer ao menos duas aulas.');
        }
    }

    public function type(): ConstraintType
    {
        return ConstraintType::MUTUAL_EXCLUSION;
    }

    public function leftGroup(): ConstraintTargetGroup
    {
        return $this->leftGroup;
    }

    public function rightGroup(): ?ConstraintTargetGroup
    {
        return $this->rightGroup;
    }

    public function payload(): array
    {
        return [
            'left_group' => $this->leftGroup->toArray(),
            'right_group' => $this->rightGroup?->toArray(),
        ];
    }
}
