<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../Support/PestIdeStubs.php';

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\DistributionRule;
use App\Modules\Horarios\Domain\ValueObjects\ClassData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ProfessorData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

it('does not penalize lessons with a single weekly occurrence', function (): void {
    $rule = new DistributionRule();

    $chromosome = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
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
            ],
            professors: [
                1 => new ProfessorData(1, 20),
            ],
            classes: [
                1 => new ClassData(1, 6),
            ],
            timeSlots: [
                1 => new TimeSlot(1, 1, 1),
            ],
            restrictions: [],
            lessonsByProfessor: [],
            lessonsByClass: [],
            restrictionsByProfessor: [],
            restrictionsByClass: [],
            expectedLoadByLesson: [],
            availableSlotsByProfessor: [],
            availableSlotsByClass: [],
            totalTimeSlots: 1,
            totalLessons: 1,
            totalProfessors: 1,
            totalClasses: 1,
        ),
    );

    $result = $rule->evaluate($context);

    $this->assertSame(0.0, $result->penalty());
});

it('penalizes lessons with two weekly occurrences allocated in a single day', function (): void {
    $rule = new DistributionRule();

    $chromosome = new Cromossomo([
        new Gene(2, 2, 2, 2, 1, 2, 1),
        new Gene(2, 2, 2, 2, 1, 3, 1),
    ]);

    $context = new EvaluationContext(
        cromossomo: $chromosome,
        data: new ScheduleData(
            lessons: [
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
                2 => new ProfessorData(2, 20),
            ],
            classes: [
                2 => new ClassData(2, 6),
            ],
            timeSlots: [
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
            totalTimeSlots: 2,
            totalLessons: 1,
            totalProfessors: 1,
            totalClasses: 1,
        ),
    );

    $result = $rule->evaluate($context);

    $this->assertSame(1.0, $result->penalty());
});

it('does not penalize lessons with two weekly occurrences distributed across different days', function (): void {
    $rule = new DistributionRule();

    $chromosome = new Cromossomo([
        new Gene(2, 2, 2, 2, 1, 2, 1),
        new Gene(2, 2, 2, 2, 2, 3, 1),
    ]);

    $context = new EvaluationContext(
        cromossomo: $chromosome,
        data: new ScheduleData(
            lessons: [
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
                2 => new ProfessorData(2, 20),
            ],
            classes: [
                2 => new ClassData(2, 6),
            ],
            timeSlots: [
                2 => new TimeSlot(2, 1, 2),
                3 => new TimeSlot(3, 2, 1),
            ],
            restrictions: [],
            lessonsByProfessor: [],
            lessonsByClass: [],
            restrictionsByProfessor: [],
            restrictionsByClass: [],
            expectedLoadByLesson: [],
            availableSlotsByProfessor: [],
            availableSlotsByClass: [],
            totalTimeSlots: 2,
            totalLessons: 1,
            totalProfessors: 1,
            totalClasses: 1,
        ),
    );

    $result = $rule->evaluate($context);

    $this->assertSame(0.0, $result->penalty());
});
