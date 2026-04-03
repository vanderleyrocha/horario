<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Application;

use App\Modules\Horarios\Domain\Constraints\Repositories\ScheduleConstraintRepository;

final class DeleteScheduleConstraintAction
{
    public function __construct(
        private readonly ScheduleConstraintRepository $repository,
    ) {}

    public function execute(int $id, int $horarioId): bool
    {
        return $this->repository->delete($id, $horarioId);
    }
}
