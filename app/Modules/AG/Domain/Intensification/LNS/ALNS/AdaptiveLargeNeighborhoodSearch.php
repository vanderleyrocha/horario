<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS;

use App\Modules\AG\Domain\Intensification\LNS\Destroy\AdaptiveDestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\DestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\Repair\AdaptiveRepairOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RepairOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class AdaptiveLargeNeighborhoodSearch
{
    private const RECENT_HISTORY_WINDOW = 6;

    private OperatorScoreManager $scores;

    /**
     * @var array<int, array{improvement: float, success: bool}>
     */
    private array $recentOutcomes = [];

    private array $lastTelemetry = [];

    /**
     * @param  DestroyOperatorInterface[]  $destroyOperators
     * @param  RepairOperatorInterface[]  $repairOperators
     */
    public function __construct(
        private array $destroyOperators,
        private array $repairOperators,
        private readonly ?OperatorSelectionStrategy $selector = null
    ) {
        $this->scores = new OperatorScoreManager(
            $destroyOperators,
            $repairOperators
        );
    }

    public function improve(Cromossomo $solution, array $context = []): Cromossomo
    {
        $selector = $this->selector ?? new RouletteWheelSelector;

        /** @var DestroyOperatorInterface $destroy */
        $destroy = $selector->select(
            $this->destroyOperators,
            $this->scores->destroyStats()
        );

        /** @var RepairOperatorInterface $repair */
        $repair = $selector->select(
            $this->repairOperators,
            $this->scores->repairStats()
        );

        $profile = $this->resolveIntensityProfile($solution, $context);
        $this->applyIntensityProfile($destroy, $repair, $profile);
        $this->scores->registerSelection($destroy, $repair);

        $partial = $destroy->destroy($solution);
        $candidate = $repair->repair($partial);
        $finalizeCandidate = $context['finalize_candidate'] ?? null;

        if (is_callable($finalizeCandidate)) {
            $finalized = $finalizeCandidate($candidate);

            if ($finalized instanceof Cromossomo) {
                $candidate = $finalized;
            }
        }

        // 🔧 PRIORIDADE 10: Extrair AffectedRegion para habilitar delta evaluation
        $affectedRegion = $partial->buildAffectedRegion();

        $baselineFitness = $solution->fitness();
        $candidateFitness = $candidate->fitness();
        $improvement = $candidateFitness - $baselineFitness;

        $this->scores->reward($destroy, $repair, $improvement);
        $this->recordOutcome($improvement);

        $this->lastTelemetry = [
            'alns_destroy_operator' => $destroy->getName(),
            'alns_repair_operator' => $repair->getName(),
            'alns_improvement' => $improvement,
            'alns_base_fitness' => $baselineFitness,
            'alns_candidate_fitness' => $candidateFitness,
            'alns_destroy_stats' => $this->scores->destroyStats()[$destroy->getName()] ?? [],
            'alns_repair_stats' => $this->scores->repairStats()[$repair->getName()] ?? [],
            'alns_intensity_profile' => $profile,
            'alns_recent_effectiveness' => $this->recentEffectivenessSummary(),
            // 🔧 PRIORIDADE 10: Incluir AffectedRegion na telemetria
            'alns_affected_region' => [
                'gene_indexes_count' => count($affectedRegion->geneIndexes),
                'professores_count' => count($affectedRegion->professores),
                'turmas_count' => count($affectedRegion->turmas),
                'dias_count' => count($affectedRegion->dias),
            ],
            'alns_removed_genes' => count($partial->unassigned()),
            'alns_remaining_assigned_genes' => count($partial->assigned()),
        ];

        return $candidate;
    }

    public function lastTelemetry(): array
    {
        return $this->lastTelemetry;
    }

    /**
     * @return array<string, float|int>
     */
    public function recentEffectiveness(): array
    {
        return $this->recentEffectivenessSummary();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveIntensityProfile(Cromossomo $solution, array $context): array
    {
        $landscapeObservation = is_array($context['landscape_observation'] ?? null)
            ? $context['landscape_observation']
            : [];
        $triggerTelemetry = is_array($context['trigger'] ?? null)
            ? $context['trigger']
            : [];
        $recent = $this->recentEffectivenessSummary();
        $intensity = 0.35;
        $reasons = [];

        if (($triggerTelemetry['alns_landscape_pressure'] ?? false) === true) {
            $intensity += 0.15;
            $reasons[] = 'landscape_pressure';
        }

        if (($triggerTelemetry['alns_real_activation_applied'] ?? false) === true) {
            $intensity += 0.22;
            $reasons[] = 'activation_gate';
        }

        if (($landscapeObservation['basin_of_attraction_lock_detected'] ?? false) === true) {
            $intensity += 0.20;
            $reasons[] = 'basin_lock';
        }

        $phenomenon = (string) ($landscapeObservation['phenomenon'] ?? '');

        if ($phenomenon === 'deep_valley') {
            $intensity += 0.18;
            $reasons[] = 'deep_valley';
        } elseif ($phenomenon === 'local_minimum') {
            $intensity += 0.12;
            $reasons[] = 'local_minimum';
        }

        if (($recent['success_rate'] ?? 0.0) < 0.35) {
            $intensity += 0.08;
            $reasons[] = 'recent_low_success';
        }

        if (($recent['mean_improvement'] ?? 0.0) > 0.0 && ($recent['success_rate'] ?? 0.0) >= 0.6) {
            $intensity -= 0.06;
            $reasons[] = 'recent_positive_efficacy';
        }

        $intensity = max(0.20, min(0.90, $intensity));
        $destroyRatio = max(0.12, min(0.65, 0.12 + ($intensity * 0.43)));
        $repairIntensity = max(0.35, min(1.0, 0.35 + ($intensity * 0.65)));
        $aggressionLabel = match (true) {
            $intensity >= 0.75 => 'high',
            $intensity >= 0.50 => 'medium',
            default => 'low',
        };

        return [
            'intensity' => round($intensity, 4),
            'aggression_label' => $aggressionLabel,
            'destroy_ratio' => round($destroyRatio, 4),
            'repair_intensity' => round($repairIntensity, 4),
            'solution_size' => $solution->count(),
            'target_removed_genes' => max(1, (int) floor($solution->count() * $destroyRatio)),
            'trigger_reason' => $triggerTelemetry['alns_trigger_reason'] ?? null,
            'real_activation_policy' => $triggerTelemetry['alns_real_activation_policy'] ?? null,
            'real_activation_mode' => $triggerTelemetry['alns_real_activation_mode'] ?? null,
            'recent_mean_improvement' => round((float) ($recent['mean_improvement'] ?? 0.0), 4),
            'recent_success_rate' => round((float) ($recent['success_rate'] ?? 0.0), 4),
            'recent_sample_size' => (int) ($recent['sample_size'] ?? 0),
            'reasons' => $reasons,
        ];
    }

    private function applyIntensityProfile(
        DestroyOperatorInterface $destroy,
        RepairOperatorInterface $repair,
        array $profile
    ): void {
        if ($destroy instanceof AdaptiveDestroyOperatorInterface) {
            $destroy->configureDestroyIntensity((float) ($profile['intensity'] ?? 0.35));
        }

        if ($repair instanceof AdaptiveRepairOperatorInterface) {
            $repair->configureRepairIntensity((float) ($profile['repair_intensity'] ?? 0.35));
        }
    }

    private function recordOutcome(float $improvement): void
    {
        $this->recentOutcomes[] = [
            'improvement' => $improvement,
            'success' => $improvement > 0.0,
        ];

        if (count($this->recentOutcomes) > self::RECENT_HISTORY_WINDOW) {
            $this->recentOutcomes = array_slice($this->recentOutcomes, -self::RECENT_HISTORY_WINDOW);
        }
    }

    /**
     * @return array<string, float|int>
     */
    private function recentEffectivenessSummary(): array
    {
        if ($this->recentOutcomes === []) {
            return [
                'sample_size' => 0,
                'mean_improvement' => 0.0,
                'success_rate' => 0.0,
            ];
        }

        $sampleSize = count($this->recentOutcomes);
        $totalImprovement = array_sum(array_column($this->recentOutcomes, 'improvement'));
        $successes = count(array_filter(
            $this->recentOutcomes,
            static fn (array $outcome): bool => $outcome['success'] === true
        ));

        return [
            'sample_size' => $sampleSize,
            'mean_improvement' => $totalImprovement / $sampleSize,
            'success_rate' => $successes / $sampleSize,
        ];
    }
}
