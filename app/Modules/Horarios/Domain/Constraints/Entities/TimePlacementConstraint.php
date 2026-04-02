<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Entities;

use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Enums\TimePlacementMode;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\ConstraintTargetGroup;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\TimePlacementWindow;

final class TimePlacementConstraint extends ScheduleConstraint
{
    public function __construct(
        ?int $id,
        int $horarioId,
        string $name,
        ?string $description,
        ConstraintLevel $level,
        int $weight,
        bool $isActive,
        private readonly ConstraintTargetGroup $targetGroup,
        private readonly TimePlacementMode $mode,
        private readonly TimePlacementWindow $window,
    ) {
        parent::__construct($id, $horarioId, $name, $description, $level, $weight, $isActive);
    }

    public function type(): ConstraintType
    {
        return ConstraintType::TIME_PLACEMENT;
    }

    public function targetGroup(): ConstraintTargetGroup
    {
        return $this->targetGroup;
    }

    public function mode(): TimePlacementMode
    {
        return $this->mode;
    }

    public function window(): TimePlacementWindow
    {
        return $this->window;
    }

    public function payload(): array
    {
        return [
            'target_group' => $this->targetGroup->toArray(),
            'mode' => $this->mode->value,
            ...$this->window->toArray(),
        ];
    }
}
