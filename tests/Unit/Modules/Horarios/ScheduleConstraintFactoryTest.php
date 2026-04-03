<?php

declare(strict_types=1);

use App\Modules\Horarios\Domain\Constraints\DTO\CreateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\DTO\ScheduleConstraintData;
use App\Modules\Horarios\Domain\Constraints\DTO\UpdateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Entities\SyncSameTimeslotConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\TimePlacementConstraint;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;
use App\Modules\Horarios\Domain\Constraints\Factories\ScheduleConstraintFactory;

it('builds a typed constraint from create input through the factory', function (): void {
    $factory = new ScheduleConstraintFactory;

    $constraint = $factory->fromCreateInput(new CreateScheduleConstraintInput(
        horarioId: 12,
        name: 'Sincronismo de laboratorios',
        description: 'Sincroniza duas aulas.',
        type: ConstraintType::SYNC_SAME_TIMESLOT,
        level: ConstraintLevel::HARD,
        weight: 9,
        isActive: true,
        payload: [
            'left_group' => ['lesson_ids' => [10, 11]],
            'right_group' => ['lesson_ids' => [20, 21]],
            'occurrence_mode' => 'ALL',
            'match_mode' => 'ALL_TO_ALL',
        ],
    ));

    expect($constraint)->toBeInstanceOf(SyncSameTimeslotConstraint::class)
        ->and($constraint->weight())->toBe(1)
        ->and($constraint->payload()['match_mode'])->toBe('ALL_TO_ALL');
});

it('builds a typed constraint from persisted data through the factory', function (): void {
    $factory = new ScheduleConstraintFactory;

    $constraint = $factory->fromData(new ScheduleConstraintData(
        id: 7,
        horarioId: 12,
        name: 'Janelas preferidas',
        description: null,
        type: ConstraintType::TIME_PLACEMENT,
        level: ConstraintLevel::SOFT,
        weight: 3,
        isActive: true,
        payload: [
            'target_group' => ['lesson_ids' => [50]],
            'mode' => 'PREFERRED',
            'allowed_days' => [1, 3],
            'allowed_periods' => [1, 2],
        ],
    ));

    expect($constraint)->toBeInstanceOf(TimePlacementConstraint::class)
        ->and($constraint->payload()['allowed_days'])->toBe([1, 3]);
});

it('builds a typed constraint from update input when the persisted type is supplied', function (): void {
    $factory = new ScheduleConstraintFactory;

    $constraint = $factory->fromUpdateInput(
        new UpdateScheduleConstraintInput(
            id: 9,
            horarioId: 12,
            name: 'Bloqueio de tempos',
            description: null,
            level: ConstraintLevel::SOFT,
            weight: 5,
            isActive: false,
            payload: [
                'target_group' => ['lesson_ids' => [80, 81]],
                'mode' => 'FORBIDDEN',
                'allowed_periods' => [4, 5],
            ],
        ),
        ConstraintType::TIME_PLACEMENT,
    );

    expect($constraint)->toBeInstanceOf(TimePlacementConstraint::class)
        ->and($constraint->isActive())->toBeFalse()
        ->and($constraint->payload()['allowed_periods'])->toBe([4, 5]);
});

it('rejects invalid payloads with clear messages', function (): void {
    $factory = new ScheduleConstraintFactory;

    expect(fn (): SyncSameTimeslotConstraint => $factory->fromCreateInput(new CreateScheduleConstraintInput(
        horarioId: 12,
        name: 'Sync invalida',
        description: null,
        type: ConstraintType::SYNC_SAME_TIMESLOT,
        level: ConstraintLevel::SOFT,
        weight: 4,
        isActive: true,
        payload: [
            'left_group' => ['lesson_ids' => [10]],
            'right_group' => ['lesson_ids' => [20]],
            'occurrence_mode' => 'ALL',
        ],
    )))->toThrow(InvalidScheduleConstraintException::class, 'match_mode');
});

it('rejects soft constraints with invalid weight', function (): void {
    $factory = new ScheduleConstraintFactory;

    expect(fn (): TimePlacementConstraint => $factory->fromCreateInput(new CreateScheduleConstraintInput(
        horarioId: 12,
        name: 'Weight invalido',
        description: null,
        type: ConstraintType::TIME_PLACEMENT,
        level: ConstraintLevel::SOFT,
        weight: 0,
        isActive: true,
        payload: [
            'target_group' => ['lesson_ids' => [50]],
            'mode' => 'PREFERRED',
            'allowed_days' => [1],
        ],
    )))->toThrow(InvalidScheduleConstraintException::class, 'SOFT');
});
