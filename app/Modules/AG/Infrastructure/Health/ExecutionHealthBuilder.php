<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Health;

use App\Modules\AG\Infrastructure\Health\DTO\ExecutionHealthDTO;

final class ExecutionHealthBuilder
{
    /**
     * @param array<string, mixed> $progress
     * @param array<string, mixed> $operationalCounters
     * @param array<string, mixed> $bottlenecks
     */
    public function build(array $progress, array $operationalCounters, array $bottlenecks = []): ExecutionHealthDTO
    {
        $failFastCount = max(0, (int) ($operationalCounters['quality_gate_fail_fast'] ?? 0));
        $qualityGateRejections = max(0, (int) ($operationalCounters['quality_gate_rejections'] ?? 0));
        $repairPasses = max(0, (int) ($operationalCounters['repair_passes'] ?? 0));
        $alnsActivations = max(0, (int) ($operationalCounters['alns_activations'] ?? 0));
        $generationsRecorded = max(0, (int) ($operationalCounters['generations_recorded'] ?? 0));

        $attemptLimit = $this->resolveAttemptLimit($progress, $bottlenecks);

        if ($failFastCount > 0 || ($attemptLimit > 0 && $qualityGateRejections >= $attemptLimit)) {
            $criticalReason = $failFastCount > 0
                ? 'fail_fast_detected'
                : 'quality_gate_rejections_reached_attempt_limit';

            return new ExecutionHealthDTO(
                health: 'critical',
                dominantPhase: 'initial_population',
                recommendations: $this->criticalRecommendations($criticalReason, $qualityGateRejections, $attemptLimit),
            );
        }

        if ($repairPasses >= 3) {
            return new ExecutionHealthDTO(
                health: 'warn',
                dominantPhase: 'repair',
                recommendations: [
                    'O repair esta com pressao elevada; revise conflitos hard residuais e ajuste o quality gate para melhorar a semente inicial.',
                    'Avalie se o CustomConstraintRepairExtension esta atacando os tipos de violacao dominantes nesta execucao.',
                ],
            );
        }

        if ($alnsActivations === 0 && $generationsRecorded >= 30) {
            return new ExecutionHealthDTO(
                health: 'warn',
                dominantPhase: 'evolution',
                recommendations: [
                    'Nao houve ativacoes de ALNS em uma janela longa; ajuste a frequencia dinamica para reduzir risco de estagnacao.',
                    'Reveja os thresholds de landscape e cooldown do ALNS para permitir intensificacao em platôs longos.',
                ],
            );
        }

        return new ExecutionHealthDTO(
            health: 'ok',
            dominantPhase: $this->resolveFallbackPhase($progress),
            recommendations: [
                'Continuar monitorando os heartbeats por fase para detectar degradacao cedo.',
            ],
        );
    }

    /**
     * @param array<string, mixed> $progress
     * @param array<string, mixed> $bottlenecks
     */
    private function resolveAttemptLimit(array $progress, array $bottlenecks): int
    {
        if (is_numeric($progress['attempt_limit'] ?? null)) {
            return max(0, (int) $progress['attempt_limit']);
        }

        if (is_numeric($bottlenecks['current_attempt_limit'] ?? null)) {
            return max(0, (int) $bottlenecks['current_attempt_limit']);
        }

        if (is_numeric($bottlenecks['attempts_recorded'] ?? null)) {
            return max(0, (int) $bottlenecks['attempts_recorded']);
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function criticalRecommendations(string $reason, int $qualityGateRejections, int $attemptLimit): array
    {
        if ($reason === 'fail_fast_detected') {
            return [
                'Falha critica na populacao inicial por fail-fast; priorize aliviar conflitos hard estruturais antes de elevar geracoes.',
                'Execute benchmark A/B com ajuste de construtor inicial e validação de constraints customizadas para reduzir colisoes iniciais.',
            ];
        }

        return [
            sprintf(
                'Quality gate rejeitou %d tentativas (limite %d); revise attempt_limit e as regras de aceite para evitar ciclo improdutivo.',
                $qualityGateRejections,
                $attemptLimit,
            ),
            'Aplique tuning na fase de construcao inicial (alpha/perfil de ilha) para elevar a taxa de sementes viaveis.',
        ];
    }

    /**
     * @param array<string, mixed> $progress
     */
    private function resolveFallbackPhase(array $progress): string
    {
        $phase = (string) ($progress['phase'] ?? '');

        return $phase !== '' ? $phase : 'evolution';
    }
}
