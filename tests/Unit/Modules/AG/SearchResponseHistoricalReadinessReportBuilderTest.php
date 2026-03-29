<?php

use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use App\Modules\AG\Domain\Landscape\SearchResponseHistoricalReadinessReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds a historical readiness report across executions', function (): void {
    $executionA = new ScheduleExecution([
        'id' => 101,
        'status' => 'finished',
        'start_time' => Carbon::parse('2026-03-28 10:00:00'),
        'end_time' => Carbon::parse('2026-03-28 10:05:00'),
        'generations' => 30,
        'best_fitness' => 0.93,
    ]);
    $executionA->exists = true;
    $executionA->setAttribute('id', 101);
    $executionA->setRelation('latestMetric', new ScheduleGenerationMetric([
        'generation' => 30,
        'landscape_observation' => [
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
                'status' => 'candidate_ready',
                'headline' => 'Diagnostic candidate ready: basin_lock_escape',
                'resolved_evidence_count' => 6,
                'pending_audits' => 1,
                'best_policy_by_success' => [
                    'policy' => 'basin_lock_escape',
                    'success_rate' => 0.81,
                    'avg_progress_score' => 0.74,
                ],
                'best_policy_by_progress' => [
                    'policy' => 'deep_valley_probe',
                    'success_rate' => 0.52,
                    'avg_progress_score' => 0.79,
                ],
                'latest_outcome' => [
                    'policy' => 'basin_lock_escape',
                    'targets_satisfied' => true,
                    'progress_score' => 0.84,
                ],
                'activation_gate' => [
                    'candidate_policy' => 'basin_lock_escape',
                    'eligible_as_candidate' => true,
                    'blocking_reasons' => [],
                ],
                'blocking_reasons' => [],
                'policy_rows' => [
                    [
                        'policy' => 'basin_lock_escape',
                        'resolved_outcomes' => 4,
                        'success_rate' => 0.81,
                        'avg_progress_score' => 0.74,
                    ],
                    [
                        'policy' => 'deep_valley_probe',
                        'resolved_outcomes' => 2,
                        'success_rate' => 0.52,
                        'avg_progress_score' => 0.79,
                    ],
                ],
            ],
        ],
    ]));

    $executionB = new ScheduleExecution([
        'id' => 102,
        'status' => 'finished',
        'start_time' => Carbon::parse('2026-03-28 11:00:00'),
        'end_time' => Carbon::parse('2026-03-28 11:04:00'),
        'generations' => 24,
        'best_fitness' => 0.88,
    ]);
    $executionB->exists = true;
    $executionB->setAttribute('id', 102);
    $executionB->setRelation('latestMetric', new ScheduleGenerationMetric([
        'generation' => 24,
        'landscape_observation' => [
            'episode_trend' => [
                'direction' => 'improving',
                'strength' => 'strong',
                'headline' => 'Tendencia de melhora',
                'detail' => 'Sequencia recente em recuperacao do landscape.',
                'sequence' => ['deep_valley', 'local_minimum', 'recovered'],
            ],
            'search_response_readiness_dashboard' => [
                'status' => 'collecting_evidence',
                'headline' => 'Collecting evidence before any real activation',
                'resolved_evidence_count' => 3,
                'pending_audits' => 0,
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
                'latest_outcome' => [
                    'policy' => 'deep_valley_probe',
                    'targets_satisfied' => false,
                    'progress_score' => 0.59,
                ],
                'activation_gate' => [
                    'candidate_policy' => null,
                    'eligible_as_candidate' => false,
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
        'id' => 103,
        'status' => 'failed',
        'start_time' => Carbon::parse('2026-03-28 12:00:00'),
        'end_time' => Carbon::parse('2026-03-28 12:01:00'),
        'generations' => 10,
        'best_fitness' => 0.41,
    ]);
    $executionC->exists = true;
    $executionC->setAttribute('id', 103);

    $report = (new SearchResponseHistoricalReadinessReportBuilder)
        ->build([$executionA, $executionB, $executionC])
        ->toArray();

    expect($report['status'])->toBe('candidate_ready')
        ->and($report['compared_executions_count'])->toBe(3)
        ->and($report['executions_with_readiness'])->toBe(2)
        ->and($report['candidate_ready_executions'])->toBe(1)
        ->and($report['worsening_executions'])->toBe(1)
        ->and($report['improving_executions'])->toBe(1)
        ->and($report['real_activation_while_worsening'])->toBe(1)
        ->and($report['real_activation_while_improving'])->toBe(0)
        ->and($report['best_execution_by_success']['execution_id'])->toBe(101)
        ->and($report['best_execution_by_success']['policy'])->toBe('basin_lock_escape')
        ->and($report['best_execution_by_progress']['execution_id'])->toBe(101)
        ->and($report['policy_rows'][0]['policy'])->toBe('basin_lock_escape')
        ->and($report['policy_rows'][0]['candidate_ready_executions'])->toBe(1)
        ->and($report['policy_rows'][0]['worsening_executions'])->toBe(1)
        ->and($report['policy_rows'][1]['policy'])->toBe('deep_valley_probe')
        ->and($report['executions'][1]['blocking_reasons'][0])->toBe('Need more resolved shadow outcomes.')
        ->and($report['executions'][0]['alns_real_activation_applied'])->toBeTrue()
        ->and($report['executions'][2]['has_readiness_signal'])->toBeFalse();
});
