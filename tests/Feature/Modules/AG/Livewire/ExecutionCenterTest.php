<?php

use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use App\Modules\AG\UI\Livewire\ExecutionCenter;
use Livewire\Livewire;

it('exibe o readiness historico entre execucoes no execution center', function (): void {
    $horario = Horario::query()->create([
        'nome' => 'Horario Integrado',
        'ano' => 2026,
        'semestre' => 1,
        'status' => 'rascunho',
    ]);

    $candidateExecution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'finished',
        'start_time' => now()->subMinutes(15),
        'end_time' => now()->subMinutes(12),
        'generations' => 42,
        'best_fitness' => 0.95,
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $candidateExecution->id,
        'generation' => 42,
        'best_fitness' => 0.95,
        'avg_fitness' => 0.9,
        'variance' => 0.01,
        'diversity' => 0.14,
        'entropy' => 0.41,
        'mutation_rate' => 0.2,
        'crossover_rate' => 0.8,
        'operator_used' => 'epsilon_greedy',
        'operator_reward' => 0.12,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'deep_valley',
        'stagnation' => 2,
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
                'status' => 'candidate_ready',
                'headline' => 'Diagnostic candidate ready: basin_lock_escape',
                'resolved_evidence_count' => 5,
                'pending_audits' => 1,
                'best_policy_by_success' => [
                    'policy' => 'basin_lock_escape',
                    'success_rate' => 0.78,
                    'avg_progress_score' => 0.71,
                ],
                'best_policy_by_progress' => [
                    'policy' => 'deep_valley_probe',
                    'success_rate' => 0.49,
                    'avg_progress_score' => 0.76,
                ],
                'latest_outcome' => [
                    'policy' => 'basin_lock_escape',
                    'targets_satisfied' => true,
                    'progress_score' => 0.82,
                ],
                'activation_gate' => [
                    'candidate_policy' => 'basin_lock_escape',
                    'eligible_as_candidate' => true,
                    'supporting_stats' => [
                        'policy' => 'basin_lock_escape',
                    ],
                    'blocking_reasons' => [],
                ],
                'blocking_reasons' => [],
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
                        'success_rate' => 0.49,
                        'avg_progress_score' => 0.76,
                    ],
                ],
            ],
        ],
    ]);

    $collectingExecution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'running',
        'start_time' => now()->subMinutes(6),
        'generations' => 18,
        'best_fitness' => 0.73,
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $collectingExecution->id,
        'generation' => 18,
        'best_fitness' => 0.73,
        'avg_fitness' => 0.69,
        'variance' => 0.02,
        'diversity' => 0.2,
        'entropy' => 0.46,
        'mutation_rate' => 0.22,
        'crossover_rate' => 0.78,
        'operator_used' => 'epsilon_greedy',
        'operator_reward' => 0.04,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'local_minimum',
        'stagnation' => 3,
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
                'status' => 'collecting_evidence',
                'headline' => 'Collecting evidence before any real activation',
                'resolved_evidence_count' => 2,
                'pending_audits' => 2,
                'best_policy_by_success' => [
                    'policy' => 'deep_valley_probe',
                    'success_rate' => 0.56,
                    'avg_progress_score' => 0.6,
                ],
                'best_policy_by_progress' => [
                    'policy' => 'deep_valley_probe',
                    'success_rate' => 0.56,
                    'avg_progress_score' => 0.6,
                ],
                'latest_outcome' => [
                    'policy' => 'deep_valley_probe',
                    'targets_satisfied' => false,
                    'progress_score' => 0.58,
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
                        'resolved_outcomes' => 2,
                        'success_rate' => 0.56,
                        'avg_progress_score' => 0.6,
                    ],
                ],
            ],
        ],
    ]);

    $impactExecution = ScheduleExecution::query()->create([
        'horario_id' => $horario->id,
        'status' => 'finished',
        'start_time' => now()->subMinutes(30),
        'end_time' => now()->subMinutes(26),
        'generations' => 5,
        'best_fitness' => 0.58,
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $impactExecution->id,
        'generation' => 1,
        'best_fitness' => 0.40,
        'avg_fitness' => 0.35,
        'variance' => 0.03,
        'diversity' => 0.22,
        'entropy' => 0.48,
        'mutation_rate' => 0.2,
        'crossover_rate' => 0.8,
        'operator_used' => 'epsilon_greedy',
        'operator_reward' => 0.01,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'local_minimum',
        'stagnation' => 2,
        'landscape_observation' => [
            'basin_of_attraction_lock_confidence' => 0.92,
            'depth_score' => 0.84,
            'best_delta_window' => 0.01,
            'population_turnover' => 0.09,
        ],
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $impactExecution->id,
        'generation' => 2,
        'best_fitness' => 0.42,
        'avg_fitness' => 0.37,
        'variance' => 0.03,
        'diversity' => 0.21,
        'entropy' => 0.47,
        'mutation_rate' => 0.2,
        'crossover_rate' => 0.8,
        'operator_used' => 'epsilon_greedy',
        'operator_reward' => 0.01,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'local_minimum',
        'stagnation' => 2,
        'landscape_observation' => [
            'basin_of_attraction_lock_confidence' => 0.90,
            'depth_score' => 0.80,
            'best_delta_window' => 0.02,
            'population_turnover' => 0.10,
        ],
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $impactExecution->id,
        'generation' => 3,
        'best_fitness' => 0.44,
        'avg_fitness' => 0.39,
        'variance' => 0.03,
        'diversity' => 0.2,
        'entropy' => 0.46,
        'mutation_rate' => 0.2,
        'crossover_rate' => 0.8,
        'operator_used' => 'epsilon_greedy',
        'operator_reward' => 0.01,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'deep_valley',
        'stagnation' => 3,
        'landscape_observation' => [
            'episode_trend' => [
                'direction' => 'worsening',
                'strength' => 'moderate',
                'headline' => 'Sinal de piora',
                'detail' => 'Ativacao ocorreu ainda em fase de piora.',
                'sequence' => ['plateau', 'local_minimum'],
            ],
            'alns_trigger' => [
                'real_activation' => [
                    'applied' => true,
                    'policy' => 'basin_lock_escape',
                ],
            ],
        ],
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $impactExecution->id,
        'generation' => 4,
        'best_fitness' => 0.55,
        'avg_fitness' => 0.48,
        'variance' => 0.02,
        'diversity' => 0.26,
        'entropy' => 0.52,
        'mutation_rate' => 0.2,
        'crossover_rate' => 0.8,
        'operator_used' => 'epsilon_greedy',
        'operator_reward' => 0.05,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'local_minimum',
        'stagnation' => 1,
        'landscape_observation' => [
            'basin_of_attraction_lock_confidence' => 0.70,
            'depth_score' => 0.56,
            'best_delta_window' => 0.08,
            'population_turnover' => 0.24,
        ],
    ]);

    ScheduleGenerationMetric::query()->create([
        'execution_id' => $impactExecution->id,
        'generation' => 5,
        'best_fitness' => 0.58,
        'avg_fitness' => 0.50,
        'variance' => 0.02,
        'diversity' => 0.28,
        'entropy' => 0.54,
        'mutation_rate' => 0.2,
        'crossover_rate' => 0.8,
        'operator_used' => 'epsilon_greedy',
        'operator_reward' => 0.05,
        'landscape_state' => 'stagnation',
        'landscape_phenomenon' => 'recovered',
        'stagnation' => 0,
        'landscape_observation' => [
            'basin_of_attraction_lock_confidence' => 0.62,
            'depth_score' => 0.48,
            'best_delta_window' => 0.10,
            'population_turnover' => 0.26,
        ],
    ]);

    Livewire::test(ExecutionCenter::class, ['horario' => $horario])
        ->assertSee('Readiness Historico')
        ->assertSee('Comparacao entre execucoes')
        ->assertSee('Policy Readiness Global')
        ->assertSee('Readiness agregado por policy')
        ->assertSee('basin_lock_escape')
        ->assertSee('deep_valley_probe')
        ->assertSee('Mais perto do gate')
        ->assertSee('stagnation')
        ->assertSee('deep_valley')
        ->assertSee('Execucoes em worsening')
        ->assertSee('Execucoes em improving')
        ->assertSee('Near gate em worsening')
        ->assertSee('Near gate em improving')
        ->assertSee('ALNS real em worsening')
        ->assertSee('ALNS real em improving')
        ->assertSee('Melhor contexto para ativacao')
        ->assertSee('Impacto temporal da ativacao real')
        ->assertSee('Ativacoes analisadas')
        ->assertSee('Melhor contexto temporal')
        ->assertSee('Delta medio best fitness')
        ->assertSee('Impacto')
        ->assertSee('Sinal de piora')
        ->assertSee('Tendencia de melhora')
        ->assertSee('Ativacao adaptativa aplicada')
        ->assertSee('Need more resolved shadow outcomes.')
        ->assertSee('Abrir Dashboard')
        ->assertSee('Cancelar');
});
