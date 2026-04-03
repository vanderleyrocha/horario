<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Constraints\Repair\CustomConstraintRepairExtension;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

it('adds repair targets for violated custom constraints', function (): void {
    $extension = new CustomConstraintRepairExtension();
    $chromosome = new Cromossomo([
        new Gene(10, 1, 1, 1, 1, 1, 1),
        new Gene(20, 2, 2, 1, 1, 1, 1),
        new Gene(30, 3, 3, 1, 1, 3, 1),
    ]);

    $targets = $extension->augmentRepairTargets($chromosome, makeScheduleDataWithCustomConstraints());

    expect($targets)
        ->toHaveKeys([0, 1, 2])
        ->and($targets[0]['violations'])->toContain('custom_mutual_exclusion')
        ->and($targets[1]['violations'])->toContain('custom_mutual_exclusion')
        ->and($targets[2]['violations'])->toContain('custom_time_placement');
});

it('filters candidate slots according to time placement constraints', function (): void {
    $extension = new CustomConstraintRepairExtension();
    $data = makeScheduleDataWithCustomConstraints();
    $gene = new Gene(30, 3, 3, 1, 1, 1, 1);

    $filtered = $extension->filterCandidateStartSlots($gene, $data, [1, 2, 3]);

    expect($filtered)->toBe([2]);
});

it('counts target violations only for custom-constraint repair targets', function (): void {
    $extension = new CustomConstraintRepairExtension();
    $chromosome = new Cromossomo([
        new Gene(10, 1, 1, 1, 1, 1, 1),
        new Gene(20, 2, 2, 1, 1, 1, 1),
        new Gene(30, 3, 3, 1, 1, 3, 1),
    ]);

    $data = makeScheduleDataWithCustomConstraints();

    $customViolations = $extension->countTargetViolations(
        $chromosome,
        2,
        ['custom_time_placement'],
        $data,
    );

    $nonCustomViolations = $extension->countTargetViolations(
        $chromosome,
        2,
        ['overlap'],
        $data,
    );

    expect($customViolations)->toBeGreaterThan(0)
        ->and($nonCustomViolations)->toBe(0);
});

function makeScheduleDataWithCustomConstraints(): ScheduleData
{
    $timeSlots = [
        1 => new TimeSlot(1, 1, 1),
        2 => new TimeSlot(2, 2, 1),
        3 => new TimeSlot(3, 2, 2),
    ];

    return new ScheduleData(
        lessons: [],
        professors: [],
        classes: [],
        timeSlots: $timeSlots,
        restrictions: [],
        lessonsByProfessor: [],
        lessonsByClass: [],
        restrictionsByProfessor: [],
        restrictionsByClass: [],
        expectedLoadByLesson: [],
        availableSlotsByProfessor: [
            1 => [1, 2, 3],
            2 => [1, 2, 3],
            3 => [1, 2, 3],
        ],
        availableSlotsByClass: [
            1 => [1, 2, 3],
            2 => [1, 2, 3],
            3 => [1, 2, 3],
        ],
        totalTimeSlots: 3,
        totalLessons: 3,
        totalProfessors: 3,
        totalClasses: 3,
        customConstraints: [
            new CustomConstraintData(
                id: 1,
                name: 'Nao sobrepor 10 e 20',
                description: null,
                type: 'MUTUAL_EXCLUSION',
                level: 'HARD',
                weight: 1,
                isActive: true,
                payload: [
                    'left_group' => ['lesson_ids' => [10]],
                    'right_group' => ['lesson_ids' => [20]],
                ],
            ),
            new CustomConstraintData(
                id: 2,
                name: 'Aula 30 apenas no dia 2 periodo 1',
                description: null,
                type: 'TIME_PLACEMENT',
                level: 'HARD',
                weight: 1,
                isActive: true,
                payload: [
                    'target_group' => ['lesson_ids' => [30]],
                    'mode' => 'REQUIRED',
                    'allowed_days' => [2],
                    'allowed_periods' => [1],
                ],
            ),
        ],
    );
}
