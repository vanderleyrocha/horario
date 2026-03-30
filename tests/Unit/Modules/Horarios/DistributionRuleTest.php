<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\DistributionRule;
use App\Modules\Horarios\Domain\ValueObjects\ClassData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ProfessorData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

it('only penalizes distribution when lesson has at least two weekly occurrences', function (): void {
    $rule = new DistributionRule;

    $chromosome = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
        new Gene(2, 2, 2, 2, 1, 2, 1),
        new Gene(2, 2, 2, 2, 1, 3, 1),
    ]);

    $context = new EvaluationContext(
        cromossomo: $chromosome,
        data: new ScheduleData(
            lessons: [
                1 => new LessonData(
                    id: 1,
                    professorId: 1,
                    classId: 1,
                    disciplinaId: 1,
                    requiredSlots: 1,
                    weeklyOccurrences: 1,
                    requiresConsecutive: false,
                ),
                2 => new LessonData(
                    id: 2,
                    professorId: 2,
                    classId: 2,
                    disciplinaId: 2,
                    requiredSlots: 1,
                    weeklyOccurrences: 2,
                    requiresConsecutive: false,
                ),
            ],
            professors: [
                1 => new ProfessorData(1, 20),
                2 => new ProfessorData(2, 20),
            ],
            classes: [
                1 => new ClassData(1, 6),
                2 => new ClassData(2, 6),
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
