<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Landscape\SearchResponseReadinessDashboardBuilder;

it('builds a readiness dashboard with evidence, gate, latest outcome and blockers', function (): void {
    $builder = new SearchResponseReadinessDashboardBuilder;

    $dashboard = $builder->build(
        effectivenessReport: [
            'total_resolved_outcomes' => 4,
            'best_policy_by_success' => 'basin_lock_escape',
            'best_policy_by_progress' => 'deep_valley_probe',
            'policies' => [
                [
                    'policy' => 'basin_lock_escape',
                    'resolved_outcomes' => 4,
                    'targets_satisfied_count' => 2,
                    'approached_targets_count' => 4,
                    'avg_progress_score' => 0.74,
                    'success_rate' => 0.5,
                    'approach_rate' => 1.0,
                ],
                [
                    'policy' => 'deep_valley_probe',
                    'resolved_outcomes' => 2,
                    'targets_satisfied_count' => 0,
                    'approached_targets_count' => 2,
                    'avg_progress_score' => 0.79,
                    'success_rate' => 0.0,
                    'approach_rate' => 1.0,
                ],
            ],
        ],
        activationGate: [
            'eligible_as_candidate' => false,
            'candidate_policy' => null,
            'mode' => 'diagnostic_only',
            'blocking_reasons' => [
                'Need more resolved shadow outcomes.',
                'Success rate is still below the activation threshold.',
            ],
        ],
        latestOutcome: [
            'policy' => 'basin_lock_escape',
            'targets_satisfied' => false,
            'progress_score' => 0.67,
            'resolved_generation' => 11,
        ],
        pendingAudits: 2
    )->toArray();

    expect($dashboard)->toMatchArray([
        'status' => 'collecting_evidence',
        'resolved_evidence_count' => 4,
        'pending_audits' => 2,
        'blocking_reasons' => [
            'Need more resolved shadow outcomes.',
            'Success rate is still below the activation threshold.',
        ],
    ])
        ->and($dashboard['best_policy_by_success']['policy'] ?? null)->toBe('basin_lock_escape')
        ->and($dashboard['best_policy_by_progress']['policy'] ?? null)->toBe('deep_valley_probe')
        ->and($dashboard['latest_outcome']['policy'] ?? null)->toBe('basin_lock_escape')
        ->and($dashboard['policy_rows'])->toHaveCount(2);
});

it('marks the readiness dashboard as candidate ready when the activation gate approves a policy', function (): void {
    $builder = new SearchResponseReadinessDashboardBuilder;

    $dashboard = $builder->build(
        effectivenessReport: [
            'total_resolved_outcomes' => 6,
            'best_policy_by_success' => 'basin_lock_escape',
            'best_policy_by_progress' => 'basin_lock_escape',
            'policies' => [
                [
                    'policy' => 'basin_lock_escape',
                    'resolved_outcomes' => 6,
                    'targets_satisfied_count' => 4,
                    'approached_targets_count' => 6,
                    'avg_progress_score' => 0.81,
                    'success_rate' => 0.666667,
                    'approach_rate' => 1.0,
                ],
            ],
        ],
        activationGate: [
            'eligible_as_candidate' => true,
            'candidate_policy' => 'basin_lock_escape',
            'mode' => 'diagnostic_only',
            'blocking_reasons' => [],
        ],
        latestOutcome: [
            'policy' => 'basin_lock_escape',
            'targets_satisfied' => true,
            'progress_score' => 0.92,
            'resolved_generation' => 17,
        ],
        pendingAudits: 0
    )->toArray();

    expect($dashboard)->toMatchArray([
        'status' => 'candidate_ready',
        'headline' => 'Diagnostic candidate ready: basin_lock_escape',
    ]);
});
