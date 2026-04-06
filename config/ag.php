<?php

return [
    'islands' => (int) env('AG_ISLANDS', 1),
    'migration_interval' => (int) env('AG_MIGRATION_INTERVAL', 5),
    'parallel_evaluation' => true,
    'parallel_evaluation_threshold' => (int) env('AG_PARALLEL_EVALUATION_THRESHOLD', 200),
    'max_workers' => env('AG_MAX_WORKERS', 8),
    'progress' => [
        'log_incomplete_generation_snapshot' => env('AG_LOG_INCOMPLETE_GENERATION_SNAPSHOT', false),
        'incomplete_generation_snapshot_log_ttl_seconds' => (int) env('AG_INCOMPLETE_GENERATION_SNAPSHOT_LOG_TTL_SECONDS', 300),
        'incomplete_generation_snapshot_counter_ttl_seconds' => (int) env('AG_INCOMPLETE_GENERATION_SNAPSHOT_COUNTER_TTL_SECONDS', 43200),
    ],
    'initial_population' => [
        'custom_constraint_repair_extension_enabled' => env('AG_CUSTOM_CONSTRAINT_REPAIR_EXTENSION_ENABLED', true),
        'constructor_portfolio_enabled' => env('AG_CONSTRUCTOR_PORTFOLIO_ENABLED', true),
        'nogoods_signature_enabled' => env('AG_NOGOODS_SIGNATURE_ENABLED', true),
        'nogoods_signature_cache_ttl_days' => (int) env('AG_NOGOODS_SIGNATURE_CACHE_TTL_DAYS', 7),
        'nogoods_signature_max_entries_per_type' => (int) env('AG_NOGOODS_SIGNATURE_MAX_ENTRIES_PER_TYPE', 500),
        'grasp_attempt_time_budget_ms' => (int) env('AG_GRASP_ATTEMPT_TIME_BUDGET_MS', 480000),
        // Piloto de construção paralela: cria indivíduos da população inicial em paralelo.
        // O primeiro indivíduo é sempre construído de forma serial (preserva aprendizado de nogoods).
        // Os demais são construídos em paralelo com instâncias isoladas do problema.
        // Desligado por padrão; ative com AG_PARALLEL_CONSTRUCTION_ENABLED=true.
        'parallel_construction_enabled' => env('AG_PARALLEL_CONSTRUCTION_ENABLED', false),
        'parallel_construction_workers' => (int) env('AG_PARALLEL_CONSTRUCTION_WORKERS', 4),
        'parallel_construction_timeout_seconds' => (int) env('AG_PARALLEL_CONSTRUCTION_TIMEOUT_SECONDS', 60),
        'sync_hybrid_seed' => [
            'enabled' => env('AG_SYNC_HYBRID_SEED_ENABLED', true),
            'min_sync_constraints' => (int) env('AG_SYNC_HYBRID_MIN_SYNC_CONSTRAINTS', 3),
            'min_queue_size' => (int) env('AG_SYNC_HYBRID_MIN_QUEUE_SIZE', 12),
            'max_units' => (int) env('AG_SYNC_HYBRID_MAX_UNITS', 12),
        ],
        'hybrid_cp_assignment' => [
            'enabled' => env('AG_HYBRID_CP_ASSIGNMENT_ENABLED', true),
            'min_quality_gate_rejections' => (int) env('AG_HYBRID_CP_MIN_QG_REJECTIONS', 2),
            'min_peak_hard_conflicts' => (int) env('AG_HYBRID_CP_MIN_PEAK_HARD_CONFLICTS', 2),
            'require_attempt_limit_reduced' => env('AG_HYBRID_CP_REQUIRE_ATTEMPT_LIMIT_REDUCED', false),
        ],
    ],
    'migration' => [
        'strategy' => env('AG_MIGRATION_STRATEGY', 'profile_aware_best'),
        'interval' => (int) env('AG_MIGRATION_INTERVAL', 5),
        'profile_aware' => [
            'conservative_migrants' => (int) env('AG_MIGRATION_CONSERVATIVE_MIGRANTS', 1),
            'balanced_migrants' => (int) env('AG_MIGRATION_BALANCED_MIGRANTS', 2),
            'exploratory_migrants' => (int) env('AG_MIGRATION_EXPLORATORY_MIGRANTS', 3),
            // Matriz direcional: define o perfil preferencial de destino por perfil de origem.
            // null = fallback para round-robin sequencial.
            'directional_matrix' => [
                'conservative' => env('AG_MIGRATION_DIR_CONSERVATIVE_TARGET', 'exploratory'),
                'exploratory' => env('AG_MIGRATION_DIR_EXPLORATORY_TARGET', 'conservative'),
                'balanced' => env('AG_MIGRATION_DIR_BALANCED_TARGET', null),
            ],
        ],
    ],
    'alns_adaptive' => [
        'enabled' => env('AG_ALNS_ADAPTIVE_ENABLED', true),
        'landscape_frequency_divisor' => (int) env('AG_ALNS_LANDSCAPE_FREQUENCY_DIVISOR', 2),
        'cooldown_brake_min_sample_size' => (int) env('AG_ALNS_COOLDOWN_BRAKE_MIN_SAMPLE_SIZE', 3),
        'cooldown_brake_low_success_rate' => (float) env('AG_ALNS_COOLDOWN_BRAKE_LOW_SUCCESS_RATE', 0.34),
        'cooldown_brake_very_low_success_rate' => (float) env('AG_ALNS_COOLDOWN_BRAKE_VERY_LOW_SUCCESS_RATE', 0.15),
        'cooldown_brake_non_positive_improvement' => (float) env('AG_ALNS_COOLDOWN_BRAKE_NON_POSITIVE_IMPROVEMENT', 0.0),
        'cooldown_brake_negative_improvement' => (float) env('AG_ALNS_COOLDOWN_BRAKE_NEGATIVE_IMPROVEMENT', -5.0),
        'cooldown_brake_extra_generations_low_return' => (int) env('AG_ALNS_COOLDOWN_BRAKE_EXTRA_GENERATIONS_LOW_RETURN', 2),
        'cooldown_brake_extra_generations_negative_return' => (int) env('AG_ALNS_COOLDOWN_BRAKE_EXTRA_GENERATIONS_NEGATIVE_RETURN', 3),
    ],
    'alns_step' => [
        // Timeout máximo por passo ALNS (destroy + repair + evaluate).
        // Evita bloquear a fase de evaluating_population por longos períodos sem checkpoint.
        'max_millis' => (int) env('AG_ALNS_STEP_MAX_MILLIS', 15000),
    ],
    'stagnation_policy' => [
        'enabled' => env('AG_STAGNATION_POLICY_ENABLED', true),
        'window_size' => (int) env('AG_STAGNATION_WINDOW_SIZE', 5),
        'min_generations_before_detection' => (int) env('AG_STAGNATION_MIN_GENERATIONS_BEFORE_DETECTION', 8),
        'patience_generations' => (int) env('AG_STAGNATION_PATIENCE_GENERATIONS', 5),
        'improvement_epsilon' => (float) env('AG_STAGNATION_IMPROVEMENT_EPSILON', 0.0005),
        'diversity_high_threshold' => (float) env('AG_STAGNATION_DIVERSITY_HIGH_THRESHOLD', 0.95),
        'diversity_stability_tolerance' => (float) env('AG_STAGNATION_DIVERSITY_STABILITY_TOLERANCE', 0.02),
        'require_alns_no_gain' => env('AG_STAGNATION_REQUIRE_ALNS_NO_GAIN', true),
        'burst' => [
            'enabled' => env('AG_STAGNATION_BURST_ENABLED', true),
            'generations' => (int) env('AG_STAGNATION_BURST_GENERATIONS', 3),
            'mutation_multiplier' => (float) env('AG_STAGNATION_BURST_MUTATION_MULTIPLIER', 1.35),
            'selection_pressure_multiplier' => (float) env('AG_STAGNATION_BURST_SELECTION_PRESSURE_MULTIPLIER', 0.85),
            'force_alns' => env('AG_STAGNATION_BURST_FORCE_ALNS', true),
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
