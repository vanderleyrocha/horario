<?php

use App\Models\Horario;
use App\Modules\AG\Infrastructure\Logging\GATelemetryLogger;
use App\Modules\AG\Infrastructure\Metrics\ExecutionMetricsRecorder;
use App\Modules\AG\Infrastructure\Progress\CacheAndDbProgressReporter;
use App\Modules\AG\Infrastructure\Progress\CacheProgressReporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('flushes generation metrics immediately and publishes the current metric to cache', function () {
    $horario = Horario::factory()->create();

    $recorder = new ExecutionMetricsRecorder;
    $executionId = $recorder->startExecution(
        horarioId: $horario->id,
        populationSize: 120,
        generations: 500,
        parameters: ['populacao' => 120, 'geracoes' => 500]
    );

    $reporter = new CacheAndDbProgressReporter(
        new CacheProgressReporter($horario->id),
        $recorder,
        app(GATelemetryLogger::class),
        $horario->id
    );

    $reporter->report([
        'phase' => 'evolving',
        'generation' => 5,
        'best_fitness' => 91.25,
        'avg_fitness' => 84.10,
        'variance' => 1.4,
        'diversity' => 0.66,
        'entropy' => 0.72,
        'mutation_rate' => 0.14,
        'stagnation' => 2,
        'landscape_state' => 'equilibrado',
        'landscape_phenomenon' => 'local_minimum',
        'landscape_observation' => [
            'phenomenon' => 'local_minimum',
            'confidence' => 0.81,
            'depth_score' => 0.74,
            'best_delta' => 0.0,
            'best_delta_window' => -0.015,
            'avg_delta_window' => -0.008,
            'improvement_acceptance_rate' => 0.12,
            'worsening_acceptance_rate' => 0.58,
            'population_turnover' => 0.18,
            'best_signature_changed' => false,
            'elite_similarity' => 0.82,
            'current_episode' => [
                'phenomenon' => 'local_minimum',
                'start_generation' => 3,
                'last_generation' => 5,
                'duration' => 3,
                'peak_confidence' => 0.83,
                'peak_depth_score' => 0.76,
                'avg_population_turnover' => 0.17,
                'avg_elite_similarity' => 0.80,
                'stable_best_signature_rate' => 1.0,
                'active' => true,
                'exit_mode' => 'active',
            ],
            'previous_episode' => [
                'phenomenon' => 'plateau',
                'start_generation' => 1,
                'last_generation' => 2,
                'duration' => 2,
                'peak_confidence' => 0.54,
                'peak_depth_score' => 0.50,
                'avg_population_turnover' => 0.34,
                'avg_elite_similarity' => 0.61,
                'stable_best_signature_rate' => 0.5,
                'active' => false,
                'exit_mode' => 'phenomenon_shift',
            ],
            'recent_episode_history' => [
                [
                    'phenomenon' => 'plateau',
                    'start_generation' => 1,
                    'last_generation' => 2,
                    'duration' => 2,
                    'peak_confidence' => 0.54,
                    'peak_depth_score' => 0.50,
                    'avg_population_turnover' => 0.34,
                    'avg_elite_similarity' => 0.61,
                    'stable_best_signature_rate' => 0.5,
                    'active' => false,
                    'exit_mode' => 'phenomenon_shift',
                ],
            ],
            'episode_trend' => [
                'direction' => 'worsening',
                'strength' => 'moderate',
                'headline' => 'Sinal de piora',
                'detail' => 'A sequencia recente aumentou a intensidade do landscape.',
                'sequence' => ['plateau', 'local_minimum'],
            ],
            'basin_of_attraction_lock_confidence' => 0.77,
            'basin_of_attraction_lock_detected' => true,
            'search_response_simulation' => [
                'policy' => 'basin_lock_escape',
                'simulated_only' => true,
                'would_escalate' => true,
                'target_state' => 'exploration',
                'mutation_multiplier' => 3.0,
                'activate_alns' => true,
                'selection_pressure_multiplier' => 0.65,
                'diversification_boost' => 0.85,
                'reason' => 'Persistent basin-of-attraction lock with stable elite signature and low turnover.',
            ],
            'selection_pressure' => [
                'supported' => true,
                'source' => 'activation_gate',
                'landscape_state' => 'premature_convergence',
                'base_multiplier' => 0.8,
                'effective_multiplier' => 0.65,
                'state' => 'reduced',
                'base_tournament_size' => 3,
                'effective_tournament_size' => 2,
                'real_reduction' => [
                    'enabled' => true,
                    'requested' => true,
                    'applied' => true,
                    'policy' => 'basin_lock_escape',
                    'mode' => 'opt_in',
                    'cooldown_generations' => 2,
                    'duration_generations' => 2,
                    'multiplier' => 0.65,
                    'effective_from_generation' => 6,
                    'reason' => 'Temporary selection pressure reduction armed via SearchResponseActivationGate.',
                ],
                'reduction_active' => true,
                'reduction_multiplier' => 0.65,
                'reduction_policy' => 'basin_lock_escape',
                'reduction_reason' => 'Temporary selection pressure reduction armed via SearchResponseActivationGate.',
                'reduction_activated_generation' => 5,
                'remaining_generations_before' => 2,
                'remaining_generations_after' => 1,
            ],
            'search_response_audit' => [
                'policy' => 'basin_lock_escape',
                'audit_generation' => 5,
                'shadow_mode' => true,
                'would_trigger' => true,
                'evaluation_horizon_generations' => 6,
                'target_best_delta_window' => 0.03,
                'target_population_turnover' => 0.45,
                'target_max_elite_similarity' => 0.62,
                'target_max_basin_lock_confidence' => 0.45,
                'observed_best_delta_window' => -0.015,
                'observed_population_turnover' => 0.18,
                'observed_elite_similarity' => 0.82,
                'observed_basin_lock_confidence' => 0.77,
                'best_delta_window_gap' => 0.045,
                'population_turnover_gap' => 0.27,
                'elite_similarity_reduction_needed' => 0.2,
                'basin_lock_confidence_reduction_needed' => 0.32,
                'reason' => 'Persistent basin-of-attraction lock with stable elite signature and low turnover. Episode duration=3',
            ],
            'search_response_outcome' => [
                'policy' => 'basin_lock_escape',
                'audit_generation' => 5,
                'resolved_generation' => 11,
                'horizon_generations' => 6,
                'shadow_mode' => true,
                'would_trigger' => true,
                'targets_satisfied' => false,
                'approached_targets' => true,
                'progress_score' => 0.67,
                'best_delta_window_progress' => 0.72,
                'population_turnover_progress' => 0.62,
                'elite_similarity_progress' => 0.70,
                'basin_lock_confidence_progress' => 0.64,
                'observed_best_delta_window' => 0.012,
                'observed_population_turnover' => 0.32,
                'observed_elite_similarity' => 0.72,
                'observed_basin_lock_confidence' => 0.58,
                'reason' => 'Persistent basin-of-attraction lock with stable elite signature and low turnover. Episode duration=3',
            ],
            'search_response_pending_audits' => 2,
            'search_response_effectiveness_report' => [
                'total_resolved_outcomes' => 6,
                'best_policy_by_success' => 'basin_lock_escape',
                'best_policy_by_progress' => 'deep_valley_probe',
                'policies' => [
                    [
                        'policy' => 'basin_lock_escape',
                        'resolved_outcomes' => 4,
                        'targets_satisfied_count' => 3,
                        'approached_targets_count' => 4,
                        'avg_progress_score' => 0.76,
                        'success_rate' => 0.75,
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
            'search_response_activation_gate' => [
                'eligible_as_candidate' => true,
                'candidate_policy' => 'basin_lock_escape',
                'mode' => 'diagnostic_only',
                'minimum_resolved_outcomes' => 3,
                'minimum_success_rate' => 0.6,
                'minimum_approach_rate' => 0.75,
                'minimum_avg_progress_score' => 0.65,
                'supporting_stats' => [
                    'policy' => 'basin_lock_escape',
                    'resolved_outcomes' => 4,
                    'targets_satisfied_count' => 3,
                    'approached_targets_count' => 4,
                    'avg_progress_score' => 0.76,
                    'success_rate' => 0.75,
                    'approach_rate' => 1.0,
                ],
                'blocking_reasons' => [],
                'reason' => 'Policy crossed the evidence thresholds and is now a diagnostic candidate for future real activation.',
            ],
            'search_response_readiness_dashboard' => [
                'status' => 'candidate_ready',
                'headline' => 'Diagnostic candidate ready: basin_lock_escape',
                'resolved_evidence_count' => 6,
                'pending_audits' => 2,
                'best_policy_by_success' => [
                    'policy' => 'basin_lock_escape',
                    'resolved_outcomes' => 4,
                    'targets_satisfied_count' => 3,
                    'approached_targets_count' => 4,
                    'avg_progress_score' => 0.76,
                    'success_rate' => 0.75,
                    'approach_rate' => 1.0,
                ],
                'best_policy_by_progress' => [
                    'policy' => 'deep_valley_probe',
                    'resolved_outcomes' => 2,
                    'targets_satisfied_count' => 0,
                    'approached_targets_count' => 2,
                    'avg_progress_score' => 0.79,
                    'success_rate' => 0.0,
                    'approach_rate' => 1.0,
                ],
                'latest_outcome' => [
                    'policy' => 'basin_lock_escape',
                    'audit_generation' => 5,
                    'resolved_generation' => 11,
                    'targets_satisfied' => false,
                    'approached_targets' => true,
                    'progress_score' => 0.67,
                ],
                'activation_gate' => [
                    'eligible_as_candidate' => true,
                    'candidate_policy' => 'basin_lock_escape',
                    'mode' => 'diagnostic_only',
                    'minimum_resolved_outcomes' => 3,
                    'minimum_success_rate' => 0.6,
                    'minimum_approach_rate' => 0.75,
                    'minimum_avg_progress_score' => 0.65,
                    'supporting_stats' => [
                        'policy' => 'basin_lock_escape',
                        'resolved_outcomes' => 4,
                        'targets_satisfied_count' => 3,
                        'approached_targets_count' => 4,
                        'avg_progress_score' => 0.76,
                        'success_rate' => 0.75,
                        'approach_rate' => 1.0,
                    ],
                    'blocking_reasons' => [],
                    'reason' => 'Policy crossed the evidence thresholds and is now a diagnostic candidate for future real activation.',
                ],
                'blocking_reasons' => [],
                'policy_rows' => [
                    [
                        'policy' => 'basin_lock_escape',
                        'resolved_outcomes' => 4,
                        'targets_satisfied_count' => 3,
                        'approached_targets_count' => 4,
                        'avg_progress_score' => 0.76,
                        'success_rate' => 0.75,
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
            'alns_trigger' => [
                'base_frequency' => 10,
                'budget_frequency' => 5,
                'landscape_frequency' => 3,
                'effective_frequency' => 3,
                'base_cooldown_generations' => 1,
                'cooldown_generations' => 1,
                'cooldown_brake' => [
                    'applied' => true,
                    'extra_generations' => 3,
                    'reason' => 'Recent ALNS outcomes are consistently negative or null.',
                ],
                'recent_effectiveness' => [
                    'sample_size' => 3,
                    'mean_improvement' => 0.0,
                    'success_rate' => 0.0,
                ],
                'generations_since_last_trigger' => 2,
                'landscape_pressure' => true,
                'eligible' => true,
                'triggered' => true,
                'reason' => 'budget_interval+landscape_pressure',
                'sources' => ['budget_interval', 'landscape_pressure'],
                'real_activation' => [
                    'enabled' => true,
                    'requested' => true,
                    'applied' => true,
                    'policy' => 'basin_lock_escape',
                    'mode' => 'opt_in',
                    'cooldown_generations' => 2,
                    'reason' => 'Temporary intensive ALNS activated via SearchResponseActivationGate.',
                ],
                'response' => [
                    'destroy_operator' => 'ConflictDestroy',
                    'repair_operator' => 'RegretInsertion',
                    'improvement' => 0.42,
                    'aggression_label' => 'high',
                    'intensity' => 0.81,
                    'destroy_ratio' => 0.47,
                    'repair_intensity' => 0.88,
                    'target_removed_genes' => 6,
                    'removed_genes' => 6,
                    'remaining_assigned_genes' => 12,
                    'recent_mean_improvement' => 0.21,
                    'recent_success_rate' => 0.67,
                    'recent_sample_size' => 3,
                    'reasons' => ['landscape_pressure', 'basin_lock', 'deep_valley'],
                ],
            ],
        ],
        'operator_used' => 'StructuredSwapMutation',
        'operator_reward' => 0.18,
        'alns_destroy_operator' => 'ConflictDestroy',
        'alns_repair_operator' => 'RegretInsertion',
        'alns_improvement' => 0.42,
    ]);

    $storedMetric = DB::table('schedule_generation_metrics')
        ->where('execution_id', $executionId)
        ->where('generation', 5)
        ->first();

    $cachedMetric = Cache::get("ga_execution_metrics_{$executionId}");

    expect($storedMetric)->not->toBeNull()
        ->and((int) $storedMetric->generation)->toBe(5)
        ->and((float) $storedMetric->best_fitness)->toBe(91.25)
        ->and($storedMetric->landscape_phenomenon)->toBe('local_minimum')
        ->and($storedMetric->alns_destroy_operator)->toBe('ConflictDestroy')
        ->and($storedMetric->alns_repair_operator)->toBe('RegretInsertion')
        ->and((float) $storedMetric->alns_improvement)->toBe(0.42)
        ->and($cachedMetric)->toMatchArray([
            'execution_id' => $executionId,
            'generation' => 5,
            'best_fitness' => 91.25,
            'avg_fitness' => 84.10,
            'operator_used' => 'StructuredSwapMutation',
            'landscape_state' => 'equilibrado',
            'landscape_phenomenon' => 'local_minimum',
            'alns_destroy_operator' => 'ConflictDestroy',
            'alns_repair_operator' => 'RegretInsertion',
        ]);

    expect($cachedMetric['landscape_observation'] ?? null)->toBeArray()
        ->and($cachedMetric['landscape_observation']['confidence'] ?? null)->toBe(0.81)
        ->and($cachedMetric['landscape_observation']['population_turnover'] ?? null)->toBe(0.18)
        ->and($cachedMetric['landscape_observation']['elite_similarity'] ?? null)->toBe(0.82)
        ->and($cachedMetric['landscape_observation']['best_signature_changed'] ?? null)->toBeFalse()
        ->and($cachedMetric['landscape_observation']['basin_of_attraction_lock_detected'] ?? null)->toBeTrue()
        ->and($cachedMetric['landscape_observation']['current_episode']['duration'] ?? null)->toBe(3)
        ->and($cachedMetric['landscape_observation']['episode_trend']['direction'] ?? null)->toBe('worsening')
        ->and($cachedMetric['landscape_observation']['alns_trigger']['effective_frequency'] ?? null)->toBe(3)
        ->and($cachedMetric['landscape_observation']['alns_trigger']['base_cooldown_generations'] ?? null)->toBe(1)
        ->and($cachedMetric['landscape_observation']['alns_trigger']['cooldown_brake']['applied'] ?? null)->toBeTrue()
        ->and($cachedMetric['landscape_observation']['alns_trigger']['cooldown_brake']['extra_generations'] ?? null)->toBe(3)
        ->and($cachedMetric['landscape_observation']['alns_trigger']['recent_effectiveness']['sample_size'] ?? null)->toBe(3)
        ->and($cachedMetric['landscape_observation']['alns_trigger']['reason'] ?? null)->toBe('budget_interval+landscape_pressure')
        ->and($cachedMetric['landscape_observation']['alns_trigger']['real_activation']['applied'] ?? null)->toBeTrue()
        ->and($cachedMetric['landscape_observation']['alns_trigger']['real_activation']['policy'] ?? null)->toBe('basin_lock_escape')
        ->and($cachedMetric['landscape_observation']['alns_trigger']['response']['aggression_label'] ?? null)->toBe('high')
        ->and($cachedMetric['landscape_observation']['alns_trigger']['response']['destroy_ratio'] ?? null)->toBe(0.47)
        ->and($cachedMetric['landscape_observation']['alns_trigger']['response']['recent_success_rate'] ?? null)->toBe(0.67)
        ->and($cachedMetric['landscape_observation']['search_response_simulation']['policy'] ?? null)->toBe('basin_lock_escape')
        ->and($cachedMetric['landscape_observation']['selection_pressure']['effective_multiplier'] ?? null)->toBe(0.65)
        ->and($cachedMetric['landscape_observation']['selection_pressure']['effective_tournament_size'] ?? null)->toBe(2)
        ->and($cachedMetric['landscape_observation']['selection_pressure']['real_reduction']['applied'] ?? null)->toBeTrue()
        ->and($cachedMetric['landscape_observation']['search_response_audit']['target_population_turnover'] ?? null)->toBe(0.45)
        ->and($cachedMetric['landscape_observation']['search_response_outcome']['progress_score'] ?? null)->toBe(0.67)
        ->and($cachedMetric['landscape_observation']['search_response_pending_audits'] ?? null)->toBe(2)
        ->and($cachedMetric['landscape_observation']['search_response_effectiveness_report']['best_policy_by_success'] ?? null)->toBe('basin_lock_escape')
        ->and($cachedMetric['landscape_observation']['search_response_activation_gate']['candidate_policy'] ?? null)->toBe('basin_lock_escape')
        ->and($cachedMetric['landscape_observation']['search_response_readiness_dashboard']['status'] ?? null)->toBe('candidate_ready');

    $storedObservation = json_decode((string) $storedMetric->landscape_observation, true);

    expect($storedObservation)->toBeArray()
        ->and($storedObservation['best_delta_window'] ?? null)->toBe(-0.015)
        ->and($storedObservation['avg_delta_window'] ?? null)->toBe(-0.008)
        ->and($storedObservation['previous_episode']['exit_mode'] ?? null)->toBe('phenomenon_shift')
        ->and($storedObservation['episode_trend']['headline'] ?? null)->toBe('Sinal de piora')
        ->and($storedObservation['basin_of_attraction_lock_confidence'] ?? null)->toBe(0.77)
        ->and($storedObservation['search_response_simulation']['activate_alns'] ?? null)->toBeTrue()
        ->and($storedObservation['selection_pressure']['supported'] ?? null)->toBeTrue()
        ->and($storedObservation['selection_pressure']['source'] ?? null)->toBe('activation_gate')
        ->and($storedObservation['selection_pressure']['real_reduction']['policy'] ?? null)->toBe('basin_lock_escape')
        ->and($storedObservation['search_response_audit']['best_delta_window_gap'] ?? null)->toBe(0.045)
        ->and($storedObservation['search_response_audit']['evaluation_horizon_generations'] ?? null)->toBe(6)
        ->and($storedObservation['search_response_outcome']['resolved_generation'] ?? null)->toBe(11)
        ->and($storedObservation['search_response_outcome']['approached_targets'] ?? null)->toBeTrue()
        ->and($storedObservation['search_response_effectiveness_report']['best_policy_by_progress'] ?? null)->toBe('deep_valley_probe')
        ->and($storedObservation['search_response_effectiveness_report']['policies'][0]['resolved_outcomes'] ?? null)->toBe(4)
        ->and($storedObservation['search_response_activation_gate']['eligible_as_candidate'] ?? null)->toBeTrue()
        ->and($storedObservation['search_response_readiness_dashboard']['best_policy_by_success']['policy'] ?? null)->toBe('basin_lock_escape')
        ->and($storedObservation['alns_trigger']['sources'] ?? null)->toBe(['budget_interval', 'landscape_pressure'])
        ->and($storedObservation['alns_trigger']['cooldown_brake']['applied'] ?? null)->toBeTrue()
        ->and($storedObservation['alns_trigger']['cooldown_brake']['reason'] ?? null)->toBe('Recent ALNS outcomes are consistently negative or null.')
        ->and($storedObservation['alns_trigger']['real_activation']['mode'] ?? null)->toBe('opt_in')
        ->and($storedObservation['alns_trigger']['response']['repair_intensity'] ?? null)->toBe(0.88)
        ->and($storedObservation['alns_trigger']['response']['reasons'] ?? null)->toBe(['landscape_pressure', 'basin_lock', 'deep_valley']);
});

it('publica o encerramento terminal da execucao no cache especifico da execucao', function () {
    $horario = Horario::factory()->create();
    $reporter = new CacheProgressReporter($horario->id);

    $reporter->reportFailed(33, [
        'title' => 'Execucao interrompida por falha',
        'reason' => 'A populacao inicial acumulou conflitos hard demais.',
        'phase' => 'initial_population',
        'stage' => 'quality_gate_fail_fast',
        'initial_population_bottlenecks' => [
            'fail_fast_count' => 6,
        ],
    ]);

    $progress = Cache::get('ga_execution_progress_33');

    expect($progress)->toBeArray()
        ->and($progress['execution_status'] ?? null)->toBe('failed')
        ->and($progress['phase'] ?? null)->toBe('terminal')
        ->and($progress['reason'] ?? null)->toBe('A populacao inicial acumulou conflitos hard demais.')
        ->and($progress['initial_population_bottlenecks']['fail_fast_count'] ?? null)->toBe(6);
});
