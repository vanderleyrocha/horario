<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

use App\Models\ScheduleExecution;
use Illuminate\Support\Carbon;

final class SearchResponseHistoricalReadinessReportBuilder
{
    /**
     * @param  iterable<int, ScheduleExecution>  $executions
     */
    public function build(iterable $executions): SearchResponseHistoricalReadinessReport
    {
        $executionRows = [];
        $policyAggregates = [];

        foreach ($executions as $execution) {
            $executionRow = $this->buildExecutionRow($execution);
            $executionRows[] = $executionRow;
            $this->mergePolicyEvidence($policyAggregates, $executionRow);
        }

        $comparedExecutionsCount = count($executionRows);
        $executionsWithReadiness = count(array_filter(
            $executionRows,
            static fn (array $row): bool => (bool) ($row['has_readiness_signal'] ?? false)
        ));
        $candidateReadyExecutions = count(array_filter(
            $executionRows,
            static fn (array $row): bool => (bool) ($row['candidate_eligible'] ?? false)
        ));

        $policyRows = $this->finalizePolicyRows($policyAggregates);

        return new SearchResponseHistoricalReadinessReport(
            status: $this->resolveStatus($comparedExecutionsCount, $candidateReadyExecutions, $executionsWithReadiness),
            headline: $this->resolveHeadline($comparedExecutionsCount, $candidateReadyExecutions, $executionsWithReadiness),
            comparedExecutionsCount: $comparedExecutionsCount,
            executionsWithReadiness: $executionsWithReadiness,
            candidateReadyExecutions: $candidateReadyExecutions,
            bestExecutionBySuccess: $this->bestExecutionBySuccess($executionRows),
            bestExecutionByProgress: $this->bestExecutionByProgress($executionRows),
            policyRows: $policyRows,
            executions: $executionRows,
        );
    }

    private function buildExecutionRow(ScheduleExecution $execution): array
    {
        $observation = is_array($execution->latestMetric?->landscape_observation)
            ? $execution->latestMetric->landscape_observation
            : [];

        $readiness = is_array($observation['search_response_readiness_dashboard'] ?? null)
            ? $observation['search_response_readiness_dashboard']
            : [];
        $gate = is_array($readiness['activation_gate'] ?? null)
            ? $readiness['activation_gate']
            : (is_array($observation['search_response_activation_gate'] ?? null) ? $observation['search_response_activation_gate'] : []);
        $latestOutcome = is_array($readiness['latest_outcome'] ?? null)
            ? $readiness['latest_outcome']
            : (is_array($observation['search_response_outcome'] ?? null) ? $observation['search_response_outcome'] : []);
        $bestBySuccess = is_array($readiness['best_policy_by_success'] ?? null) ? $readiness['best_policy_by_success'] : null;
        $bestByProgress = is_array($readiness['best_policy_by_progress'] ?? null) ? $readiness['best_policy_by_progress'] : null;
        $policyRows = is_array($readiness['policy_rows'] ?? null) ? array_values($readiness['policy_rows']) : [];
        $blockingReasons = is_array($readiness['blocking_reasons'] ?? null)
            ? array_values($readiness['blocking_reasons'])
            : (is_array($gate['blocking_reasons'] ?? null) ? array_values($gate['blocking_reasons']) : []);

        $resolvedEvidenceCount = (int) ($readiness['resolved_evidence_count'] ?? 0);
        $pendingAudits = (int) ($readiness['pending_audits'] ?? ($observation['search_response_pending_audits'] ?? 0));
        $candidateEligible = (bool) ($gate['eligible_as_candidate'] ?? false);
        $candidatePolicy = $this->nullableString($gate['candidate_policy'] ?? null);
        $readinessStatus = $this->nullableString($readiness['status'] ?? null)
            ?? ($candidateEligible ? 'candidate_ready' : ($resolvedEvidenceCount > 0 || $pendingAudits > 0 ? 'collecting_evidence' : 'idle'));
        $readinessHeadline = $this->nullableString($readiness['headline'] ?? null)
            ?? ($candidateEligible ? 'Candidate ready based on historical shadow evidence' : 'No readiness evidence collected yet');

        return [
            'execution_id' => $execution->id,
            'execution_status' => (string) $execution->status,
            'start_time' => $this->formatDateTime($execution->start_time),
            'end_time' => $this->formatDateTime($execution->end_time),
            'generations' => $execution->generations,
            'best_fitness' => $execution->best_fitness,
            'latest_generation' => $execution->latestMetric?->generation,
            'readiness_status' => $readinessStatus,
            'readiness_headline' => $readinessHeadline,
            'resolved_evidence_count' => $resolvedEvidenceCount,
            'pending_audits' => $pendingAudits,
            'candidate_policy' => $candidatePolicy,
            'candidate_eligible' => $candidateEligible,
            'best_policy_by_success' => $bestBySuccess,
            'best_policy_by_progress' => $bestByProgress,
            'latest_outcome' => $latestOutcome !== [] ? $latestOutcome : null,
            'blocking_reasons' => $blockingReasons,
            'policy_rows' => $policyRows,
            'has_readiness_signal' => $candidateEligible || $resolvedEvidenceCount > 0 || $pendingAudits > 0 || $policyRows !== [] || $latestOutcome !== [],
        ];
    }

    /**
     * @param  array<string, array<string, float|int|string|null>>  $policyAggregates
     * @param  array<string, mixed>  $executionRow
     */
    private function mergePolicyEvidence(array &$policyAggregates, array $executionRow): void
    {
        $bestPolicyBySuccess = is_array($executionRow['best_policy_by_success'] ?? null)
            ? $executionRow['best_policy_by_success']
            : null;
        $bestPolicyByProgress = is_array($executionRow['best_policy_by_progress'] ?? null)
            ? $executionRow['best_policy_by_progress']
            : null;

        foreach (($executionRow['policy_rows'] ?? []) as $policyRow) {
            if (! is_array($policyRow) || ! is_string($policyRow['policy'] ?? null) || $policyRow['policy'] === '') {
                continue;
            }

            $policy = $policyRow['policy'];
            $bucket = $policyAggregates[$policy] ?? [
                'policy' => $policy,
                'executions_seen' => 0,
                'candidate_ready_executions' => 0,
                'best_by_success_count' => 0,
                'best_by_progress_count' => 0,
                'resolved_outcomes_total' => 0,
                'success_rate_sum' => 0.0,
                'success_rate_count' => 0,
                'avg_progress_sum' => 0.0,
                'avg_progress_count' => 0,
            ];

            $bucket['executions_seen']++;
            $bucket['resolved_outcomes_total'] += (int) ($policyRow['resolved_outcomes'] ?? 0);

            if (isset($policyRow['success_rate'])) {
                $bucket['success_rate_sum'] += (float) $policyRow['success_rate'];
                $bucket['success_rate_count']++;
            }

            if (isset($policyRow['avg_progress_score'])) {
                $bucket['avg_progress_sum'] += (float) $policyRow['avg_progress_score'];
                $bucket['avg_progress_count']++;
            }

            if (($executionRow['candidate_eligible'] ?? false) === true && ($executionRow['candidate_policy'] ?? null) === $policy) {
                $bucket['candidate_ready_executions']++;
            }

            if (($bestPolicyBySuccess['policy'] ?? null) === $policy) {
                $bucket['best_by_success_count']++;
            }

            if (($bestPolicyByProgress['policy'] ?? null) === $policy) {
                $bucket['best_by_progress_count']++;
            }

            $policyAggregates[$policy] = $bucket;
        }
    }

    /**
     * @param  array<string, array<string, float|int|string|null>>  $policyAggregates
     * @return array<int, array<string, mixed>>
     */
    private function finalizePolicyRows(array $policyAggregates): array
    {
        $rows = array_map(
            static function (array $bucket): array {
                $avgSuccessRate = ($bucket['success_rate_count'] ?? 0) > 0
                    ? ((float) $bucket['success_rate_sum'] / (int) $bucket['success_rate_count'])
                    : 0.0;
                $avgProgressScore = ($bucket['avg_progress_count'] ?? 0) > 0
                    ? ((float) $bucket['avg_progress_sum'] / (int) $bucket['avg_progress_count'])
                    : 0.0;

                return [
                    'policy' => $bucket['policy'],
                    'executions_seen' => (int) $bucket['executions_seen'],
                    'candidate_ready_executions' => (int) $bucket['candidate_ready_executions'],
                    'best_by_success_count' => (int) $bucket['best_by_success_count'],
                    'best_by_progress_count' => (int) $bucket['best_by_progress_count'],
                    'resolved_outcomes_total' => (int) $bucket['resolved_outcomes_total'],
                    'avg_success_rate' => round($avgSuccessRate, 4),
                    'avg_progress_score' => round($avgProgressScore, 4),
                ];
            },
            array_values($policyAggregates)
        );

        usort(
            $rows,
            static fn (array $left, array $right): int => [$right['candidate_ready_executions'], $right['avg_success_rate'], $right['avg_progress_score'], $right['resolved_outcomes_total']]
                <=> [$left['candidate_ready_executions'], $left['avg_success_rate'], $left['avg_progress_score'], $left['resolved_outcomes_total']]
        );

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $executionRows
     * @return array<string, mixed>|null
     */
    private function bestExecutionBySuccess(array $executionRows): ?array
    {
        $best = null;
        $bestScore = null;

        foreach ($executionRows as $row) {
            $score = is_array($row['best_policy_by_success'] ?? null)
                ? (float) ($row['best_policy_by_success']['success_rate'] ?? 0.0)
                : null;

            if ($score === null || ($bestScore !== null && $score <= $bestScore)) {
                continue;
            }

            $bestScore = $score;
            $best = [
                'execution_id' => $row['execution_id'],
                'policy' => $row['best_policy_by_success']['policy'] ?? null,
                'success_rate' => round($score, 4),
                'readiness_status' => $row['readiness_status'],
            ];
        }

        return $best;
    }

    /**
     * @param  array<int, array<string, mixed>>  $executionRows
     * @return array<string, mixed>|null
     */
    private function bestExecutionByProgress(array $executionRows): ?array
    {
        $best = null;
        $bestScore = null;

        foreach ($executionRows as $row) {
            $score = is_array($row['best_policy_by_progress'] ?? null)
                ? (float) ($row['best_policy_by_progress']['avg_progress_score'] ?? 0.0)
                : null;

            if ($score === null || ($bestScore !== null && $score <= $bestScore)) {
                continue;
            }

            $bestScore = $score;
            $best = [
                'execution_id' => $row['execution_id'],
                'policy' => $row['best_policy_by_progress']['policy'] ?? null,
                'avg_progress_score' => round($score, 4),
                'readiness_status' => $row['readiness_status'],
            ];
        }

        return $best;
    }

    private function resolveStatus(int $comparedExecutionsCount, int $candidateReadyExecutions, int $executionsWithReadiness): string
    {
        if ($candidateReadyExecutions > 0) {
            return 'candidate_ready';
        }

        if ($executionsWithReadiness > 0) {
            return 'collecting_evidence';
        }

        return $comparedExecutionsCount > 0 ? 'no_readiness_signal' : 'idle';
    }

    private function resolveHeadline(int $comparedExecutionsCount, int $candidateReadyExecutions, int $executionsWithReadiness): string
    {
        if ($candidateReadyExecutions > 0) {
            return "{$candidateReadyExecutions} execution(s) already indicate a real-activation candidate.";
        }

        if ($executionsWithReadiness > 0) {
            return "Comparing readiness evidence across {$executionsWithReadiness} execution(s).";
        }

        if ($comparedExecutionsCount > 0) {
            return 'Executions exist, but no historical readiness signal has been recorded yet.';
        }

        return 'No executions available for historical readiness comparison.';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->format('d/m/Y H:i');
        }

        return null;
    }
}
