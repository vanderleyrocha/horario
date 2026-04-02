<?php

declare(strict_types=1);

use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncMatchMode;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncOccurrenceMode;
use App\Modules\Horarios\Domain\Constraints\Enums\TimePlacementMode;
use App\Modules\Horarios\Domain\Constraints\Exceptions\InvalidScheduleConstraintException;
use App\Modules\Horarios\Domain\Constraints\Validators\MutualExclusionConstraintValidator;
use App\Modules\Horarios\Domain\Constraints\Validators\SyncSameTimeslotConstraintValidator;
use App\Modules\Horarios\Domain\Constraints\Validators\TimePlacementConstraintValidator;

it('exposes the initial custom constraint enums with the expected values', function (): void {
    expect(array_column(ConstraintType::cases(), 'value'))->toBe([
        'SYNC_SAME_TIMESLOT',
        'MUTUAL_EXCLUSION',
        'TIME_PLACEMENT',
    ])->and(array_column(SyncOccurrenceMode::cases(), 'value'))->toBe([
        'ALL',
        'AT_LEAST_ONE',
    ])->and(array_column(SyncMatchMode::cases(), 'value'))->toBe([
        'ALL_TO_ALL',
        'FIRST_WITH_FIRST',
    ])->and(array_column(TimePlacementMode::cases(), 'value'))->toBe([
        'REQUIRED',
        'PREFERRED',
        'FORBIDDEN',
    ])->and(ConstraintLevel::HARD->requiresWeight())->toBeFalse()
        ->and(ConstraintLevel::SOFT->requiresWeight())->toBeTrue();
});

it('normalizes a valid sync payload through the semantic validator', function (): void {
    $payload = (new SyncSameTimeslotConstraintValidator())->validate([
        'left_group' => ['lesson_ids' => [10, 11]],
        'right_group' => ['lesson_ids' => [20, 21]],
        'occurrence_mode' => 'AT_LEAST_ONE',
        'match_mode' => 'FIRST_WITH_FIRST',
    ]);

    expect($payload)->toBe([
        'left_group' => ['lesson_ids' => [10, 11]],
        'right_group' => ['lesson_ids' => [20, 21]],
        'occurrence_mode' => 'AT_LEAST_ONE',
        'match_mode' => 'FIRST_WITH_FIRST',
    ]);
});

it('rejects intersecting groups in sync and mutual exclusion validators', function (): void {
    expect(fn (): array => (new SyncSameTimeslotConstraintValidator())->validate([
        'left_group' => ['lesson_ids' => [10, 11]],
        'right_group' => ['lesson_ids' => [11, 20]],
        'occurrence_mode' => 'ALL',
        'match_mode' => 'ALL_TO_ALL',
    ]))->toThrow(InvalidScheduleConstraintException::class, 'interseccao')
        ->and(fn (): array => (new MutualExclusionConstraintValidator())->validate([
            'left_group' => ['lesson_ids' => [30, 31]],
            'right_group' => ['lesson_ids' => [31, 32]],
        ]))->toThrow(InvalidScheduleConstraintException::class, 'interseccao');
});

it('rejects a time placement payload without a valid temporal window', function (): void {
    expect(fn (): array => (new TimePlacementConstraintValidator())->validate([
        'target_group' => ['lesson_ids' => [50]],
        'mode' => 'PREFERRED',
    ]))->toThrow(InvalidScheduleConstraintException::class, 'ao menos um dia ou periodo');
});

it('normalizes a valid time placement payload through the semantic validator', function (): void {
    $payload = (new TimePlacementConstraintValidator())->validate([
        'target_group' => ['lesson_ids' => [50, 51]],
        'mode' => 'FORBIDDEN',
        'allowed_days' => [1, 3],
        'allowed_periods' => [5, 6],
    ]);

    expect($payload)->toBe([
        'target_group' => ['lesson_ids' => [50, 51]],
        'mode' => 'FORBIDDEN',
        'allowed_days' => [1, 3],
        'allowed_periods' => [5, 6],
    ]);
});
