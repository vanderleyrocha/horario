<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Landscape\SearchResponseActivationGate;
use App\Modules\AG\Domain\Landscape\SearchResponseEffectivenessReport;

it('marks a policy as activation candidate when evidence thresholds are satisfied', function (): void {
    $gate = new SearchResponseActivationGate;

    $report = new SearchResponseEffectivenessReport(
        totalResolvedOutcomes: 6,
        bestPolicyBySuccess: 'basin_lock_escape',
        bestPolicyByProgress: 'basin_lock_escape',
        policies: [
            [
                'policy' => 'basin_lock_escape',
                'resolved_outcomes' => 4,
                'targets_satisfied_count' => 3,
                'approached_targets_count' => 4,
                'avg_progress_score' => 0.79,
                'success_rate' => 0.75,
                'approach_rate' => 1.0,
            ],
            [
                'policy' => 'deep_valley_probe',
                'resolved_outcomes' => 2,
                'targets_satisfied_count' => 0,
                'approached_targets_count' => 1,
                'avg_progress_score' => 0.51,
                'success_rate' => 0.0,
                'approach_rate' => 0.5,
            ],
        ]
    );

    $decision = $gate->evaluate($report)->toArray();

    expect($decision)->toMatchArray([
        'eligible_as_candidate' => true,
        'candidate_policy' => 'basin_lock_escape',
        'mode' => 'diagnostic_only',
        'minimum_resolved_outcomes' => 3,
        'minimum_success_rate' => 0.6,
        'minimum_approach_rate' => 0.75,
        'minimum_avg_progress_score' => 0.65,
        'blocking_reasons' => [],
    ]);
});

it('keeps activation disabled when policies still lack evidence', function (): void {
    $gate = new SearchResponseActivationGate;

    $report = new SearchResponseEffectivenessReport(
        totalResolvedOutcomes: 2,
        bestPolicyBySuccess: 'deep_valley_probe',
        bestPolicyByProgress: 'deep_valley_probe',
        policies: [
            [
                'policy' => 'deep_valley_probe',
                'resolved_outcomes' => 2,
                'targets_satisfied_count' => 0,
                'approached_targets_count' => 2,
                'avg_progress_score' => 0.62,
                'success_rate' => 0.0,
                'approach_rate' => 1.0,
            ],
        ]
    );

    $decision = $gate->evaluate($report)->toArray();

    expect($decision)->toMatchArray([
        'eligible_as_candidate' => false,
        'candidate_policy' => null,
        'mode' => 'diagnostic_only',
    ])
        ->and($decision['blocking_reasons'] ?? [])->toContain('Need more resolved shadow outcomes.')
        ->and($decision['blocking_reasons'] ?? [])->toContain('Success rate is still below the activation threshold.')
        ->and($decision['blocking_reasons'] ?? [])->toContain('Average progress score is still below the activation threshold.');
});
