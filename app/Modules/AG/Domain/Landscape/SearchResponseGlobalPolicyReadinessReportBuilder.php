<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

use App\Models\ScheduleExecution;

final class SearchResponseGlobalPolicyReadinessReportBuilder
{
    /**
     * @param  iterable<int, ScheduleExecution>  $executions
     */
    public function build(iterable $executions): SearchResponseGlobalPolicyReadinessReport
    {
        $policyBuckets = [];
        $windowExecutionsCount = 0;
        $worseningOccurrences = 0;
        $improvingOccurrences = 0;
        $activationTrendComparison = [
            'worsening' => [
                'occurrences' => 0,
                'real_activation_occurrences' => 0,
                'avg_success_rate_sum' => 0.0,
                'avg_success_rate_count' => 0,
                'avg_progress_score_sum' => 0.0,
                'avg_progress_score_count' => 0,
            ],
            'improving' => [
                'occurrences' => 0,
                'real_activation_occurrences' => 0,
                'avg_success_rate_sum' => 0.0,
                'avg_success_rate_count' => 0,
                'avg_progress_score_sum' => 0.0,
                'avg_progress_score_count' => 0,
            ],
        ];

        foreach ($executions as $execution) {
            $windowExecutionsCount++;

            $observation = is_array($execution->latestMetric?->landscape_observation)
                ? $execution->latestMetric->landscape_observation
                : [];
            $readiness = is_array($observation['search_response_readiness_dashboard'] ?? null)
                ? $observation['search_response_readiness_dashboard']
                : [];
            $gate = is_array($readiness['activation_gate'] ?? null)
                ? $readiness['activation_gate']
                : (is_array($observation['search_response_activation_gate'] ?? null) ? $observation['search_response_activation_gate'] : []);
            $policyRows = is_array($readiness['policy_rows'] ?? null)
                ? array_values($readiness['policy_rows'])
                : [];
            $resolvedEvidenceCount = (int) ($readiness['resolved_evidence_count'] ?? 0);
            $candidateEligible = (bool) ($gate['eligible_as_candidate'] ?? false);
            $candidatePolicy = $this->nullableString($gate['candidate_policy'] ?? null);
            $supportingPolicy = $this->nullableString($gate['supporting_stats']['policy'] ?? null);
            $bestBySuccessPolicy = $this->nullableString($readiness['best_policy_by_success']['policy'] ?? null);
            $bestByProgressPolicy = $this->nullableString($readiness['best_policy_by_progress']['policy'] ?? null);
            $nearGatePolicy = $candidatePolicy
                ?? $supportingPolicy
                ?? $bestBySuccessPolicy
                ?? $bestByProgressPolicy
                ?? $this->nullableString($policyRows[0]['policy'] ?? null);
            $blockingReasons = is_array($readiness['blocking_reasons'] ?? null)
                ? array_values($readiness['blocking_reasons'])
                : (is_array($gate['blocking_reasons'] ?? null) ? array_values($gate['blocking_reasons']) : []);
            $landscapeState = $this->nullableString($execution->latestMetric?->landscape_state ?? ($observation['state'] ?? null));
            $landscapePhenomenon = $this->nullableString($execution->latestMetric?->landscape_phenomenon ?? ($observation['phenomenon'] ?? null));
            $episodeTrend = is_array($observation['episode_trend'] ?? null)
                ? $observation['episode_trend']
                : [];
            $episodeTrendDirection = $this->nullableString($episodeTrend['direction'] ?? null);
            $realActivation = is_array($observation['alns_trigger']['real_activation'] ?? null)
                ? $observation['alns_trigger']['real_activation']
                : [];
            $realActivationApplied = (bool) ($realActivation['applied'] ?? false);

            if ($episodeTrendDirection === 'worsening') {
                $worseningOccurrences++;
            }

            if ($episodeTrendDirection === 'improving') {
                $improvingOccurrences++;
            }

            foreach ($policyRows as $policyRow) {
                if (! is_array($policyRow) || ! is_string($policyRow['policy'] ?? null) || $policyRow['policy'] === '') {
                    continue;
                }

                $policy = $policyRow['policy'];
                $bucket = $policyBuckets[$policy] ?? $this->emptyBucket($policy);

                $bucket['executions_seen']++;
                $bucket['resolved_outcomes_total'] += (int) ($policyRow['resolved_outcomes'] ?? 0);
                $bucket['avg_success_rate_sum'] += (float) ($policyRow['success_rate'] ?? 0.0);
                $bucket['avg_success_rate_count']++;
                $bucket['avg_progress_score_sum'] += (float) ($policyRow['avg_progress_score'] ?? 0.0);
                $bucket['avg_progress_score_count']++;

                if ($bestBySuccessPolicy === $policy) {
                    $bucket['best_by_success_count']++;
                }

                if ($bestByProgressPolicy === $policy) {
                    $bucket['best_by_progress_count']++;
                }

                if ($nearGatePolicy === $policy) {
                    $bucket['near_gate_occurrences']++;
                    $bucket['near_gate_resolved_evidence_sum'] += $resolvedEvidenceCount;
                    $bucket['near_gate_resolved_evidence_count']++;
                    $bucket['sample_execution_ids'][] = $execution->id;

                    if ($candidateEligible && $candidatePolicy === $policy) {
                        $bucket['candidate_ready_occurrences']++;
                    } else {
                        $bucket['blocked_near_gate_occurrences']++;
                    }

                    if ($landscapeState !== null) {
                        $bucket['landscape_state_counts'][$landscapeState] = ($bucket['landscape_state_counts'][$landscapeState] ?? 0) + 1;
                    }

                    if ($landscapePhenomenon !== null) {
                        $bucket['landscape_phenomenon_counts'][$landscapePhenomenon] = ($bucket['landscape_phenomenon_counts'][$landscapePhenomenon] ?? 0) + 1;
                    }

                    if ($episodeTrendDirection !== null) {
                        $bucket['trend_direction_counts'][$episodeTrendDirection] = ($bucket['trend_direction_counts'][$episodeTrendDirection] ?? 0) + 1;
                    }

                    if ($episodeTrendDirection === 'worsening') {
                        $bucket['worsening_occurrences']++;
                    }

                    if ($episodeTrendDirection === 'improving') {
                        $bucket['improving_occurrences']++;
                    }

                    if ($realActivationApplied) {
                        $bucket['real_activation_occurrences']++;
                    }

                    if (
                        $episodeTrendDirection !== null &&
                        isset($activationTrendComparison[$episodeTrendDirection])
                    ) {
                        $activationTrendComparison[$episodeTrendDirection]['occurrences']++;

                        if ($realActivationApplied) {
                            $activationTrendComparison[$episodeTrendDirection]['real_activation_occurrences']++;
                        }

                        $activationTrendComparison[$episodeTrendDirection]['avg_success_rate_sum'] += (float) ($policyRow['success_rate'] ?? 0.0);
                        $activationTrendComparison[$episodeTrendDirection]['avg_success_rate_count']++;
                        $activationTrendComparison[$episodeTrendDirection]['avg_progress_score_sum'] += (float) ($policyRow['avg_progress_score'] ?? 0.0);
                        $activationTrendComparison[$episodeTrendDirection]['avg_progress_score_count']++;
                    }

                    foreach ($blockingReasons as $blockingReason) {
                        if (! is_string($blockingReason) || trim($blockingReason) === '') {
                            continue;
                        }

                        $bucket['blocking_reason_counts'][$blockingReason] = ($bucket['blocking_reason_counts'][$blockingReason] ?? 0) + 1;
                    }

                    $this->accumulateFloat($bucket, 'basin_lock_confidence_sum', 'basin_lock_confidence_count', $observation['basin_of_attraction_lock_confidence'] ?? null);
                    $this->accumulateFloat($bucket, 'depth_score_sum', 'depth_score_count', $observation['depth_score'] ?? null);
                    $this->accumulateFloat($bucket, 'population_turnover_sum', 'population_turnover_count', $observation['population_turnover'] ?? null);
                    $this->accumulateFloat($bucket, 'elite_similarity_sum', 'elite_similarity_count', $observation['elite_similarity'] ?? null);
                    $this->accumulateFloat($bucket, 'best_delta_window_sum', 'best_delta_window_count', $observation['best_delta_window'] ?? null);
                }

                $policyBuckets[$policy] = $bucket;
            }
        }

        $policyRows = $this->finalizePolicyRows($policyBuckets);

        return new SearchResponseGlobalPolicyReadinessReport(
            status: $this->resolveStatus($windowExecutionsCount, $policyRows),
            headline: $this->resolveHeadline($windowExecutionsCount, $policyRows),
            windowExecutionsCount: $windowExecutionsCount,
            policiesCount: count($policyRows),
            worseningOccurrences: $worseningOccurrences,
            improvingOccurrences: $improvingOccurrences,
            activationTrendComparison: $this->finalizeActivationTrendComparison($activationTrendComparison),
            leadingPolicyByNearGate: $policyRows[0] ?? null,
            leadingPolicyByCandidateReady: $this->leadingPolicyByCandidateReady($policyRows),
            policyRows: $policyRows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBucket(string $policy): array
    {
        return [
            'policy' => $policy,
            'executions_seen' => 0,
            'near_gate_occurrences' => 0,
            'candidate_ready_occurrences' => 0,
            'blocked_near_gate_occurrences' => 0,
            'best_by_success_count' => 0,
            'best_by_progress_count' => 0,
            'resolved_outcomes_total' => 0,
            'worsening_occurrences' => 0,
            'improving_occurrences' => 0,
            'real_activation_occurrences' => 0,
            'avg_success_rate_sum' => 0.0,
            'avg_success_rate_count' => 0,
            'avg_progress_score_sum' => 0.0,
            'avg_progress_score_count' => 0,
            'near_gate_resolved_evidence_sum' => 0,
            'near_gate_resolved_evidence_count' => 0,
            'landscape_state_counts' => [],
            'landscape_phenomenon_counts' => [],
            'trend_direction_counts' => [],
            'blocking_reason_counts' => [],
            'basin_lock_confidence_sum' => 0.0,
            'basin_lock_confidence_count' => 0,
            'depth_score_sum' => 0.0,
            'depth_score_count' => 0,
            'population_turnover_sum' => 0.0,
            'population_turnover_count' => 0,
            'elite_similarity_sum' => 0.0,
            'elite_similarity_count' => 0,
            'best_delta_window_sum' => 0.0,
            'best_delta_window_count' => 0,
            'sample_execution_ids' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $bucket
     */
    private function accumulateFloat(array &$bucket, string $sumKey, string $countKey, mixed $value): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $bucket[$sumKey] += (float) $value;
        $bucket[$countKey]++;
    }

    /**
     * @param  array<string, array<string, mixed>>  $policyBuckets
     * @return array<int, array<string, mixed>>
     */
    private function finalizePolicyRows(array $policyBuckets): array
    {
        $rows = array_map(function (array $bucket): array {
            return [
                'policy' => $bucket['policy'],
                'executions_seen' => (int) $bucket['executions_seen'],
                'near_gate_occurrences' => (int) $bucket['near_gate_occurrences'],
                'candidate_ready_occurrences' => (int) $bucket['candidate_ready_occurrences'],
                'blocked_near_gate_occurrences' => (int) $bucket['blocked_near_gate_occurrences'],
                'best_by_success_count' => (int) $bucket['best_by_success_count'],
                'best_by_progress_count' => (int) $bucket['best_by_progress_count'],
                'resolved_outcomes_total' => (int) $bucket['resolved_outcomes_total'],
                'worsening_occurrences' => (int) $bucket['worsening_occurrences'],
                'improving_occurrences' => (int) $bucket['improving_occurrences'],
                'real_activation_occurrences' => (int) $bucket['real_activation_occurrences'],
                'avg_success_rate' => $this->safeAverage($bucket['avg_success_rate_sum'], $bucket['avg_success_rate_count']),
                'avg_progress_score' => $this->safeAverage($bucket['avg_progress_score_sum'], $bucket['avg_progress_score_count']),
                'avg_near_gate_resolved_evidence' => $this->safeAverage($bucket['near_gate_resolved_evidence_sum'], $bucket['near_gate_resolved_evidence_count']),
                'top_landscape_state' => $this->topKey($bucket['landscape_state_counts']),
                'top_landscape_phenomenon' => $this->topKey($bucket['landscape_phenomenon_counts']),
                'top_trend_direction' => $this->topKey($bucket['trend_direction_counts']),
                'top_blocking_reason' => $this->topKey($bucket['blocking_reason_counts']),
                'avg_basin_lock_confidence' => $this->safeAverage($bucket['basin_lock_confidence_sum'], $bucket['basin_lock_confidence_count']),
                'avg_depth_score' => $this->safeAverage($bucket['depth_score_sum'], $bucket['depth_score_count']),
                'avg_population_turnover' => $this->safeAverage($bucket['population_turnover_sum'], $bucket['population_turnover_count']),
                'avg_elite_similarity' => $this->safeAverage($bucket['elite_similarity_sum'], $bucket['elite_similarity_count']),
                'avg_best_delta_window' => $this->safeAverage($bucket['best_delta_window_sum'], $bucket['best_delta_window_count']),
                'sample_execution_ids' => array_slice(array_values(array_unique($bucket['sample_execution_ids'])), 0, 5),
            ];
        }, array_values($policyBuckets));

        usort($rows, static function (array $left, array $right): int {
            return [
                $right['near_gate_occurrences'],
                $right['candidate_ready_occurrences'],
                $right['avg_success_rate'],
                $right['avg_progress_score'],
            ] <=> [
                $left['near_gate_occurrences'],
                $left['candidate_ready_occurrences'],
                $left['avg_success_rate'],
                $left['avg_progress_score'],
            ];
        });

        return $rows;
    }

    /**
     * @param  array<string, array<string, float|int>>  $comparison
     * @return array<string, mixed>
     */
    private function finalizeActivationTrendComparison(array $comparison): array
    {
        $worsening = $comparison['worsening'];
        $improving = $comparison['improving'];

        $worseningPayload = [
            'occurrences' => (int) $worsening['occurrences'],
            'real_activation_occurrences' => (int) $worsening['real_activation_occurrences'],
            'avg_success_rate' => $this->safeAverage($worsening['avg_success_rate_sum'], (int) $worsening['avg_success_rate_count']),
            'avg_progress_score' => $this->safeAverage($worsening['avg_progress_score_sum'], (int) $worsening['avg_progress_score_count']),
        ];
        $improvingPayload = [
            'occurrences' => (int) $improving['occurrences'],
            'real_activation_occurrences' => (int) $improving['real_activation_occurrences'],
            'avg_success_rate' => $this->safeAverage($improving['avg_success_rate_sum'], (int) $improving['avg_success_rate_count']),
            'avg_progress_score' => $this->safeAverage($improving['avg_progress_score_sum'], (int) $improving['avg_progress_score_count']),
        ];

        $betterTrend = null;

        if ($improvingPayload['avg_success_rate'] > $worseningPayload['avg_success_rate']) {
            $betterTrend = 'improving';
        } elseif ($improvingPayload['avg_success_rate'] < $worseningPayload['avg_success_rate']) {
            $betterTrend = 'worsening';
        } elseif ($improvingPayload['avg_progress_score'] > $worseningPayload['avg_progress_score']) {
            $betterTrend = 'improving';
        } elseif ($improvingPayload['avg_progress_score'] < $worseningPayload['avg_progress_score']) {
            $betterTrend = 'worsening';
        }

        return [
            'worsening' => $worseningPayload,
            'improving' => $improvingPayload,
            'better_trend' => $betterTrend,
        ];
    }

    private function safeAverage(float|int $sum, int $count): float
    {
        return $count > 0 ? round(((float) $sum) / $count, 4) : 0.0;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function topKey(array $counts): ?string
    {
        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $policyRows
     * @return array<string, mixed>|null
     */
    private function leadingPolicyByCandidateReady(array $policyRows): ?array
    {
        if ($policyRows === []) {
            return null;
        }

        $sorted = $policyRows;
        usort($sorted, static function (array $left, array $right): int {
            return [
                $right['candidate_ready_occurrences'],
                $right['near_gate_occurrences'],
                $right['avg_success_rate'],
            ] <=> [
                $left['candidate_ready_occurrences'],
                $left['near_gate_occurrences'],
                $left['avg_success_rate'],
            ];
        });

        return $sorted[0] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $policyRows
     */
    private function resolveStatus(int $windowExecutionsCount, array $policyRows): string
    {
        if ($policyRows === []) {
            return $windowExecutionsCount > 0 ? 'no_policy_signal' : 'idle';
        }

        if (($policyRows[0]['candidate_ready_occurrences'] ?? 0) > 0) {
            return 'candidate_ready';
        }

        if (($policyRows[0]['near_gate_occurrences'] ?? 0) > 0) {
            return 'near_gate_signal';
        }

        return 'observing';
    }

    /**
     * @param  array<int, array<string, mixed>>  $policyRows
     */
    private function resolveHeadline(int $windowExecutionsCount, array $policyRows): string
    {
        if ($policyRows === []) {
            return $windowExecutionsCount > 0
                ? 'No policy-level readiness signal was detected in the latest execution window.'
                : 'No executions available for global policy readiness analysis.';
        }

        $leader = $policyRows[0];

        return sprintf(
            '%s is the most recurrent near-gate policy across the last %d execution(s).',
            (string) $leader['policy'],
            $windowExecutionsCount
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
