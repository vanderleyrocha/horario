<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Factories;

use App\Modules\Horarios\Domain\Constraints\DTO\CreateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\DTO\ScheduleConstraintData;
use App\Modules\Horarios\Domain\Constraints\DTO\UpdateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Entities\MutualExclusionConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\SyncSameTimeslotConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\TimePlacementConstraint;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncMatchMode;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncOccurrenceMode;
use App\Modules\Horarios\Domain\Constraints\Enums\TimePlacementMode;
use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;
use App\Modules\Horarios\Domain\Constraints\Validators\ConstraintValidator;
use App\Modules\Horarios\Domain\Constraints\Validators\MutualExclusionConstraintValidator;
use App\Modules\Horarios\Domain\Constraints\Validators\SyncSameTimeslotConstraintValidator;
use App\Modules\Horarios\Domain\Constraints\Validators\TimePlacementConstraintValidator;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\ConstraintTargetGroup;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\TimePlacementWindow;

final class ScheduleConstraintFactory
{
    /**
     * @param  array<string, ConstraintValidator>|null  $validators
     */
    public function __construct(
        private readonly ?array $validators = null,
    ) {}

    public function fromCreateInput(CreateScheduleConstraintInput $input): ScheduleConstraint
    {
        return $this->build(
            id: null,
            horarioId: $input->horarioId,
            name: $input->name,
            description: $input->description,
            type: $input->type,
            level: $input->level,
            weight: $input->weight,
            isActive: $input->isActive,
            payload: $input->payload,
        );
    }

    public function fromUpdateInput(UpdateScheduleConstraintInput $input, ConstraintType $type): ScheduleConstraint
    {
        return $this->build(
            id: $input->id,
            horarioId: $input->horarioId,
            name: $input->name,
            description: $input->description,
            type: $type,
            level: $input->level,
            weight: $input->weight,
            isActive: $input->isActive,
            payload: $input->payload,
        );
    }

    public function fromData(ScheduleConstraintData $data): ScheduleConstraint
    {
        return $this->build(
            id: $data->id,
            horarioId: $data->horarioId,
            name: $data->name,
            description: $data->description,
            type: $data->type,
            level: $data->level,
            weight: $data->weight,
            isActive: $data->isActive,
            payload: $data->payload,
        );
    }

    private function build(
        ?int $id,
        int $horarioId,
        string $name,
        ?string $description,
        ConstraintType $type,
        ConstraintLevel $level,
        int $weight,
        bool $isActive,
        array $payload,
    ): ScheduleConstraint {
        $this->assertIdentity($id, $horarioId, $name);

        $normalizedWeight = $this->normalizeWeight($level, $weight);
        $normalizedPayload = $this->validatorFor($type)->validate($payload);

        return match ($type) {
            ConstraintType::SYNC_SAME_TIMESLOT => new SyncSameTimeslotConstraint(
                id: $id,
                horarioId: $horarioId,
                name: trim($name),
                description: $description,
                level: $level,
                weight: $normalizedWeight,
                isActive: $isActive,
                leftGroup: new ConstraintTargetGroup($normalizedPayload['left_group']['lesson_ids']),
                rightGroup: isset($normalizedPayload['right_group']['lesson_ids'])
                    ? new ConstraintTargetGroup($normalizedPayload['right_group']['lesson_ids'])
                    : null,
                occurrenceMode: SyncOccurrenceMode::from($normalizedPayload['occurrence_mode']),
                matchMode: SyncMatchMode::from($normalizedPayload['match_mode']),
            ),
            ConstraintType::MUTUAL_EXCLUSION => new MutualExclusionConstraint(
                id: $id,
                horarioId: $horarioId,
                name: trim($name),
                description: $description,
                level: $level,
                weight: $normalizedWeight,
                isActive: $isActive,
                leftGroup: new ConstraintTargetGroup($normalizedPayload['left_group']['lesson_ids']),
                rightGroup: isset($normalizedPayload['right_group']['lesson_ids'])
                    ? new ConstraintTargetGroup($normalizedPayload['right_group']['lesson_ids'])
                    : null,
            ),
            ConstraintType::TIME_PLACEMENT => new TimePlacementConstraint(
                id: $id,
                horarioId: $horarioId,
                name: trim($name),
                description: $description,
                level: $level,
                weight: $normalizedWeight,
                isActive: $isActive,
                targetGroup: new ConstraintTargetGroup($normalizedPayload['target_group']['lesson_ids']),
                mode: TimePlacementMode::from($normalizedPayload['mode']),
                window: new TimePlacementWindow(
                    $normalizedPayload['allowed_days'] ?? [],
                    $normalizedPayload['allowed_periods'] ?? [],
                ),
            ),
        };
    }

    private function assertIdentity(?int $id, int $horarioId, string $name): void
    {
        if ($id !== null && $id <= 0) {
            throw InvalidScheduleConstraintException::single('Constraint id deve ser maior que zero quando informado.');
        }

        if ($horarioId <= 0) {
            throw InvalidScheduleConstraintException::single('Constraint horarioId deve ser maior que zero.');
        }

        if (trim($name) === '') {
            throw InvalidScheduleConstraintException::single('Constraint name nao pode ser vazio.');
        }
    }

    private function normalizeWeight(ConstraintLevel $level, int $weight): int
    {
        if ($level->requiresWeight() && $weight < 1) {
            throw InvalidScheduleConstraintException::single('Constraints SOFT exigem weight maior ou igual a 1.');
        }

        if (! $level->requiresWeight() && $weight < 1) {
            throw InvalidScheduleConstraintException::single('Constraints HARD nao aceitam weight menor que 1.');
        }

        return $level === ConstraintLevel::HARD ? 1 : $weight;
    }

    private function validatorFor(ConstraintType $type): ConstraintValidator
    {
        return $this->validators()[$type->value]
            ?? throw InvalidScheduleConstraintException::single(sprintf('Nao existe validator registrado para o tipo %s.', $type->value));
    }

    /**
     * @return array<string, ConstraintValidator>
     */
    private function validators(): array
    {
        return $this->validators ?? [
            ConstraintType::SYNC_SAME_TIMESLOT->value => new SyncSameTimeslotConstraintValidator,
            ConstraintType::MUTUAL_EXCLUSION->value => new MutualExclusionConstraintValidator,
            ConstraintType::TIME_PLACEMENT->value => new TimePlacementConstraintValidator,
        ];
    }
}
