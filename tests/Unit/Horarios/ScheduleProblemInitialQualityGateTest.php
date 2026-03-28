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
    $progress = new ScheduleProblemProgressSpy;
    $problem = makeScheduleProblem(
        hardPenalty: 20.0,
        softPenalty: 5.0,
        progress: $progress
    );

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class, 'Quality gate rejeitou');

    expect($progress->stages())->toContain('quality_gate_rejected')
        ->and($progress->payloadsForStage('quality_gate_rejected'))->toHaveCount(12)
        ->and($progress->payloadsForStage('quality_gate_rejected')[0]['max_hard_penalty'])->toBe(12.0);
});

it('accepts the initial candidate when the quality gate metrics are within threshold', function (): void {
    $progress = new ScheduleProblemProgressSpy;
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 3.0,
        progress: $progress
    );

    $individual = $problem->createIndividual();

    expect($individual->count())->toBe(1)
        ->and($progress->stages())->toContain('quality_gate_passed');
});

function makeScheduleProblem(float $hardPenalty, float $softPenalty, ?ProgressReporterInterface $progress = null): ScheduleProblem
{
    $lesson = new LessonData(
        id: 1,
        professorId: 10,
        classId: 20,
        disciplinaId: 30,
        requiredSlots: 1,
        weeklyOccurrences: 1,
        requiresConsecutive: false
    );

    $scheduleData = new ScheduleData(
        lessons: [1 => $lesson],
        professors: [],
        classes: [],
        timeSlots: [1 => new TimeSlot(1, 1, 1)],
        restrictions: [],
        lessonsByProfessor: [10 => [1]],
        lessonsByClass: [20 => [1]],
        restrictionsByProfessor: [],
        restrictionsByClass: [],
        expectedLoadByLesson: [1 => 1],
        availableSlotsByProfessor: [10 => [1]],
        availableSlotsByClass: [20 => [1]],
        totalTimeSlots: 1,
        totalLessons: 1,
        totalProfessors: 1,
        totalClasses: 1
    );

    $fitnessEvaluator = new FitnessEvaluator(
        weights: new FitnessWeights,
        rules: [
            new ScheduleProblemFixedHardPenaltyRule($hardPenalty),
            new ScheduleProblemFixedSoftPenaltyRule($softPenalty),
        ]
    );

    return new ScheduleProblem(
        data: $scheduleData,
        contextBuilder: new EvaluationContextBuilder,
        fitnessEvaluator: $fitnessEvaluator,
        repairOperator: new GreedyRepairOperator,
        progress: $progress
    );
}

final class ScheduleProblemProgressSpy implements ProgressReporterInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $reports = [];

    public function report(array $data): void
    {
        $this->reports[] = $data;
    }

    public function stages(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $payload): ?string => $payload['stage'] ?? null,
            $this->reports
        )));
    }

    public function payloadsForStage(string $stage): array
    {
        return array_values(array_filter(
            $this->reports,
            static fn (array $payload): bool => ($payload['stage'] ?? null) === $stage
        ));
    }
}

final class ScheduleProblemFixedHardPenaltyRule implements HardRuleInterface
{
    public function __construct(private readonly float $penalty) {}

    public function evaluate(EvaluationContext $context): RuleResult
    {
        return new RuleResult($this->penalty, self::class);
    }

    public function isHard(): bool
    {
        return true;
    }
}

final class ScheduleProblemFixedSoftPenaltyRule implements SoftRuleInterface
{
    public function __construct(private readonly float $penalty) {}

    public function evaluate(EvaluationContext $context): RuleResult
    {
        return new RuleResult($this->penalty, self::class);
    }

    public function isHard(): bool
    {
        return false;
    }
}
