<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Constraints\Evaluators\ConstraintEvaluationPipeline;
use App\Modules\Horarios\Domain\Constraints\Evaluators\MutualExclusionConstraintEvaluator;
use App\Modules\Horarios\Domain\Constraints\Evaluators\SyncSameTimeslotConstraintEvaluator;
use App\Modules\Horarios\Domain\Constraints\Evaluators\TimePlacementConstraintEvaluator;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\HardRules\CustomConstraintHardRule;
use App\Modules\Horarios\Domain\Evaluation\SoftRules\CustomConstraintSoftRule;
use App\Modules\Horarios\Domain\ValueObjects\ClassData;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ProfessorData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

it('applies hard sync penalties to hardPenalty in the current fitness contract', function (): void {
    $pipeline = makeConstraintPipeline();
    $context = makeConstraintContext(
        customConstraints: [
            new CustomConstraintData(
                id: 11,
                name: 'Sincronismo hard',
                description: null,
                type: 'SYNC_SAME_TIMESLOT',
                level: 'HARD',
                weight: 1,
                isActive: true,
                payload: [
                    'left_group' => ['lesson_ids' => [1]],
                    'right_group' => ['lesson_ids' => [2]],
                    'occurrence_mode' => 'ALL',
                    'match_mode' => 'ALL_TO_ALL',
                ],
            ),
        ],
        genes: [
            new Gene(1, 10, 100, 1000, 1, 1, 1),
            new Gene(2, 11, 101, 1001, 1, 2, 1),
        ],
    );

    $result = (new FitnessEvaluator(
        weights: FitnessWeights::default(),
        rules: [
            new CustomConstraintHardRule($pipeline),
            new CustomConstraintSoftRule($pipeline),
        ],
    ))->evaluate($context->cromossomo(), $context);

    $summary = $pipeline->lastSummary();

    expect($result->hardPenalty())->toBe(1.0)
        ->and($result->softPenalty())->toBe(0.0)
        ->and($result->score())->toBeLessThan(50.0)
        ->and($summary)->not->toBeNull()
        ->and($summary?->violationsForConstraint(11))->toHaveCount(1);
});

it('applies soft time placement weight to softPenalty', function (): void {
    $pipeline = makeConstraintPipeline();
    $context = makeConstraintContext(
        customConstraints: [
            new CustomConstraintData(
                id: 21,
                name: 'Primeiro tempo preferido',
                description: null,
                type: 'TIME_PLACEMENT',
                level: 'SOFT',
                weight: 4,
                isActive: true,
                payload: [
                    'target_group' => ['lesson_ids' => [1]],
                    'mode' => 'PREFERRED',
                    'allowed_days' => [1],
                    'allowed_periods' => [1],
                ],
            ),
        ],
        genes: [
            new Gene(1, 10, 100, 1000, 2, 2, 1),
        ],
    );

    $result = (new FitnessEvaluator(
        weights: FitnessWeights::default(),
        rules: [
            new CustomConstraintHardRule($pipeline),
            new CustomConstraintSoftRule($pipeline),
        ],
    ))->evaluate($context->cromossomo(), $context);

    expect($result->hardPenalty())->toBe(0.0)
        ->and($result->softPenalty())->toBe(4.0)
        ->and($result->score())->toBeGreaterThan(50.0)
        ->and($result->score())->toBeLessThan(100.0);
});

it('returns diagnostic details grouped by constraint', function (): void {
    $pipeline = makeConstraintPipeline();
    $context = makeConstraintContext(
        customConstraints: [
            new CustomConstraintData(
                id: 31,
                name: 'Sem sobreposicao',
                description: null,
                type: 'MUTUAL_EXCLUSION',
                level: 'HARD',
                weight: 1,
                isActive: true,
                payload: [
                    'left_group' => ['lesson_ids' => [1]],
                    'right_group' => ['lesson_ids' => [2]],
                ],
            ),
        ],
        genes: [
            new Gene(1, 10, 100, 1000, 1, 3, 1),
            new Gene(2, 11, 101, 1001, 1, 3, 1),
        ],
    );

    $summary = $pipeline->evaluate($context);
    $grouped = $summary->groupedByConstraint();

    expect($summary->hardPenalty())->toBe(1.0)
        ->and($grouped)->toHaveKey(31)
        ->and($grouped[31]['constraint_name'])->toBe('Sem sobreposicao')
        ->and($grouped[31]['effective_penalty'])->toBe(1.0)
        ->and($grouped[31]['violations'])->toHaveCount(1)
        ->and($grouped[31]['violations'][0]['details']['overlap_slots'][0])->toBe([
            'dia' => 1,
            'periodo' => 3,
        ]);
});

function makeConstraintPipeline(): ConstraintEvaluationPipeline
{
    return new ConstraintEvaluationPipeline([
        new SyncSameTimeslotConstraintEvaluator(),
        new MutualExclusionConstraintEvaluator(),
        new TimePlacementConstraintEvaluator(),
    ]);
}

function makeConstraintContext(array $customConstraints, array $genes): EvaluationContext
{
    $chromosome = new Cromossomo($genes);

    return new EvaluationContext(
        cromossomo: $chromosome,
        data: new ScheduleData(
            lessons: [
                1 => new LessonData(1, 10, 100, 1000, 1, 1, false),
                2 => new LessonData(2, 11, 101, 1001, 1, 1, false),
            ],
            professors: [
                10 => new ProfessorData(10, 20),
                11 => new ProfessorData(11, 20),
            ],
            classes: [
                100 => new ClassData(100, 6),
                101 => new ClassData(101, 6),
            ],
            timeSlots: [
                1 => new TimeSlot(1, 1, 1),
                2 => new TimeSlot(2, 1, 2),
                3 => new TimeSlot(3, 1, 3),
                4 => new TimeSlot(4, 2, 2),
            ],
            restrictions: [],
            lessonsByProfessor: [
                10 => [1],
                11 => [2],
            ],
            lessonsByClass: [
                100 => [1],
                101 => [2],
            ],
            restrictionsByProfessor: [],
            restrictionsByClass: [],
            expectedLoadByLesson: [],
            availableSlotsByProfessor: [],
            availableSlotsByClass: [],
            totalTimeSlots: 4,
            totalLessons: 2,
            totalProfessors: 2,
            totalClasses: 2,
            customConstraints: $customConstraints,
        ),
    );
}