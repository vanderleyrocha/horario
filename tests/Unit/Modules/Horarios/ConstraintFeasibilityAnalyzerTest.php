<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Horarios;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Constraints\Analysis\ConstraintFeasibilityAnalyzer;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;
use App\Modules\Horarios\Domain\ValueObjects\ClassData;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ProfessorData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;
use Pest\Expectation;
use RuntimeException;
use Tests\TestCase;

if (! function_exists(__NAMESPACE__ . '\\expect')) {
    function expect(mixed $value = null): Expectation
    {
        return new Expectation($value);
    }
}

uses(TestCase::class);

it('detects invalid references and impossible required windows before the AG starts', function (): void {
    $report = (new ConstraintFeasibilityAnalyzer())->analyze(makeConstraintFeasibilityScheduleData([
        new CustomConstraintData(
            id: 101,
            name: 'Sync com aula inexistente',
            description: null,
            type: 'SYNC_SAME_TIMESLOT',
            level: 'HARD',
            weight: 1,
            isActive: true,
            payload: [
                'left_group' => ['lesson_ids' => [1]],
                'right_group' => ['lesson_ids' => [999]],
                'occurrence_mode' => 'ALL',
                'match_mode' => 'ALL_TO_ALL',
            ],
        ),
        new CustomConstraintData(
            id: 102,
            name: 'Primeiro tempo impossivel',
            description: null,
            type: 'TIME_PLACEMENT',
            level: 'HARD',
            weight: 1,
            isActive: true,
            payload: [
                'target_group' => ['lesson_ids' => [1]],
                'mode' => 'REQUIRED',
                'allowed_days' => [1],
                'allowed_periods' => [1],
            ],
        ),
    ]));

    expect($report->hasBlockingIssues())->toBeTrue()
        ->and(collect($report->blockingIssues())->pluck('code')->all())
        ->toContain('UNKNOWN_LESSON_REFERENCE', 'TIME_PLACEMENT_REQUIRED_IMPOSSIBLE')
        ->and($report->riskContribution())->toBeGreaterThan(0)
        ->and($report->toArray()['grouped_by_constraint'])->toHaveKeys([101, 102]);
});

it('flags suspicious synchronization and first-period pressure as warnings', function (): void {
    $report = (new ConstraintFeasibilityAnalyzer())->analyze(makeConstraintFeasibilityScheduleData([
        new CustomConstraintData(
            id: 201,
            name: 'Sync apertado',
            description: null,
            type: 'SYNC_SAME_TIMESLOT',
            level: 'HARD',
            weight: 1,
            isActive: true,
            payload: [
                'left_group' => ['lesson_ids' => [3]],
                'right_group' => ['lesson_ids' => [4]],
                'occurrence_mode' => 'AT_LEAST_ONE',
                'match_mode' => 'ALL_TO_ALL',
            ],
        ),
        new CustomConstraintData(
            id: 202,
            name: 'Primeiro tempo preferido',
            description: null,
            type: 'TIME_PLACEMENT',
            level: 'SOFT',
            weight: 3,
            isActive: true,
            payload: [
                'target_group' => ['lesson_ids' => [1, 2]],
                'mode' => 'PREFERRED',
                'allowed_days' => [1, 2],
                'allowed_periods' => [1],
            ],
        ),
    ], scenario: 'warning'));

    expect($report->hasBlockingIssues())->toBeFalse()
        ->and($report->hasWarnings())->toBeTrue()
        ->and(collect($report->warnings())->pluck('code')->all())
        ->toContain('LOW_SYNC_FLEXIBILITY', 'FIRST_PERIOD_PRESSURE_HIGH');
});

it('aborts preventive diagnosis when a custom constraint is structurally impossible', function (): void {
    $progress = makeConstraintFeasibilityProgressSpy();
    $problem = new ScheduleProblem(
        data: makeConstraintFeasibilityScheduleData([
            new CustomConstraintData(
                id: 301,
                name: 'Janela inviavel',
                description: null,
                type: 'TIME_PLACEMENT',
                level: 'HARD',
                weight: 1,
                isActive: true,
                payload: [
                    'target_group' => ['lesson_ids' => [1]],
                    'mode' => 'REQUIRED',
                    'allowed_days' => [1],
                    'allowed_periods' => [1],
                ],
            ),
        ]),
        contextBuilder: new EvaluationContextBuilder(),
        fitnessEvaluator: new FitnessEvaluator(
            weights: new FitnessWeights(),
            rules: [
                makeConstraintFeasibilityFixedHardPenaltyRule(0.0),
                makeConstraintFeasibilityFixedSoftPenaltyRule(0.0),
            ],
        ),
        repairOperator: new GreedyRepairOperator(),
        progress: $progress,
    );

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class, 'Constraint 301 exige 2 ocorrencias da aula 1 dentro da janela');

    $diagnosisPayload = $progress->payloadsForStage('diagnosis')[0] ?? [];

    expect($diagnosisPayload['constraint_infeasibilities'] ?? [])->not->toBeEmpty()
        ->and(($diagnosisPayload['constraint_infeasibilities'][0]['code'] ?? null))->toBe('TIME_PLACEMENT_REQUIRED_IMPOSSIBLE');
});

function makeConstraintFeasibilityScheduleData(array $constraints, string $scenario = 'default'): ScheduleData
{
    $timeSlots = [
        1 => new TimeSlot(1, 1, 1),
        2 => new TimeSlot(2, 1, 2),
        3 => new TimeSlot(3, 2, 1),
        4 => new TimeSlot(4, 2, 2),
        5 => new TimeSlot(5, 3, 1),
        6 => new TimeSlot(6, 3, 2),
    ];

    $lessons = [
        1 => new LessonData(1, 10, 20, 31, 1, 2, false),
        2 => new LessonData(2, 10, 20, 32, 1, 1, false),
        3 => new LessonData(3, 11, 21, 33, 1, 1, false),
        4 => new LessonData(4, 12, 22, 34, 1, 1, false),
    ];

    $availableSlotsByProfessor = [
        10 => $scenario === 'warning' ? [1, 3] : [1, 2, 3, 4, 5, 6],
        11 => [1],
        12 => [1],
    ];

    $availableSlotsByClass = [
        20 => $scenario === 'warning' ? [1, 3] : [1, 2, 3, 4, 5, 6],
        21 => [1],
        22 => [1],
    ];

    return new ScheduleData(
        lessons: $lessons,
        professors: [
            10 => new ProfessorData(10, 20),
            11 => new ProfessorData(11, 20),
            12 => new ProfessorData(12, 20),
        ],
        classes: [
            20 => new ClassData(20, 6),
            21 => new ClassData(21, 6),
            22 => new ClassData(22, 6),
        ],
        timeSlots: $timeSlots,
        restrictions: [],
        lessonsByProfessor: [
            10 => [1, 2],
            11 => [3],
            12 => [4],
        ],
        lessonsByClass: [
            20 => [1, 2],
            21 => [3],
            22 => [4],
        ],
        restrictionsByProfessor: [],
        restrictionsByClass: [],
        expectedLoadByLesson: [1 => 2, 2 => 1, 3 => 1, 4 => 1],
        availableSlotsByProfessor: $availableSlotsByProfessor,
        availableSlotsByClass: $availableSlotsByClass,
        totalTimeSlots: count($timeSlots),
        totalLessons: count($lessons),
        totalProfessors: 3,
        totalClasses: 3,
        customConstraints: $constraints,
    );
}

function makeConstraintFeasibilityProgressSpy(): object
{
    return new class () implements ProgressReporterInterface {
        private array $reports = [];

        public function report(array $data): void
        {
            $this->reports[] = $data;
        }

        public function payloadsForStage(string $stage): array
        {
            return array_values(array_filter(
                $this->reports,
                static fn (array $payload): bool => ($payload['stage'] ?? null) === $stage,
            ));
        }
    };
}

function makeConstraintFeasibilityFixedHardPenaltyRule(float $penalty): HardRuleInterface
{
    return new class ($penalty) implements HardRuleInterface {
        public function __construct(private readonly float $penalty)
        {
        }

        public function evaluate(EvaluationContext $context): RuleResult
        {
            return new RuleResult($this->penalty, self::class);
        }

        public function isHard(): bool
        {
            return true;
        }
    };
}

function makeConstraintFeasibilityFixedSoftPenaltyRule(float $penalty): SoftRuleInterface
{
    return new class ($penalty) implements SoftRuleInterface {
        public function __construct(private readonly float $penalty)
        {
        }

        public function evaluate(EvaluationContext $context): RuleResult
        {
            return new RuleResult($this->penalty, self::class);
        }

        public function isHard(): bool
        {
            return false;
        }
    };
}
