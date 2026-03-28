<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

it('keeps any feasible solution above any infeasible solution', function (): void {
    $evaluator = new FitnessEvaluator(
        weights: new FitnessWeights(),
        rules: [
            new FixedHardPenaltyRule(1.0),
            new FixedSoftPenaltyRule(40.0),
        ]
    );

    $context = makeEvalContext();
    $chromosome = new Cromossomo([new Gene(1, 1, 1, 1, 1, 1, 1)]);

    $infeasible = $evaluator->evaluate($chromosome, $context);

    $feasibleEvaluator = new FitnessEvaluator(
        weights: new FitnessWeights(),
        rules: [
            new FixedHardPenaltyRule(0.0),
            new FixedSoftPenaltyRule(40.0),
        ]
    );

    $feasible = $feasibleEvaluator->evaluate($chromosome, $context);

    expect($infeasible->hardPenalty())->toBeGreaterThan(0.0)
        ->and($infeasible->score())->toBeLessThan(50.0)
        ->and($feasible->hardPenalty())->toBe(0.0)
        ->and($feasible->score())->toBeGreaterThanOrEqual(50.0)
        ->and($feasible->score())->toBeGreaterThan($infeasible->score());
});

function makeEvalContext(): EvaluationContext
{
    $scheduleData = new ScheduleData(
        lessons: [],
        professors: [],
        classes: [],
        timeSlots: [1 => new TimeSlot(1, 1, 1)],
        restrictions: [],
        lessonsByProfessor: [],
        lessonsByClass: [],
        restrictionsByProfessor: [],
        restrictionsByClass: [],
        expectedLoadByLesson: [],
        availableSlotsByProfessor: [],
        availableSlotsByClass: [],
        totalTimeSlots: 1,
        totalLessons: 0,
        totalProfessors: 0,
        totalClasses: 0
    );

    return new EvaluationContext(
        cromossomo: new Cromossomo([new Gene(1, 1, 1, 1, 1, 1, 1)]),
        data: $scheduleData
    );
}

final class FixedHardPenaltyRule implements HardRuleInterface
{
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
}

final class FixedSoftPenaltyRule implements SoftRuleInterface
{
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
}

