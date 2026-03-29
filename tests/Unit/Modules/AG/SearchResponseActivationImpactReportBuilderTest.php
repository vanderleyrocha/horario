<?php

use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use App\Modules\AG\Domain\Landscape\SearchResponseActivationImpactReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('measures temporal impact around real alns activation windows', function (): void {
    $worseningExecution = new ScheduleExecution([
        'id' => 301,
        'status' => 'finished',
        'generations' => 5,
        'best_fitness' => 0.58,
    ]);
    $worseningExecution->exists = true;
    $worseningExecution->setAttribute('id', 301);
    $worseningExecution->setRelation('metrics', collect([
        new ScheduleGenerationMetric([
            'generation' => 1,
            'best_fitness' => 0.40,
            'avg_fitness' => 0.35,
            'landscape_observation' => [
                'basin_of_attraction_lock_confidence' => 0.92,
                'depth_score' => 0.84,
                'best_delta_window' => 0.01,
                'population_turnover' => 0.09,
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 2,
            'best_fitness' => 0.42,
            'avg_fitness' => 0.37,
            'landscape_observation' => [
                'basin_of_attraction_lock_confidence' => 0.90,
                'depth_score' => 0.80,
                'best_delta_window' => 0.02,
                'population_turnover' => 0.10,
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 3,
            'best_fitness' => 0.44,
            'avg_fitness' => 0.39,
            'landscape_observation' => [
                'episode_trend' => [
                    'direction' => 'worsening',
                    'headline' => 'Sinal de piora',
                ],
                'alns_trigger' => [
                    'real_activation' => [
                        'applied' => true,
                        'policy' => 'basin_lock_escape',
                    ],
                ],
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 4,
            'best_fitness' => 0.55,
            'avg_fitness' => 0.48,
            'landscape_observation' => [
                'basin_of_attraction_lock_confidence' => 0.70,
                'depth_score' => 0.56,
                'best_delta_window' => 0.08,
                'population_turnover' => 0.24,
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 5,
            'best_fitness' => 0.58,
            'avg_fitness' => 0.50,
            'landscape_observation' => [
                'basin_of_attraction_lock_confidence' => 0.62,
                'depth_score' => 0.48,
                'best_delta_window' => 0.10,
                'population_turnover' => 0.26,
            ],
        ]),
    ]));

    $improvingExecution = new ScheduleExecution([
        'id' => 302,
        'status' => 'finished',
        'generations' => 5,
        'best_fitness' => 0.71,
    ]);
    $improvingExecution->exists = true;
    $improvingExecution->setAttribute('id', 302);
    $improvingExecution->setRelation('metrics', collect([
        new ScheduleGenerationMetric([
            'generation' => 1,
            'best_fitness' => 0.60,
            'avg_fitness' => 0.55,
            'landscape_observation' => [
                'basin_of_attraction_lock_confidence' => 0.64,
                'depth_score' => 0.52,
                'best_delta_window' => 0.05,
                'population_turnover' => 0.18,
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 2,
            'best_fitness' => 0.62,
            'avg_fitness' => 0.57,
            'landscape_observation' => [
                'basin_of_attraction_lock_confidence' => 0.60,
                'depth_score' => 0.48,
                'best_delta_window' => 0.06,
                'population_turnover' => 0.19,
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 3,
            'best_fitness' => 0.63,
            'avg_fitness' => 0.58,
            'landscape_observation' => [
                'episode_trend' => [
                    'direction' => 'improving',
                    'headline' => 'Tendencia de melhora',
                ],
                'alns_trigger' => [
                    'real_activation' => [
                        'applied' => true,
                        'policy' => 'deep_valley_probe',
                    ],
                ],
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 4,
            'best_fitness' => 0.66,
            'avg_fitness' => 0.59,
            'landscape_observation' => [
                'basin_of_attraction_lock_confidence' => 0.56,
                'depth_score' => 0.43,
                'best_delta_window' => 0.07,
                'population_turnover' => 0.20,
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 5,
            'best_fitness' => 0.67,
            'avg_fitness' => 0.60,
            'landscape_observation' => [
                'basin_of_attraction_lock_confidence' => 0.54,
                'depth_score' => 0.40,
                'best_delta_window' => 0.08,
                'population_turnover' => 0.22,
            ],
        ]),
    ]));

    $edgeExecution = new ScheduleExecution([
        'id' => 303,
        'status' => 'finished',
        'generations' => 2,
        'best_fitness' => 0.32,
    ]);
    $edgeExecution->exists = true;
    $edgeExecution->setAttribute('id', 303);
    $edgeExecution->setRelation('metrics', collect([
        new ScheduleGenerationMetric([
            'generation' => 1,
            'best_fitness' => 0.32,
            'avg_fitness' => 0.28,
            'landscape_observation' => [
                'episode_trend' => [
                    'direction' => 'worsening',
                    'headline' => 'Sinal de piora',
                ],
                'alns_trigger' => [
                    'real_activation' => [
                        'applied' => true,
                        'policy' => 'basin_lock_escape',
                    ],
                ],
            ],
        ]),
        new ScheduleGenerationMetric([
            'generation' => 2,
            'best_fitness' => 0.33,
            'avg_fitness' => 0.29,
            'landscape_observation' => [],
        ]),
    ]));

    $report = (new SearchResponseActivationImpactReportBuilder)
        ->build([$worseningExecution, $improvingExecution, $edgeExecution], windowSize: 2)
        ->toArray();

    expect($report['status'])->toBe('activation_impact_measured')
        ->and($report['window_size'])->toBe(2)
        ->and($report['analyzed_activation_events'])->toBe(2)
        ->and($report['ignored_edge_activation_events'])->toBe(1)
        ->and($report['positive_impact_events'])->toBe(2)
        ->and($report['negative_impact_events'])->toBe(0)
        ->and($report['better_trend_context'])->toBe('worsening')
        ->and($report['trend_comparison']['worsening']['activation_events'])->toBe(1)
        ->and($report['trend_comparison']['worsening']['avg_best_fitness_delta'])->toBe(0.155)
        ->and($report['trend_comparison']['worsening']['avg_basin_lock_delta'])->toBe(-0.25)
        ->and($report['trend_comparison']['improving']['activation_events'])->toBe(1)
        ->and($report['trend_comparison']['improving']['avg_best_fitness_delta'])->toBe(0.055)
        ->and($report['events'][0]['execution_id'])->toBe(302)
        ->and($report['events'][1]['execution_id'])->toBe(301)
        ->and($report['events'][1]['impact_label'])->toBe('positive');
});
