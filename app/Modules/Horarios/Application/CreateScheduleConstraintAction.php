<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application;

use App\Modules\Horarios\Domain\Constraints\DTO\CreateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\Constraints\Factories\ScheduleConstraintFactory;
use App\Modules\Horarios\Domain\Constraints\Repositories\ScheduleConstraintRepository;

final class CreateScheduleConstraintAction
{
    public function __construct(
        private readonly ScheduleConstraintRepository $repository,
        private readonly ScheduleConstraintFactory $factory,
    ) {
    }

    public function execute(CreateScheduleConstraintInput $input): ScheduleConstraint
    {
        return $this->repository->create(
            $this->factory->fromCreateInput($input),
            $input->actorId,
        );
    }
}
