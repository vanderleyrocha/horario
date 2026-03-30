<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\HardRules\MandatoryBlockViolationRule;
use App\Modules\Horarios\Domain\ValueObjects\ClassData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ProfessorData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

it('ignores same-day gaps for lessons that do not require consecutive blocks', function (): void {
    $rule = new MandatoryBlockViolationRule();

    $chromosome = new Cromossomo([
        new Gene(1, 10, 20, 30, 1, 1, 1),
        new Gene(1, 10, 20, 30, 1, 3, 1),
        new Gene(2, 11, 21, 31, 2, 1, 1),
        new Gene(2, 11, 21, 31, 2, 3, 1),
    ]);

    $context = new EvaluationContext(
        cromossomo: $chromosome,
        data: new ScheduleData(
            lessons: [
                1 => new LessonData(
                    id: 1,
                    professorId: 10,
                    classId: 20,
                    disciplinaId: 30,
                    requiredSlots: 1,
                    weeklyOccurrences: 2,
                    requiresConsecutive: false,
                ),
                2 => new LessonData(
                    id: 2,
                    professorId: 11,
                    classId: 21,
                    disciplinaId: 31,
                    requiredSlots: 1,
                    weeklyOccurrences: 2,
                    requiresConsecutive: true,
                ),
            ],
            professors: [
                10 => new ProfessorData(10, 20),
                11 => new ProfessorData(11, 20),
            ],
            classes: [
                20 => new ClassData(20, 7),
                21 => new ClassData(21, 7),
            ],
            timeSlots: [
                1 => new TimeSlot(1, 1, 1),
                2 => new TimeSlot(2, 1, 2),
                3 => new TimeSlot(3, 1, 3),
            ],
            restrictions: [],
            lessonsByProfessor: [],
            lessonsByClass: [],
            restrictionsByProfessor: [],
            restrictionsByClass: [],
            expectedLoadByLesson: [],
            availableSlotsByProfessor: [],
            availableSlotsByClass: [],
            totalTimeSlots: 3,
            totalLessons: 2,
            totalProfessors: 2,
            totalClasses: 2,
        ),
    );

    $result = $rule->evaluate($context);

    expect($result->penalty())->toBe(1.0);
});
