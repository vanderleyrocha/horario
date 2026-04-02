<?php

declare(strict_types=1);

use App\Modules\Horarios\Domain\Constraints\DTO\ScheduleConstraintData;
use App\Modules\Horarios\Domain\Constraints\Entities\MutualExclusionConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\SyncSameTimeslotConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\TimePlacementConstraint;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncMatchMode;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncOccurrenceMode;
use App\Modules\Horarios\Domain\Constraints\Enums\TimePlacementMode;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\ConstraintTargetGroup;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\TimePlacementWindow;

it('builds the initial custom constraint domain types with strong typing', function (): void {
    $sync = new SyncSameTimeslotConstraint(
        id: 1,
        horarioId: 10,
        name: 'Sincronizar grupos',
        description: 'Aulas devem acontecer juntas.',
        level: ConstraintLevel::HARD,
        weight: 1,
        isActive: true,
        leftGroup: new ConstraintTargetGroup([101, 102]),
        rightGroup: new ConstraintTargetGroup([201, 202]),
        occurrenceMode: SyncOccurrenceMode::ALL,
        matchMode: SyncMatchMode::ALL_TO_ALL,
    );

    $exclusion = new MutualExclusionConstraint(
        id: 2,
        horarioId: 10,
        name: 'Nao coexistir',
        description: null,
        level: ConstraintLevel::SOFT,
        weight: 4,
        isActive: true,
        leftGroup: new ConstraintTargetGroup([301]),
        rightGroup: new ConstraintTargetGroup([401, 402]),
    );

    $placement = new TimePlacementConstraint(
        id: 3,
        horarioId: 10,
        name: 'Primeiros tempos',
        description: null,
        level: ConstraintLevel::SOFT,
        weight: 3,
        isActive: true,
        targetGroup: new ConstraintTargetGroup([501]),
        mode: TimePlacementMode::PREFERRED,
        window: new TimePlacementWindow(days: [1, 3], periods: [1, 2]),
    );

    $data = new ScheduleConstraintData(
        id: 99,
        horarioId: 10,
        name: 'DTO constraint',
        description: 'Descricao',
        type: ConstraintType::TIME_PLACEMENT,
        level: ConstraintLevel::SOFT,
        weight: 2,
        isActive: true,
        payload: $placement->payload(),
    );

    expect($sync->type())->toBe(ConstraintType::SYNC_SAME_TIMESLOT)
        ->and($sync->payload()['occurrence_mode'])->toBe('ALL')
        ->and($exclusion->type())->toBe(ConstraintType::MUTUAL_EXCLUSION)
        ->and($placement->payload()['mode'])->toBe('PREFERRED')
        ->and($data->toArray()['type'])->toBe('TIME_PLACEMENT')
        ->and($data->toArray()['payload']['allowed_days'])->toBe([1, 3]);
});

it('rejects intersecting lesson groups in explicit constraint entities', function (): void {
    expect(fn (): MutualExclusionConstraint => new MutualExclusionConstraint(
        id: null,
        horarioId: 10,
        name: 'Invalida',
        description: null,
        level: ConstraintLevel::HARD,
        weight: 1,
        isActive: true,
        leftGroup: new ConstraintTargetGroup([1, 2]),
        rightGroup: new ConstraintTargetGroup([2, 3]),
    ))->toThrow(InvalidArgumentException::class);
});
