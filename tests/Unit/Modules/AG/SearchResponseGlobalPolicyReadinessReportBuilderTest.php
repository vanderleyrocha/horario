<?php

use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use App\Modules\AG\Domain\Landscape\SearchResponseGlobalPolicyReadinessReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds a global policy readiness report across the latest execution window', function (): void {
    $executionA = new ScheduleExecution([
        'id' => 201,
        'status' => 'finished',
        'start_time' => Carbon::parse('2026-03-28 10:00:00'),
        'generations' => 32,
        'best_fitness' => 0.91,
    ]);
    $executionA->exists = true;
    $executionA->setAttribute('id', 201);
    $executionA->setRelation('latestMetric', new ScheduleGenerationMetric([
        'generation' => 32,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'deep_valley',
        'landscape_observation' => [
            'depth_score' => 0.83,
            'population_turnover' => 0.16,
            'elite_similarity' => 0.87,
            'best_delta_window' => 0.02,
            'basin_of_attraction_lock_confidence' => 0.91,
            'episode_trend' => [
                'direction' => 'worsening',
                'strength' => 'moderate',
                'headline' => 'Sinal de piora',
                'detail' => 'A sequencia recente aumentou a intensidade do landscape.',
                'sequence' => ['plateau', 'local_minimum'],
            ],
            'alns_trigger' => [
                'real_activation' => [
                    'applied' => true,
                    'policy' => 'basin_lock_escape',
                ],
            ],
            'search_response_readiness_dashboard' => [
                'resolved_evidence_count' => 5,
                'best_policy_by_success' => [
                    'policy' => 'basin_lock_escape',
                    'success_rate' => 0.78,
                    'avg_progress_score' => 0.71,
                ],
                'best_policy_by_progress' => [
                    'policy' => 'deep_valley_probe',
                    'success_rate' => 0.51,
                    'avg_progress_score' => 0.79,
                ],
                'activation_gate' => [
                    'candidate_policy' => 'basin_lock_escape',
                    'eligible_as_candidate' => true,
                    'supporting_stats' => [
                        'policy' => 'basin_lock_escape',
                    ],
                    'blocking_reasons' => [],
                ],
                'policy_rows' => [
                    [
                        'policy' => 'basin_lock_escape',
                        'resolved_outcomes' => 5,
                        'success_rate' => 0.78,
                        'avg_progress_score' => 0.71,
                    ],
                    [
                        'policy' => 'deep_valley_probe',
                        'resolved_outcomes' => 2,
                        'success_rate' => 0.51,
                        'avg_progress_score' => 0.79,
                    ],
                ],
            ],
        ],
    ]));

    $executionB = new ScheduleExecution([
        'id' => 202,
        'status' => 'finished',
        'start_time' => Carbon::parse('2026-03-28 11:00:00'),
        'generations' => 24,
        'best_fitness' => 0.86,
    ]);
    $executionB->exists = true;
    $executionB->setAttribute('id', 202);
    $executionB->setRelation('latestMetric', new ScheduleGenerationMetric([
        'generation' => 24,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'local_minimum',
        'landscape_observation' => [
            'depth_score' => 0.72,
            'population_turnover' => 0.19,
            'elite_similarity' => 0.82,
            'best_delta_window' => 0.03,
            'basin_of_attraction_lock_confidence' => 0.78,
            'episode_trend' => [
                'direction' => 'improving',
                'strength' => 'strong',
                'headline' => 'Tendencia de melhora',
                'detail' => 'Sequencia recente em recuperacao do landscape.',
                'sequence' => ['deep_valley', 'local_minimum', 'recovered'],
            ],
            'alns_trigger' => [
                'real_activation' => [
                    'applied' => false,
                    'policy' => null,
                ],
            ],
            'search_response_readiness_dashboard' => [
                'resolved_evidence_count' => 3,
                'best_policy_by_success' => [
                    'policy' => 'deep_valley_probe',
                    'success_rate' => 0.57,
                    'avg_progress_score' => 0.63,
                ],
                'best_policy_by_progress' => [
                    'policy' => 'deep_valley_probe',
                    'success_rate' => 0.57,
                    'avg_progress_score' => 0.63,
                ],
                'activation_gate' => [
                    'candidate_policy' => null,
                    'eligible_as_candidate' => false,
                    'supporting_stats' => [
                        'policy' => 'deep_valley_probe',
                    ],
                    'blocking_reasons' => [
                        'Need more resolved shadow outcomes.',
                    ],
                ],
                'blocking_reasons' => [
                    'Need more resolved shadow outcomes.',
                ],
                'policy_rows' => [
                    [
                        'policy' => 'deep_valley_probe',
                        'resolved_outcomes' => 3,
                        'success_rate' => 0.57,
                        'avg_progress_score' => 0.63,
                    ],
                ],
            ],
        ],
    ]));

    $executionC = new ScheduleExecution([
        'id' => 203,
        'status' => 'failed',
        'start_time' => Carbon::parse('2026-03-28 12:00:00'),
    ]);
    $executionC->exists = true;
    $executionC->setAttribute('id', 203);

    $report = (new SearchResponseGlobalPolicyReadinessReportBuilder)
        ->build([$executionA, $executionB, $executionC])
        ->toArray();

    expect($report['status'])->toBe('candidate_ready')
        ->and($report['window_executions_count'])->toBe(3)
        ->and($report['policies_count'])->toBe(2)
        ->and($report['worsening_occurrences'])->toBe(1)
        ->and($report['improving_occurrences'])->toBe(1)
        ->and($report['activation_trend_comparison']['worsening']['real_activation_occurrences'])->toBe(1)
        ->and($report['activation_trend_comparison']['improving']['real_activation_occurrences'])->toBe(0)
        ->and($report['leading_policy_by_near_gate']['policy'])->toBe('basin_lock_escape')
        ->and($report['leading_policy_by_candidate_ready']['policy'])->toBe('basin_lock_escape')
        ->and($report['policy_rows'][0]['top_landscape_state'])->toBe('stagnation')
        ->and($report['policy_rows'][0]['top_landscape_phenomenon'])->toBe('deep_valley')
        ->and($report['policy_rows'][0]['top_trend_direction'])->toBe('worsening')
        ->and($report['policy_rows'][0]['real_activation_occurrences'])->toBe(1)
        ->and($report['policy_rows'][1]['top_blocking_reason'])->toBe('Need more resolved shadow outcomes.')
        ->and($report['policy_rows'][1]['near_gate_occurrences'])->toBe(1)
        ->and($report['policy_rows'][1]['candidate_ready_occurrences'])->toBe(0)
        ->and($report['activation_trend_comparison']['better_trend'])->toBe('worsening');
});
