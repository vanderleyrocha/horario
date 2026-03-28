<?php

use App\Livewire\Algoritmo\ExecutionCenter;
use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
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
        'stagnation' => 2,
        'landscape_observation' => [
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
        'stagnation' => 3,
        'landscape_observation' => [
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

    Livewire::test(ExecutionCenter::class, ['horario' => $horario])
        ->assertSee('Readiness Historico')
        ->assertSee('Comparacao entre execucoes')
        ->assertSee('basin_lock_escape')
        ->assertSee('deep_valley_probe')
        ->assertSee('Need more resolved shadow outcomes.')
        ->assertSee('Abrir Dashboard')
        ->assertSee('Cancelar');
});
