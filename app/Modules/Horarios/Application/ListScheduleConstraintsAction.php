<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application;

use App\Modules\Horarios\Domain\Constraints\Repositories\ScheduleConstraintRepository;

final class ListScheduleConstraintsAction
{
    public function __construct(
        private readonly ScheduleConstraintRepository $repository,
    ) {}

    public function execute(int $horarioId): array
    {
        return $this->repository->listByHorario($horarioId);
    }
}
