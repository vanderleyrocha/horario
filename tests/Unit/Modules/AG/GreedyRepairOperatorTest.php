<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

it('repairs conflicts considering the full duration of a gene', function (): void {
    $chromosome = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 2),
        new Gene(2, 1, 2, 1, 1, 2, 1),
    ]);

    $repair = new GreedyRepairOperator;
    $repaired = $repair->repair($chromosome, makeRelocationScheduleData());

    $genes = $repaired->genes();

    expect($genes[0]->periodoDia())->toBe(3)
        ->and($genes[0]->duracaoTempos())->toBe(2)
        ->and($genes[1]->periodoDia())->toBe(2);
});

it('uses slot swapping when relocation alone cannot fix the conflict', function (): void {
    $chromosome = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
        new Gene(2, 1, 2, 1, 1, 1, 1),
        new Gene(3, 3, 1, 1, 1, 2, 1),
    ]);

    $repair = new GreedyRepairOperator;
    $repaired = $repair->repair($chromosome, makeSwapScheduleData());
    $telemetry = $repair->lastTelemetry();
    $genes = $repaired->genes();

    expect($genes[0]->periodoDia())->toBe(2)
        ->and($genes[2]->periodoDia())->toBe(1)
        ->and($telemetry['swaps'])->toBeGreaterThan(0)
        ->and($telemetry['invalid_genes_after'])->toBe(0);
});

it('uses a local rebuild when neither relocation nor swap is enough', function (): void {
    $chromosome = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
        new Gene(2, 1, 2, 1, 1, 1, 1),
        new Gene(3, 2, 1, 1, 1, 2, 1),
    ]);

    $repair = new GreedyRepairOperator;
    $repaired = $repair->repair($chromosome, makeLocalRebuildScheduleData());
    $telemetry = $repair->lastTelemetry();
    $genes = $repaired->genes();

    expect($genes[0]->periodoDia())->toBe(2)
        ->and($genes[2]->periodoDia())->toBe(3)
        ->and($telemetry['local_rebuilds'])->toBeGreaterThan(0)
        ->and($telemetry['invalid_genes_after'])->toBe(0);
});

it('captures compact repair telemetry with hard penalty reduction per pass', function (): void {
    $chromosome = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
        new Gene(2, 1, 2, 1, 1, 1, 1),
        new Gene(3, 2, 1, 1, 1, 2, 1),
    ]);

    $probePenalties = [24.0, 12.0, 0.0];
    $repair = new GreedyRepairOperator;

    $repair->repair(
        $chromosome,
        makeLocalRebuildScheduleData(),
        function () use (&$probePenalties): array {
            $hardPenalty = array_shift($probePenalties) ?? 0.0;

            return [
                'hard_penalty' => $hardPenalty,
                'soft_penalty' => 5.0,
                'score' => max(0.0, 49.999 - $hardPenalty),
            ];
        }
    );

    $telemetry = $repair->lastTelemetry();
    $firstPass = $telemetry['passes'][0];

    expect($telemetry['hard_penalty_before'])->toBe(24.0)
        ->and($telemetry['hard_penalty_after'])->toBe(0.0)
        ->and($firstPass['hard_penalty_delta'])->toBe(12.0)
        ->and($telemetry['passes'])->toHaveCount(1);
});

it('aborts the repair early when no progress is made under an explicit no-progress budget', function (): void {
    $chromosome = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
        new Gene(2, 1, 2, 1, 1, 1, 1),
    ]);

    $events = [];
    $repair = new GreedyRepairOperator;

    $repair->repair(
        $chromosome,
        makeSwapScheduleData(),
        static fn (): array => [
            'hard_penalty' => 20.0,
            'soft_penalty' => 5.0,
            'score' => 0.0,
        ],
        function (array $payload) use (&$events): void {
            $events[] = $payload['event'] ?? null;
        },
        [
            'max_passes_without_progress' => 1,
        ]
    );

    $telemetry = $repair->lastTelemetry();

    expect($telemetry['aborted'])->toBeTrue()
        ->and($telemetry['abort_reason'])->toBe('no_progress')
        ->and($telemetry['passes_without_progress'])->toBe(1)
        ->and($events)->toContain('repair_aborted');
});

function makeRelocationScheduleData(): ScheduleData
{
    $timeSlots = [
        1 => new TimeSlot(1, 1, 1),
        2 => new TimeSlot(2, 1, 2),
        3 => new TimeSlot(3, 1, 3),
        4 => new TimeSlot(4, 1, 4),
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
            1 => [1, 2, 3, 4],
        ],
        availableSlotsByClass: [
            1 => [1, 2, 3, 4],
            2 => [1, 2, 3, 4],
        ],
        totalTimeSlots: 4,
        totalLessons: 0,
        totalProfessors: 0,
        totalClasses: 0
    );
}

function makeSwapScheduleData(): ScheduleData
{
    $timeSlots = [
        1 => new TimeSlot(1, 1, 1),
        2 => new TimeSlot(2, 1, 2),
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
            1 => [1, 2],
            3 => [1, 2],
        ],
        availableSlotsByClass: [
            1 => [1, 2],
            2 => [1, 2],
        ],
        totalTimeSlots: 2,
        totalLessons: 0,
        totalProfessors: 0,
        totalClasses: 0
    );
}

function makeLocalRebuildScheduleData(): ScheduleData
{
    $timeSlots = [
        1 => new TimeSlot(1, 1, 1),
        2 => new TimeSlot(2, 1, 2),
        3 => new TimeSlot(3, 1, 3),
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
            1 => [1, 2],
            2 => [2, 3],
        ],
        availableSlotsByClass: [
            1 => [1, 2, 3],
            2 => [1],
        ],
        totalTimeSlots: 3,
        totalLessons: 0,
        totalProfessors: 0,
        totalClasses: 0
    );
}
