<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Entities;

use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncMatchMode;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncOccurrenceMode;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\ConstraintTargetGroup;
use InvalidArgumentException;

final class SyncSameTimeslotConstraint extends ScheduleConstraint
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
        private readonly SyncOccurrenceMode $occurrenceMode,
        private readonly SyncMatchMode $matchMode,
    ) {
        parent::__construct($id, $horarioId, $name, $description, $level, $weight, $isActive);

        if ($this->rightGroup !== null && $this->leftGroup->intersects($this->rightGroup)) {
            throw new InvalidArgumentException('SyncSameTimeslotConstraint nao permite interseccao entre grupos.');
        }

        if ($this->rightGroup === null && $this->leftGroup->count() < 2) {
            throw new InvalidArgumentException('SyncSameTimeslotConstraint com grupo unico requer ao menos duas aulas.');
        }
    }

    public function type(): ConstraintType
    {
        return ConstraintType::SYNC_SAME_TIMESLOT;
    }

    public function leftGroup(): ConstraintTargetGroup
    {
        return $this->leftGroup;
    }

    public function rightGroup(): ?ConstraintTargetGroup
    {
        return $this->rightGroup;
    }

    public function occurrenceMode(): SyncOccurrenceMode
    {
        return $this->occurrenceMode;
    }

    public function matchMode(): SyncMatchMode
    {
        return $this->matchMode;
    }

    public function payload(): array
    {
        return [
            'left_group' => $this->leftGroup->toArray(),
            'right_group' => $this->rightGroup?->toArray(),
            'occurrence_mode' => $this->occurrenceMode->value,
            'match_mode' => $this->matchMode->value,
        ];
    }
}
