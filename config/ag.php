<?php

return [
    'islands' => (int) env('AG_ISLANDS', 2),
    'parallel_evaluation' => true,
    'parallel_evaluation_threshold' => (int) env('AG_PARALLEL_EVALUATION_THRESHOLD', 200),
    'max_workers' => env('AG_MAX_WORKERS', 8),
    'progress' => [
        'log_incomplete_generation_snapshot' => env('AG_LOG_INCOMPLETE_GENERATION_SNAPSHOT', false),
        'incomplete_generation_snapshot_log_ttl_seconds' => (int) env('AG_INCOMPLETE_GENERATION_SNAPSHOT_LOG_TTL_SECONDS', 300),
        'incomplete_generation_snapshot_counter_ttl_seconds' => (int) env('AG_INCOMPLETE_GENERATION_SNAPSHOT_COUNTER_TTL_SECONDS', 43200),
    ],
    'initial_population' => [
        'hybrid_cp_assignment' => [
            'enabled' => env('AG_HYBRID_CP_ASSIGNMENT_ENABLED', true),
            'min_quality_gate_rejections' => (int) env('AG_HYBRID_CP_MIN_QG_REJECTIONS', 3),
            'min_peak_hard_conflicts' => (int) env('AG_HYBRID_CP_MIN_PEAK_HARD_CONFLICTS', 4),
            'require_attempt_limit_reduced' => env('AG_HYBRID_CP_REQUIRE_ATTEMPT_LIMIT_REDUCED', true),
        ],
    ],
    'log_window_penalty' => env('AG_LOG_WINDOW_PENALTY', false),
    'termination_variance_threshold' => (float) env('AG_TERMINATION_VARIANCE_THRESHOLD', 0.0005),
    'termination_variance_window' => (int) env('AG_TERMINATION_VARIANCE_WINDOW', 8),
    'termination_min_generations_before_variance' => (int) env('AG_TERMINATION_MIN_GENERATIONS_BEFORE_VARIANCE', 20),
    'termination_min_diversity' => (float) env('AG_TERMINATION_MIN_DIVERSITY', 0.08),
    'termination_min_entropy' => (float) env('AG_TERMINATION_MIN_ENTROPY', 0.10),
    'search_response_activation' => [
        'enable_temporary_intensive_alns' => env('AG_ENABLE_TEMPORARY_INTENSIVE_ALNS', false),
        'temporary_intensive_alns_cooldown' => (int) env('AG_TEMPORARY_INTENSIVE_ALNS_COOLDOWN', 2),
        'enable_temporary_mutation_shock' => env('AG_ENABLE_TEMPORARY_MUTATION_SHOCK', false),
        'temporary_mutation_shock_cooldown' => (int) env('AG_TEMPORARY_MUTATION_SHOCK_COOLDOWN', 2),
        'temporary_mutation_shock_duration' => (int) env('AG_TEMPORARY_MUTATION_SHOCK_DURATION', 2),
        'enable_temporary_selection_pressure_reduction' => env('AG_ENABLE_TEMPORARY_SELECTION_PRESSURE_REDUCTION', false),
        'temporary_selection_pressure_reduction_cooldown' => (int) env('AG_TEMPORARY_SELECTION_PRESSURE_REDUCTION_COOLDOWN', 2),
        'temporary_selection_pressure_reduction_duration' => (int) env('AG_TEMPORARY_SELECTION_PRESSURE_REDUCTION_DURATION', 2),
    ],
];
