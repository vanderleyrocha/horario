<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application;

use App\Modules\Horarios\Domain\Constraints\DTO\UpdateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\Constraints\Exceptions\ScheduleConstraintNotFoundException;
use App\Modules\Horarios\Domain\Constraints\Factories\ScheduleConstraintFactory;
use App\Modules\Horarios\Domain\Constraints\Repositories\ScheduleConstraintRepository;

final class UpdateScheduleConstraintAction
{
    public function __construct(
        private readonly ScheduleConstraintRepository $repository,
        private readonly ScheduleConstraintFactory $factory,
    ) {}

    public function execute(UpdateScheduleConstraintInput $input): ScheduleConstraint
    {
        $existing = $this->repository->findById($input->id, $input->horarioId);

        if ($existing === null) {
            throw ScheduleConstraintNotFoundException::forContext($input->id, $input->horarioId);
        }

        return $this->repository->update(
            $this->factory->fromUpdateInput($input, $existing->type()),
            $input->actorId,
        );
    }
}
