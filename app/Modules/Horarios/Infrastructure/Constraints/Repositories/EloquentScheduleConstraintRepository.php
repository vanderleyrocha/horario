<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Infrastructure\Constraints\Repositories;

use App\Models\ScheduleConstraint as ScheduleConstraintModel;
use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;
use App\Modules\Horarios\Domain\Constraints\Exceptions\ScheduleConstraintNotFoundException;
use App\Modules\Horarios\Domain\Constraints\Factories\ScheduleConstraintFactory;
use App\Modules\Horarios\Domain\Constraints\Repositories\ScheduleConstraintRepository;
use App\Modules\Horarios\Infrastructure\Constraints\Mappers\ScheduleConstraintMapper;

final class EloquentScheduleConstraintRepository implements ScheduleConstraintRepository
{
    public function __construct(
        private readonly ScheduleConstraintMapper $mapper = new ScheduleConstraintMapper(),
        private readonly ScheduleConstraintFactory $factory = new ScheduleConstraintFactory(),
    ) {
    }

    public function create(ScheduleConstraint $constraint, ?int $actorId = null): ScheduleConstraint
    {
        if ($constraint->id() !== null) {
            throw InvalidScheduleConstraintException::single('Nao e permitido criar constraint com id previamente definido.');
        }

        $model = new ScheduleConstraintModel();
        $model->fill($this->mapper->toAttributes($constraint));
        $model->created_by = $actorId;
        $model->updated_by = $actorId;
        $model->save();

        return $this->toDomain($model->fresh());
    }

    public function update(ScheduleConstraint $constraint, ?int $actorId = null): ScheduleConstraint
    {
        $constraintId = $constraint->id();

        if ($constraintId === null) {
            throw InvalidScheduleConstraintException::single('Nao e permitido atualizar constraint sem id.');
        }

        $model = ScheduleConstraintModel::query()
            ->whereKey($constraintId)
            ->where('horario_id', $constraint->horarioId())
            ->first();

        if ($model === null) {
            throw ScheduleConstraintNotFoundException::forContext($constraintId, $constraint->horarioId());
        }

        $model->fill($this->mapper->toAttributes($constraint));
        $model->updated_by = $actorId;
        $model->save();

        return $this->toDomain($model->fresh());
    }

    public function delete(int $id, int $horarioId): bool
    {
        return ScheduleConstraintModel::query()
            ->whereKey($id)
            ->where('horario_id', $horarioId)
            ->delete() > 0;
    }

    public function findById(int $id, int $horarioId): ?ScheduleConstraint
    {
        $model = ScheduleConstraintModel::query()
            ->whereKey($id)
            ->where('horario_id', $horarioId)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function listByHorario(int $horarioId): array
    {
        return ScheduleConstraintModel::query()
            ->where('horario_id', $horarioId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ScheduleConstraintModel $model): ScheduleConstraint => $this->toDomain($model))
            ->all();
    }

    public function listActiveByHorario(int $horarioId): array
    {
        return ScheduleConstraintModel::query()
            ->where('horario_id', $horarioId)
            ->active()
            ->orderByDesc('id')
            ->get()
            ->map(fn (ScheduleConstraintModel $model): ScheduleConstraint => $this->toDomain($model))
            ->all();
    }

    private function toDomain(ScheduleConstraintModel $model): ScheduleConstraint
    {
        return $this->factory->fromData($this->mapper->toData($model));
    }
}
