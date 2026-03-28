<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

it('repairs conflicts considering the full duration of a gene', function (): void {
    $chromosome = new Cromossomo([
        // Gene with duration=2 (occupies periods 1 and 2)
        new Gene(1, 1, 1, 1, 1, 1, 2),
        // Conflicts only at period 2 (same professor)
        new Gene(2, 1, 2, 1, 1, 2, 1),
    ]);

    $repair = new GreedyRepairOperator();
    $repaired = $repair->repair($chromosome, makeScheduleData());

    $genes = $repaired->genes();

    // The duration-2 lesson must be relocated to start at period 3,
    // otherwise period 2 would still conflict with the second gene.
    expect($genes[0]->periodoDia())->toBe(3)
        ->and($genes[0]->duracaoTempos())->toBe(2)
        ->and($genes[1]->periodoDia())->toBe(2);
});

function makeScheduleData(): ScheduleData
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

