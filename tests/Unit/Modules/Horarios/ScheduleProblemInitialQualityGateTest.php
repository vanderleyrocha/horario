<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Horarios;

use App\Modules\AG\Domain\Contracts\ProgressReporterInterface;
use App\Modules\AG\Domain\Fitness\FitnessEvaluator;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Repair\GreedyRepairOperator;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Builders\EvaluationContextBuilder;
use App\Modules\Horarios\Domain\Constraints\Repair\CustomConstraintRepairExtension;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;
use App\Modules\Horarios\Domain\Problem\ScheduleProblem;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;
use Pest\Expectation;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

if (! function_exists(__NAMESPACE__ . '\\expect')) {
    function expect(mixed $value = null): Expectation
    {
        return new Expectation($value);
    }
}

uses(TestCase::class);

it('retries the initial population build when the quality gate rejects the candidate', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    // hardPenalty > 400.0 garante rejeição mesmo na fase relaxada do quality gate
    $problem = makeScheduleProblem(
        hardPenalty: 500.0,
        softPenalty: 5.0,
        progress: $progress,
    );

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class, 'Quality gate rejeitou');

    $rejectedPayloads = $progress->payloadsForStage('quality_gate_rejected');

    expect($progress->stages())->toContain('quality_gate_rejected')
        ->and($rejectedPayloads)->toHaveCount($rejectedPayloads[0]['attempt_limit'] ?? 0)
        ->and($rejectedPayloads[0]['max_hard_penalty'])->toBe(12.0)
        ->and($rejectedPayloads[0]['decision'] ?? null)->toBe('rejected')
        ->and($rejectedPayloads[0])->toHaveKey('dominant_reason')
        ->and($rejectedPayloads[0])->toHaveKey('supporting_signals');
});

it('does not use score as rejection reason when hard thresholds are the actual gate criteria', function (): void {
    $problem = makeScheduleProblem(
        hardPenalty: 20.0,
        softPenalty: 0.0,
    );

    $candidate = new Cromossomo([
        new Gene(1, 10, 20, 31, 1, 1, 1),
    ]);

    $qualityGate = scheduleProblemInvokePrivate($problem, 'evaluateInitialPopulationQualityGate', [
        $candidate,
        1,
        1,
        ['hard_conflict_allocations' => 0],
        false,
    ]);

    expect($qualityGate['passes'])->toBeFalse()
        ->and($qualityGate['rejection_reasons'])->toContain('hard_penalty_above_limit')
        ->and($qualityGate['rejection_reasons'])->not->toContain('score_below_viable_threshold')
        ->and($qualityGate['decision'])->toBe('rejected')
        ->and($qualityGate['dominant_reason'])->toBe('hard_penalty_above_limit')
        ->and($qualityGate['dominant_rejection_reason'])->toBe('hard_penalty_above_limit')
        ->and($qualityGate['supporting_signals']['score_below_viable_threshold'] ?? null)->toBeTrue();
});

it('identifies hard conflicts as dominant rejection reason when conflict excess is the strongest blocker', function (): void {
    $problem = makeScheduleProblem(
        hardPenalty: 13.0,
        softPenalty: 0.0,
    );

    $candidate = new Cromossomo([
        new Gene(1, 10, 20, 31, 1, 1, 1),
    ]);

    $qualityGate = scheduleProblemInvokePrivate($problem, 'evaluateInitialPopulationQualityGate', [
        $candidate,
        1,
        10,
        ['hard_conflict_allocations' => 10],
        false,
    ]);

    expect($qualityGate['passes'])->toBeFalse()
        ->and($qualityGate['rejection_reasons'])->toContain('hard_penalty_above_limit', 'hard_conflicts_above_limit')
        ->and($qualityGate['dominant_rejection_reason'])->toBe('hard_conflicts_above_limit');
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

it('uses a conservative alpha profile when queue pressure and recent stress are high', function (): void {
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 1.0,
    );

    scheduleProblemSetPrivate($problem, 'currentBuildAttemptLimit', 18);
    scheduleProblemSetPrivate($problem, 'cachedDiagnostics', [
        'diagnostics' => [
            [
                'candidate_slots' => 1,
                'weekly_occurrences' => 2,
            ],
        ],
    ]);
    scheduleProblemSetPrivate($problem, 'initialPopulationAttemptHistory', [
        ['outcome' => 'fail_fast', 'forced_allocations' => 8, 'hard_conflict_allocations' => 7, 'queue_size' => 10],
        ['outcome' => 'fail_fast', 'forced_allocations' => 7, 'hard_conflict_allocations' => 6, 'queue_size' => 10],
        ['outcome' => 'quality_gate_rejected', 'forced_allocations' => 6, 'hard_conflict_allocations' => 5, 'queue_size' => 10],
    ]);

    $decision = scheduleProblemInvokePrivate($problem, 'resolveAdaptiveAlpha', [2, 10]);

    expect($decision['alpha_profile'])->toBe('conservative')
        ->and($decision['alpha_pressure_score'])->toBeGreaterThanOrEqual(0.68)
        ->and($decision['alpha'])->toBeGreaterThanOrEqual($decision['alpha_min'])
        ->and($decision['alpha'])->toBeLessThanOrEqual($decision['alpha_max'])
        ->and($decision['alpha_reason'])->toContain('Pressao alta');
});

it('uses an exploratory alpha profile when pressure and recent stress are low', function (): void {
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 1.0,
    );

    scheduleProblemSetPrivate($problem, 'currentBuildAttemptLimit', 18);
    scheduleProblemSetPrivate($problem, 'cachedDiagnostics', [
        'diagnostics' => [
            [
                'candidate_slots' => 8,
                'weekly_occurrences' => 1,
            ],
        ],
    ]);
    scheduleProblemSetPrivate($problem, 'initialPopulationAttemptHistory', []);

    $decision = scheduleProblemInvokePrivate($problem, 'resolveAdaptiveAlpha', [1, 1]);

    expect($decision['alpha_profile'])->toBe('exploratory')
        ->and($decision['alpha_pressure_score'])->toBeLessThan(0.38)
        ->and($decision['alpha'])->toBeGreaterThanOrEqual($decision['alpha_min'])
        ->and($decision['alpha'])->toBeLessThanOrEqual($decision['alpha_max'])
        ->and($decision['alpha_reason'])->toContain('Pressao controlada');
});

it('activates emergency relaxed quality gate when recent rejections are far above strict limits', function (): void {
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 1.0,
    );

    scheduleProblemSetPrivate($problem, 'initialPopulationAttemptHistory', [
        ['outcome' => 'quality_gate_rejected', 'hard_penalty' => 320.0, 'max_hard_penalty' => 12.0],
        ['outcome' => 'quality_gate_rejected', 'hard_penalty' => 315.0, 'max_hard_penalty' => 18.0],
        ['outcome' => 'quality_gate_rejected', 'hard_penalty' => 309.0, 'max_hard_penalty' => 24.0],
    ]);

    $thresholds = scheduleProblemInvokePrivate($problem, 'initialQualityGateThresholds', [4, 100]);

    expect($thresholds['max_hard_penalty'])->toBe(400.0)
        ->and($thresholds['max_hard_conflict_allocations'])->toBe(2);
});

it('skips expensive initial repair when candidate is clearly out of strict hard-penalty range', function (): void {
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 1.0,
    );

    $shouldSkipStrict = scheduleProblemInvokePrivate($problem, 'shouldSkipInitialQualityGateRepair', [[
        'hard_penalty' => 300.0,
        'max_hard_penalty' => 30.0,
    ], 3]);

    $shouldSkipRelaxed = scheduleProblemInvokePrivate($problem, 'shouldSkipInitialQualityGateRepair', [[
        'hard_penalty' => 300.0,
        'max_hard_penalty' => 400.0,
    ], 12]);

    expect($shouldSkipStrict)->toBeTrue()
        ->and($shouldSkipRelaxed)->toBeFalse();
});

it('skips expensive initial repair for custom-heavy candidates with no structural escape signals', function (): void {
    $repairOperator = new GreedyRepairOperator([
        new CustomConstraintRepairExtension(),
    ]);

    $problem = makeScheduleProblemFromData(
        makeCustomHeavyScheduleData(),
        hardPenalty: 90.0,
        softPenalty: 2.0,
        repairOperator: $repairOperator,
    );

    scheduleProblemSetPrivate($problem, 'cachedDiagnostics', [
        'constraint_risk_contribution' => 40,
    ]);

    $candidate = new Cromossomo(makeCustomHeavyCandidateGenes());

    $shouldSkip = scheduleProblemInvokePrivate($problem, 'shouldSkipInitialQualityGateRepair', [[
        'hard_penalty' => 90.0,
        'max_hard_penalty' => 30.0,
        'hard_conflict_allocations' => 0,
    ], 3, $candidate, [
        'hard_conflict_allocations' => 0,
    ]]);

    expect($shouldSkip)->toBeTrue();
});

it('learns custom-only dead-end repair fingerprints for future quality gate skips', function (): void {
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 1.0,
    );

    scheduleProblemInvokePrivate($problem, 'rememberInitialRepairDeadEnd', [[
        'aborted' => true,
        'abort_reason' => 'time_budget_exhausted',
        'passes_without_progress' => 0,
        'invalid_genes_before' => 14,
        'invalid_genes_after' => 14,
        'relocations' => 0,
        'swaps' => 0,
        'local_rebuilds' => 0,
        'repair_target_summary_before' => [
            'custom_sync_same_timeslot' => 12,
            'custom_mutual_exclusion' => 2,
        ],
    ]]);

    $knownDeadEnd = scheduleProblemInvokePrivate($problem, 'isKnownInitialRepairDeadEnd', [[
        'invalid_genes' => 14,
        'repair_target_summary' => [
            'custom_mutual_exclusion' => 2,
            'custom_sync_same_timeslot' => 12,
        ],
    ]]);

    $differentPattern = scheduleProblemInvokePrivate($problem, 'isKnownInitialRepairDeadEnd', [[
        'invalid_genes' => 13,
        'repair_target_summary' => [
            'custom_mutual_exclusion' => 2,
            'custom_sync_same_timeslot' => 11,
        ],
    ]]);

    expect($knownDeadEnd)->toBeTrue()
        ->and($differentPattern)->toBeFalse();
});

it('marks the initial construction attempt as timed out when the total budget is already exhausted', function (): void {
    $problem = makeScheduleProblem(
        hardPenalty: 0.0,
        softPenalty: 1.0,
        lessonCount: 1,
        slotCount: 1,
    );

    $queue = scheduleProblemInvokePrivate($problem, 'buildPlacementQueue');
    $assignedGenes = [];
    $teacherBusy = [];
    $classBusy = [];
    $telemetry = [
        'attempt' => 1,
        'alpha' => 0.3,
        'queue_size' => count($queue),
        'allocations' => 0,
        'forced_allocations' => 0,
        'hard_conflict_allocations' => 0,
        'dynamic_reorders' => 0,
        'regret_selections' => 0,
        'attempt_limit' => 1,
        'attempt_started_at' => microtime(true),
        'attempt_time_budget_ms' => 1,
        'timed_out' => false,
        'rcl_sizes' => [],
        'constructor_strategy' => 'difficulty_default',
        'constructor_reason' => 'test',
    ];
    $args = [$queue, 0.3, &$assignedGenes, &$teacherBusy, &$classBusy, &$telemetry];

    scheduleProblemSetPrivate($problem, 'activeInitialConstructionTimeBudgetMs', 1);
    scheduleProblemSetPrivate($problem, 'activeInitialConstructionDeadlineAt', microtime(true) - 1);

    $constructed = scheduleProblemInvokePrivate($problem, 'constructWithGrasp', $args);

    expect($constructed)->toBeFalse()
        ->and($assignedGenes)->toBeEmpty()
        ->and($telemetry['timed_out'])->toBeTrue()
        ->and(scheduleProblemGetPrivate($problem, 'lastBuildFailure'))->toContain('budget total');
});

it('prioritizes synchronization-constrained lessons first and then higher professor pressure', function (): void {
    $data = makeSyncPriorityScheduleData();
    $problem = makeScheduleProblemFromData($data);
    $queue = [
        ['lesson' => $data->lessons[3], 'occurrence' => 1, 'candidate_count' => 4],
        ['lesson' => $data->lessons[2], 'occurrence' => 1, 'candidate_count' => 4],
        ['lesson' => $data->lessons[4], 'occurrence' => 1, 'candidate_count' => 4],
        ['lesson' => $data->lessons[1], 'occurrence' => 1, 'candidate_count' => 2],
    ];

    $reordered = scheduleProblemInvokePrivate($problem, 'reorderQueueSyncConstraintsFirst', [$queue]);
    $orderedLessonIds = array_map(
        static fn (array $task): int => $task['lesson']->id,
        $reordered,
    );

    expect($orderedLessonIds[0])->toBe(1)
        ->and($orderedLessonIds[1])->toBe(2)
        ->and(scheduleProblemInvokePrivate($problem, 'syncConstraintPriorityForLesson', [1]))->toBeGreaterThan(0.0)
        ->and(scheduleProblemInvokePrivate($problem, 'syncConstraintPriorityForLesson', [3]))->toBe(0.0);
});

it('creates a sync hybrid seed candidate that keeps synchronized lessons in the same slot', function (): void {
    config()->set('ag.initial_population.sync_hybrid_seed.enabled', true);
    config()->set('ag.initial_population.sync_hybrid_seed.min_sync_constraints', 1);
    config()->set('ag.initial_population.sync_hybrid_seed.min_queue_size', 1);
    config()->set('ag.initial_population.sync_hybrid_seed.max_units', 6);

    $data = makeCustomHeavyScheduleData();
    $problem = makeScheduleProblemFromData($data);
    $queue = scheduleProblemInvokePrivate($problem, 'buildPlacementQueue');
    $candidate = scheduleProblemInvokePrivate($problem, 'tryCreateIndividualFromSyncHybridSeed', [$queue]);

    expect($candidate)->toBeInstanceOf(Cromossomo::class)
        ->and($candidate->count())->toBe(count($data->lessons));

    $genesByLesson = [];

    foreach ($candidate->genes() as $gene) {
        $genesByLesson[$gene->aulaId()] = $gene;
    }

    expect($genesByLesson[1]->diaSemana())->toBe($genesByLesson[2]->diaSemana())
        ->and($genesByLesson[1]->periodoDia())->toBe($genesByLesson[2]->periodoDia())
        ->and($genesByLesson[3]->diaSemana())->toBe($genesByLesson[4]->diaSemana())
        ->and($genesByLesson[3]->periodoDia())->toBe($genesByLesson[4]->periodoDia());
});

it('publishes alpha policy, reason and impact in grasp telemetry', function (): void {
    $progress = makeScheduleProblemProgressSpy();
    $problem = makeScheduleProblem(
        hardPenalty: 500.0,
        softPenalty: 5.0,
        progress: $progress,
    );

    expect(fn () => $problem->createIndividual())
        ->toThrow(RuntimeException::class, 'Quality gate rejeitou');

    $graspStart = $progress->payloadsForStage('grasp_start');
    $graspRetry = $progress->payloadsForStage('grasp_retry');

    expect($graspStart)->not->toBeEmpty()
        ->and($graspStart[0])->toHaveKey('alpha_policy')
        ->and($graspStart[0])->toHaveKey('alpha_reason')
        ->and($graspStart[0])->toHaveKey('alpha_pressure_score')
        ->and($graspRetry)->not->toBeEmpty()
        ->and($graspRetry[0])->toHaveKey('alpha_impact')
        ->and($graspRetry[0]['alpha_impact'])->toBeArray()
        ->and($graspRetry[0]['alpha_impact'])->toHaveKeys(['forced_ratio', 'hard_conflict_ratio', 'fill_ratio', 'avg_rcl_size', 'effectiveness']);
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
    // hardPenalty > 400.0 garante rejeição mesmo na fase relaxada do quality gate
    $problem = makeDenseConflictScheduleProblem(
        lessonCount: 2,
        hardPenalty: 500.0,
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

it('prioritizes the lesson with higher live tightness when feasible slots are tied', function (): void {
    $data = makeScheduleDataForDynamicQueueSignals(
        lessons: [
            1 => new LessonData(
                id: 1,
                professorId: 10,
                classId: 20,
                disciplinaId: 31,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
            ),
            2 => new LessonData(
                id: 2,
                professorId: 11,
                classId: 21,
                disciplinaId: 32,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
            ),
        ],
        timeSlots: [
            1 => new TimeSlot(1, 1, 1),
            2 => new TimeSlot(2, 1, 2),
        ],
        lessonsByProfessor: [
            10 => [1],
            11 => [2],
        ],
        lessonsByClass: [
            20 => [1],
            21 => [2],
        ],
        expectedLoadByClass: [
            20 => 1,
            21 => 1,
        ],
        availableSlotsByProfessor: [
            10 => [1, 2],
            11 => [1, 2],
        ],
        availableSlotsByClass: [
            20 => [1, 2],
            21 => [1, 2],
        ],
    );

    $problem = makeScheduleProblemFromData($data);
    $queue = scheduleProblemInvokePrivate($problem, 'buildPlacementQueue');
    $teacherBusy = [
        10 => ['5-99' => true],
    ];
    $classBusy = [
        20 => ['5-99' => true],
    ];

    $reordered = scheduleProblemInvokePrivate(
        $problem,
        'reorderPlacementQueueDynamically',
        [$queue, $teacherBusy, $classBusy, []],
    );

    $priorityByLesson = [];

    foreach ($data->lessons as $lesson) {
        $priorityByLesson[$lesson->id] = scheduleProblemInvokePrivate(
            $problem,
            'dynamicQueuePriority',
            [$lesson, $teacherBusy, $classBusy, []],
        );
    }

    expect($priorityByLesson[2]['live_tightness'])->toBeGreaterThan($priorityByLesson[1]['live_tightness'])
        ->and($reordered[0]['lesson']->id)->toBe(2);
});

it('prioritizes lessons under higher slot contention when feasible slots and live tightness are tied', function (): void {
    $data = makeScheduleDataForDynamicQueueSignals(
        lessons: [
            1 => new LessonData(
                id: 1,
                professorId: 10,
                classId: 20,
                disciplinaId: 31,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
            ),
            2 => new LessonData(
                id: 2,
                professorId: 11,
                classId: 21,
                disciplinaId: 32,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
            ),
            3 => new LessonData(
                id: 3,
                professorId: 12,
                classId: 22,
                disciplinaId: 33,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
            ),
        ],
        timeSlots: [
            1 => new TimeSlot(1, 1, 1),
            2 => new TimeSlot(2, 1, 2),
            3 => new TimeSlot(3, 1, 3),
            4 => new TimeSlot(4, 1, 4),
        ],
        lessonsByProfessor: [
            10 => [1],
            11 => [2],
            12 => [3],
        ],
        lessonsByClass: [
            20 => [1],
            21 => [2],
            22 => [3],
        ],
        expectedLoadByClass: [
            20 => 1,
            21 => 1,
            22 => 1,
        ],
        availableSlotsByProfessor: [
            10 => [1, 2],
            11 => [1, 2],
            12 => [3, 4],
        ],
        availableSlotsByClass: [
            20 => [1, 2],
            21 => [1, 2],
            22 => [3, 4],
        ],
    );

    $problem = makeScheduleProblemFromData($data);
    scheduleProblemInvokePrivate($problem, 'buildPlacementQueue');

    $queue = [
        ['lesson' => $data->lessons[1], 'occurrence' => 1, 'candidate_count' => 2],
        ['lesson' => $data->lessons[2], 'occurrence' => 1, 'candidate_count' => 2],
        ['lesson' => $data->lessons[3], 'occurrence' => 1, 'candidate_count' => 2],
    ];

    $teacherBusy = [];
    $classBusy = [];

    $reordered = scheduleProblemInvokePrivate(
        $problem,
        'reorderPlacementQueueDynamically',
        [$queue, $teacherBusy, $classBusy, []],
    );

    $priorityLesson1 = scheduleProblemInvokePrivate(
        $problem,
        'dynamicQueuePriority',
        [$data->lessons[1], $teacherBusy, $classBusy, []],
    );
    $priorityLesson3 = scheduleProblemInvokePrivate(
        $problem,
        'dynamicQueuePriority',
        [$data->lessons[3], $teacherBusy, $classBusy, []],
    );

    $orderedLessonIds = array_map(
        static fn (array $task): int => $task['lesson']->id,
        $reordered,
    );

    expect($priorityLesson1['avg_slot_contention'])->toBeGreaterThan($priorityLesson3['avg_slot_contention'])
        ->and(array_search(3, $orderedLessonIds, true))->toBeGreaterThan(0);
});

it('uses static difficulty as stable fallback when feasible slots, live tightness and contention are tied', function (): void {
    $data = makeScheduleDataForDynamicQueueSignals(
        lessons: [
            1 => new LessonData(
                id: 1,
                professorId: 10,
                classId: 20,
                disciplinaId: 31,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
                preferredDays: [1],
            ),
            2 => new LessonData(
                id: 2,
                professorId: 11,
                classId: 21,
                disciplinaId: 32,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
            ),
            3 => new LessonData(
                id: 3,
                professorId: 12,
                classId: 22,
                disciplinaId: 33,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
            ),
            4 => new LessonData(
                id: 4,
                professorId: 13,
                classId: 23,
                disciplinaId: 34,
                requiredSlots: 1,
                weeklyOccurrences: 1,
                requiresConsecutive: false,
            ),
        ],
        timeSlots: [
            1 => new TimeSlot(1, 1, 1),
            2 => new TimeSlot(2, 1, 2),
            3 => new TimeSlot(3, 1, 3),
            4 => new TimeSlot(4, 1, 4),
            5 => new TimeSlot(5, 1, 5),
            6 => new TimeSlot(6, 1, 6),
        ],
        lessonsByProfessor: [
            10 => [1],
            11 => [2],
            12 => [3],
            13 => [4],
        ],
        lessonsByClass: [
            20 => [1],
            21 => [2],
            22 => [3],
            23 => [4],
        ],
        expectedLoadByClass: [
            20 => 1,
            21 => 1,
            22 => 1,
            23 => 1,
        ],
        availableSlotsByProfessor: [
            10 => [1, 2],
            11 => [1, 2],
            12 => [1, 2],
            13 => [3, 4, 5, 6],
        ],
        availableSlotsByClass: [
            20 => [1, 2],
            21 => [1, 2],
            22 => [1, 2],
            23 => [3, 4, 5, 6],
        ],
    );

    $problem = makeScheduleProblemFromData($data);
    scheduleProblemInvokePrivate($problem, 'buildPlacementQueue');

    $queue = [
        ['lesson' => $data->lessons[1], 'occurrence' => 1, 'candidate_count' => 2],
        ['lesson' => $data->lessons[2], 'occurrence' => 1, 'candidate_count' => 2],
        ['lesson' => $data->lessons[3], 'occurrence' => 1, 'candidate_count' => 2],
        ['lesson' => $data->lessons[4], 'occurrence' => 1, 'candidate_count' => 4],
    ];

    $teacherBusy = [];
    $classBusy = [];

    $reordered = scheduleProblemInvokePrivate(
        $problem,
        'reorderPlacementQueueDynamically',
        [$queue, $teacherBusy, $classBusy, []],
    );

    $priorityLesson1 = scheduleProblemInvokePrivate(
        $problem,
        'dynamicQueuePriority',
        [$data->lessons[1], $teacherBusy, $classBusy, []],
    );
    $priorityLesson2 = scheduleProblemInvokePrivate(
        $problem,
        'dynamicQueuePriority',
        [$data->lessons[2], $teacherBusy, $classBusy, []],
    );

    expect($priorityLesson1['feasible_slots'])->toBe($priorityLesson2['feasible_slots'])
        ->and($priorityLesson1['live_tightness'])->toBe($priorityLesson2['live_tightness'])
        ->and($priorityLesson1['avg_slot_contention'])->toBe($priorityLesson2['avg_slot_contention'])
        ->and($priorityLesson1['static_difficulty_score'])->toBeGreaterThan($priorityLesson2['static_difficulty_score'])
        ->and($reordered[0]['lesson']->id)->toBe(1)
        ->and($reordered[1]['lesson']->id)->toBe(2);
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

function makeScheduleProblemFromData(
    ScheduleData $data,
    float $hardPenalty = 0.0,
    float $softPenalty = 0.0,
    ?GreedyRepairOperator $repairOperator = null,
): ScheduleProblem
{
    $fitnessEvaluator = new FitnessEvaluator(
        weights: new FitnessWeights(),
        rules: [
            makeScheduleProblemFixedHardPenaltyRule($hardPenalty),
            makeScheduleProblemFixedSoftPenaltyRule($softPenalty),
        ],
    );

    return new ScheduleProblem(
        data: $data,
        contextBuilder: new EvaluationContextBuilder(),
        fitnessEvaluator: $fitnessEvaluator,
        repairOperator: $repairOperator ?? new GreedyRepairOperator(),
        progress: null,
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

function makeScheduleDataForDynamicQueueSignals(
    array $lessons,
    array $timeSlots,
    array $lessonsByProfessor,
    array $lessonsByClass,
    array $expectedLoadByClass,
    array $availableSlotsByProfessor,
    array $availableSlotsByClass,
): ScheduleData {
    return new ScheduleData(
        lessons: $lessons,
        professors: array_fill_keys(array_keys($lessonsByProfessor), []),
        classes: array_fill_keys(array_keys($lessonsByClass), []),
        timeSlots: $timeSlots,
        restrictions: [],
        lessonsByProfessor: $lessonsByProfessor,
        lessonsByClass: $lessonsByClass,
        restrictionsByProfessor: [],
        restrictionsByClass: [],
        expectedLoadByLesson: $expectedLoadByClass,
        availableSlotsByProfessor: $availableSlotsByProfessor,
        availableSlotsByClass: $availableSlotsByClass,
        totalTimeSlots: count($timeSlots),
        totalLessons: count($lessons),
        totalProfessors: count($lessonsByProfessor),
        totalClasses: count($lessonsByClass),
    );
}

function makeSyncPriorityScheduleData(): ScheduleData
{
    $lessons = [
        1 => new LessonData(
            id: 1,
            professorId: 10,
            classId: 20,
            disciplinaId: 31,
            requiredSlots: 1,
            weeklyOccurrences: 1,
            requiresConsecutive: false,
        ),
        2 => new LessonData(
            id: 2,
            professorId: 11,
            classId: 21,
            disciplinaId: 32,
            requiredSlots: 1,
            weeklyOccurrences: 1,
            requiresConsecutive: false,
        ),
        3 => new LessonData(
            id: 3,
            professorId: 10,
            classId: 22,
            disciplinaId: 33,
            requiredSlots: 1,
            weeklyOccurrences: 1,
            requiresConsecutive: false,
        ),
        4 => new LessonData(
            id: 4,
            professorId: 10,
            classId: 23,
            disciplinaId: 34,
            requiredSlots: 1,
            weeklyOccurrences: 1,
            requiresConsecutive: false,
        ),
    ];

    return new ScheduleData(
        lessons: $lessons,
        professors: [],
        classes: [],
        timeSlots: [
            1 => new TimeSlot(1, 1, 1),
            2 => new TimeSlot(2, 1, 2),
            3 => new TimeSlot(3, 1, 3),
            4 => new TimeSlot(4, 1, 4),
        ],
        restrictions: [],
        lessonsByProfessor: [
            10 => [1, 3, 4],
            11 => [2],
        ],
        lessonsByClass: [
            20 => [1],
            21 => [2],
            22 => [3],
            23 => [4],
        ],
        restrictionsByProfessor: [],
        restrictionsByClass: [],
        expectedLoadByLesson: [
            1 => 1,
            2 => 1,
            3 => 1,
            4 => 1,
        ],
        availableSlotsByProfessor: [
            10 => [1, 2, 3, 4],
            11 => [1, 2, 3, 4],
        ],
        availableSlotsByClass: [
            20 => [1, 2],
            21 => [1, 2, 3, 4],
            22 => [1, 2, 3, 4],
            23 => [1, 2, 3, 4],
        ],
        totalTimeSlots: 4,
        totalLessons: 4,
        totalProfessors: 2,
        totalClasses: 4,
        customConstraints: [
            new CustomConstraintData(
                id: 1,
                name: 'Sync pair 1',
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
    );
}

function makeCustomHeavyScheduleData(): ScheduleData
{
    $lessons = [];
    $timeSlots = [
        1 => new TimeSlot(1, 1, 1),
        2 => new TimeSlot(2, 1, 2),
    ];
    $availableProfessor = [];
    $availableClass = [];
    $lessonsByProfessor = [];
    $lessonsByClass = [];
    $expectedLoadByLesson = [];
    $customConstraints = [];

    for ($pair = 1; $pair <= 6; $pair++) {
        $leftLessonId = (($pair - 1) * 2) + 1;
        $rightLessonId = $leftLessonId + 1;
        $leftProfessorId = 100 + $leftLessonId;
        $rightProfessorId = 100 + $rightLessonId;
        $leftClassId = 200 + $leftLessonId;
        $rightClassId = 200 + $rightLessonId;

        $lessons[$leftLessonId] = new LessonData(
            id: $leftLessonId,
            professorId: $leftProfessorId,
            classId: $leftClassId,
            disciplinaId: 300 + $leftLessonId,
            requiredSlots: 1,
            weeklyOccurrences: 1,
            requiresConsecutive: false,
        );
        $lessons[$rightLessonId] = new LessonData(
            id: $rightLessonId,
            professorId: $rightProfessorId,
            classId: $rightClassId,
            disciplinaId: 300 + $rightLessonId,
            requiredSlots: 1,
            weeklyOccurrences: 1,
            requiresConsecutive: false,
        );

        $availableProfessor[$leftProfessorId] = [1, 2];
        $availableProfessor[$rightProfessorId] = [1, 2];
        $availableClass[$leftClassId] = [1, 2];
        $availableClass[$rightClassId] = [1, 2];
        $lessonsByProfessor[$leftProfessorId] = [$leftLessonId];
        $lessonsByProfessor[$rightProfessorId] = [$rightLessonId];
        $lessonsByClass[$leftClassId] = [$leftLessonId];
        $lessonsByClass[$rightClassId] = [$rightLessonId];
        $expectedLoadByLesson[$leftClassId] = 1;
        $expectedLoadByLesson[$rightClassId] = 1;

        $customConstraints[] = new CustomConstraintData(
            id: $pair,
            name: "Sync pair {$pair}",
            description: null,
            type: 'SYNC_SAME_TIMESLOT',
            level: 'HARD',
            weight: 1,
            isActive: true,
            payload: [
                'left_group' => ['lesson_ids' => [$leftLessonId]],
                'right_group' => ['lesson_ids' => [$rightLessonId]],
                'occurrence_mode' => 'ALL',
                'match_mode' => 'ALL_TO_ALL',
            ],
        );
    }

    return new ScheduleData(
        lessons: $lessons,
        professors: [],
        classes: [],
        timeSlots: $timeSlots,
        restrictions: [],
        lessonsByProfessor: $lessonsByProfessor,
        lessonsByClass: $lessonsByClass,
        restrictionsByProfessor: [],
        restrictionsByClass: [],
        expectedLoadByLesson: $expectedLoadByLesson,
        availableSlotsByProfessor: $availableProfessor,
        availableSlotsByClass: $availableClass,
        totalTimeSlots: count($timeSlots),
        totalLessons: count($lessons),
        totalProfessors: count($lessonsByProfessor),
        totalClasses: count($lessonsByClass),
        customConstraints: $customConstraints,
    );
}

function makeCustomHeavyCandidateGenes(): array
{
    $genes = [];

    for ($pair = 1; $pair <= 6; $pair++) {
        $leftLessonId = (($pair - 1) * 2) + 1;
        $rightLessonId = $leftLessonId + 1;

        $genes[] = new Gene(
            aulaId: $leftLessonId,
            professorId: 100 + $leftLessonId,
            turmaId: 200 + $leftLessonId,
            disciplinaId: 300 + $leftLessonId,
            diaSemana: 1,
            periodoDia: 1,
            duracaoTempos: 1,
        );
        $genes[] = new Gene(
            aulaId: $rightLessonId,
            professorId: 100 + $rightLessonId,
            turmaId: 200 + $rightLessonId,
            disciplinaId: 300 + $rightLessonId,
            diaSemana: 1,
            periodoDia: 2,
            duracaoTempos: 1,
        );
    }

    return $genes;
}

function scheduleProblemInvokePrivate(ScheduleProblem $problem, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod(ScheduleProblem::class, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($problem, $args);
}

function scheduleProblemSetPrivate(ScheduleProblem $problem, string $property, mixed $value): void
{
    $reflection = new ReflectionProperty(ScheduleProblem::class, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($problem, $value);
}

function scheduleProblemGetPrivate(ScheduleProblem $problem, string $property): mixed
{
    $reflection = new ReflectionProperty(ScheduleProblem::class, $property);
    $reflection->setAccessible(true);

    return $reflection->getValue($problem);
}

function makeScheduleProblemProgressSpy(): object
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
