<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;
use Tests\TestCase;

uses(TestCase::class);

it('retries the initial population build when the quality gate rejects the candidate', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    $problem = makeScheduleProblem(
        hardPenalty: 20.0,
        softPenalty: 5.0,
        progress: $progress,
    );

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class, 'Quality gate rejeitou');

    $rejectedPayloads = $progress->payloadsForStage('quality_gate_rejected');

    expect($progress->stages())->toContain('quality_gate_rejected')
        ->and($rejectedPayloads)->toHaveCount($rejectedPayloads[0]['attempt_limit'] ?? 0)
        ->and($rejectedPayloads[0]['max_hard_penalty'])->toBe(12.0);
});

it('reduces the attempt budget adaptively after repeated degraded builds', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    $problem = makeDenseConflictScheduleProblem(
        lessonCount: 10,
        hardPenalty: 20.0,
        softPenalty: 5.0,
        progress: $progress,
    );

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class);

    $firstRunFailFast = count($progress->payloadsForStage('quality_gate_fail_fast'));

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class);

    $allFailFast = $progress->payloadsForStage('quality_gate_fail_fast');
    $secondRunFailFast = count($allFailFast) - $firstRunFailFast;
    $secondRunFirstPayload = $allFailFast[$firstRunFailFast] ?? [];
    $secondRunBottlenecks = $secondRunFirstPayload['initial_population_bottlenecks'] ?? [];

    expect($firstRunFailFast)->toBe($allFailFast[0]['attempt_limit'] ?? 0)
        ->and($secondRunFailFast)->toBeLessThan($firstRunFailFast)
        ->and($secondRunFirstPayload['attempt_limit'] ?? null)->toBeLessThan($allFailFast[0]['attempt_limit'] ?? 0)
        ->and($secondRunBottlenecks['attempt_limit_reduced'] ?? null)->toBeTrue()
        ->and($secondRunBottlenecks['base_attempt_limit'] ?? null)->toBe($allFailFast[0]['attempt_limit'] ?? 0)
        ->and($secondRunBottlenecks['current_attempt_limit'] ?? null)->toBeLessThan($allFailFast[0]['attempt_limit'] ?? 0)
        ->and($secondRunBottlenecks['attempt_limit_reduction_criteria'] ?? [])
        ->toContain('Taxa alta de fail-fast nas ultimas tentativas.');
});

it('accepts the initial candidate when the quality gate metrics are within threshold', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 3.0,
        progress: $progress,
    );

    $individual = $problem->createIndividual();

    expect($individual->count())->toBe(1)
        ->and($progress->stages())->toContain('quality_gate_passed');
});

it('reorders the remaining queue dynamically during construction when conflict pressure changes', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 1.0,
        progress: $progress,
        lessonCount: 3,
        slotCount: 3,
    );

    $problem->createIndividual();

    $completed = $progress->payloadsForStage('grasp_completed');

    expect($completed)->not->toBeEmpty()
        ->and($completed[0]['dynamic_reorders'] ?? 0)->toBeGreaterThan(0)
        ->and($completed[0])->toHaveKey('regret_selections')
        ->and($completed[0]['regret_selections'] ?? null)->toBeInt();
});

it('fails fast before the expensive quality gate repair when hard conflicts are far above the operational limit', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    $problem = makeDenseConflictScheduleProblem(
        lessonCount: 10,
        hardPenalty: 20.0,
        softPenalty: 5.0,
        progress: $progress,
    );

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class, 'Quality gate fail-fast');

    expect($progress->stages())->toContain('quality_gate_fail_fast')
        ->and($progress->stages())->not->toContain('quality_gate_repair_started')
        ->and($progress->payloadsForStage('quality_gate_fail_fast')[0]['initial_population_bottlenecks']['nogoods_learned'] ?? 0)->toBeGreaterThan(0);
});

it('publishes heartbeat stages while repairing the initial quality gate candidate', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    $problem = makeDenseConflictScheduleProblem(
        lessonCount: 2,
        hardPenalty: 20.0,
        softPenalty: 5.0,
        progress: $progress,
    );

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class, 'Quality gate rejeitou');

    expect($progress->stages())->toContain('quality_gate_repair_started')
        ->and($progress->stages())->toContain('quality_gate_repair_finished')
        ->and($progress->payloadsForStage('quality_gate_repairing'))->not->toBeEmpty()
        ->and(collect($progress->payloadsForStage('quality_gate_repairing'))->pluck('repair_event')->filter()->all())->toContain('repair_aborted');
});

it('reuses a previously accepted seed to accelerate the next initial individual', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 1.0,
        progress: $progress,
        lessonCount: 2,
        slotCount: 3,
    );

    $first = $problem->createIndividual();
    $second = $problem->createIndividual();

    expect($first->count())->toBe(2)
        ->and($second->count())->toBe(2)
        ->and($progress->stages())->toContain('seed_reuse_start')
        ->and($progress->stages())->toContain('seed_reuse_passed');
});

function makeScheduleProblem(
    float $hardPenalty,
    float $softPenalty,
    ?ProgressReporterInterface $progress = null,
    int $lessonCount = 1,
    int $slotCount = 1,
): ScheduleProblem {
    $fitnessEvaluator = new FitnessEvaluator(
        weights: new FitnessWeights(),
        rules: [
            makeScheduleProblemFixedHardPenaltyRule($hardPenalty),
            makeScheduleProblemFixedSoftPenaltyRule($softPenalty),
        ],
    );

    return new ScheduleProblem(
        data: makeScheduleData(lessonCount: $lessonCount, slotCount: $slotCount),
        contextBuilder: new EvaluationContextBuilder(),
        fitnessEvaluator: $fitnessEvaluator,
        repairOperator: new GreedyRepairOperator(),
        progress: $progress,
    );
}

function makeDenseConflictScheduleProblem(
    int $lessonCount,
    float $hardPenalty,
    float $softPenalty,
    ?ProgressReporterInterface $progress = null,
): ScheduleProblem {
    $fitnessEvaluator = new FitnessEvaluator(
        weights: new FitnessWeights(),
        rules: [
            makeScheduleProblemFixedHardPenaltyRule($hardPenalty),
            makeScheduleProblemFixedSoftPenaltyRule($softPenalty),
        ],
    );

    return new ScheduleProblem(
        data: makeScheduleData(lessonCount: $lessonCount),
        contextBuilder: new EvaluationContextBuilder(),
        fitnessEvaluator: $fitnessEvaluator,
        repairOperator: new GreedyRepairOperator(),
        progress: $progress,
    );
}

function makeScheduleData(int $lessonCount, int $slotCount = 1): ScheduleData
{
    $lessons = [];
    $lessonIds = [];

    for ($i = 1; $i <= $lessonCount; $i++) {
        $lessons[$i] = new LessonData(
            id: $i,
            professorId: 10,
            classId: 20,
            disciplinaId: 30 + $i,
            requiredSlots: 1,
            weeklyOccurrences: 1,
            requiresConsecutive: false,
        );
        $lessonIds[] = $i;
    }

    $timeSlots = [];

    for ($slot = 1; $slot <= $slotCount; $slot++) {
        $timeSlots[$slot] = new TimeSlot($slot, 1, $slot);
    }

    return new ScheduleData(
        lessons: $lessons,
        professors: [],
        classes: [],
        timeSlots: $timeSlots,
        restrictions: [],
        lessonsByProfessor: [10 => $lessonIds],
        lessonsByClass: [20 => $lessonIds],
        restrictionsByProfessor: [],
        restrictionsByClass: [],
        expectedLoadByLesson: array_fill_keys($lessonIds, 1),
        availableSlotsByProfessor: [10 => array_keys($timeSlots)],
        availableSlotsByClass: [20 => array_keys($timeSlots)],
        totalTimeSlots: count($timeSlots),
        totalLessons: $lessonCount,
        totalProfessors: 1,
        totalClasses: 1,
    );
}

function makeScheduleProblemProgressSpy(): ProgressReporterInterface
{
    return new class () implements ProgressReporterInterface {
        private array $reports = [];

        public function report(array $data): void
        {
            $this->reports[] = $data;
        }

        public function stages(): array
        {
            return array_values(array_filter(array_map(
                static fn (array $payload): ?string => $payload['stage'] ?? null,
                $this->reports,
            )));
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

function makeScheduleProblemFixedHardPenaltyRule(float $penalty): HardRuleInterface
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

function makeScheduleProblemFixedSoftPenaltyRule(float $penalty): SoftRuleInterface
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
