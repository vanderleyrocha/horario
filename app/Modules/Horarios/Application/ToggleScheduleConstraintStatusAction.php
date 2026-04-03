<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application;

use App\Modules\Horarios\Domain\Constraints\DTO\UpdateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\Constraints\Exceptions\ScheduleConstraintNotFoundException;
use App\Modules\Horarios\Domain\Constraints\Factories\ScheduleConstraintFactory;
use App\Modules\Horarios\Domain\Constraints\Repositories\ScheduleConstraintRepository;

final class ToggleScheduleConstraintStatusAction
{
    public function __construct(
        private readonly ScheduleConstraintRepository $repository,
        private readonly ScheduleConstraintFactory $factory,
    ) {}

    public function execute(int $id, int $horarioId, ?int $actorId = null): ScheduleConstraint
    {
        $existing = $this->repository->findById($id, $horarioId);

        if ($existing === null) {
            throw ScheduleConstraintNotFoundException::forContext($id, $horarioId);
        }

        return $this->repository->update(
            $this->factory->fromUpdateInput(
                new UpdateScheduleConstraintInput(
                    id: $existing->id(),
                    horarioId: $existing->horarioId(),
                    name: $existing->name(),
                    description: $existing->description(),
                    level: $existing->level(),
                    weight: $existing->weight(),
                    isActive: ! $existing->isActive(),
                    payload: $existing->payload(),
                    actorId: $actorId,
                ),
                $existing->type(),
            ),
            $actorId,
        );
    }
}
