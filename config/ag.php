<?php

return [
    'islands' => (int) env('AG_ISLANDS', 2),
    'parallel_evaluation' => true,
    'parallel_evaluation_threshold' => (int) env('AG_PARALLEL_EVALUATION_THRESHOLD', 200),
    'max_workers' => env('AG_MAX_WORKERS', 8),
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
