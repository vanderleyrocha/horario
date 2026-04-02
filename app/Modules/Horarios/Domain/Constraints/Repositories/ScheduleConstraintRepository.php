<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Repositories;

use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;

interface ScheduleConstraintRepository
{
    public function create(ScheduleConstraint $constraint, ?int $actorId = null): ScheduleConstraint;

    public function update(ScheduleConstraint $constraint, ?int $actorId = null): ScheduleConstraint;

    public function delete(int $id, int $horarioId): bool;

    public function findById(int $id, int $horarioId): ?ScheduleConstraint;

    /**
     * @return list<ScheduleConstraint>
     */
    public function listByHorario(int $horarioId): array;

    /**
     * @return list<ScheduleConstraint>
     */
    public function listActiveByHorario(int $horarioId): array;
}
