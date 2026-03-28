<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseReadinessDashboardBuilder
{
    public function build(
        ?array $effectivenessReport,
        ?array $activationGate,
        ?array $latestOutcome,
        int $pendingAudits
    ): SearchResponseReadinessDashboard {
        $policies = is_array($effectivenessReport['policies'] ?? null)
            ? array_values($effectivenessReport['policies'])
            : [];

        $bestPolicyBySuccess = $this->findPolicyRow($policies, $effectivenessReport['best_policy_by_success'] ?? null);
        $bestPolicyByProgress = $this->findPolicyRow($policies, $effectivenessReport['best_policy_by_progress'] ?? null);
        $blockingReasons = is_array($activationGate['blocking_reasons'] ?? null)
            ? array_values($activationGate['blocking_reasons'])
            : [];

        [$status, $headline] = $this->resolveStatus(
            activationGate: $activationGate,
            latestOutcome: $latestOutcome,
            pendingAudits: $pendingAudits,
            resolvedEvidenceCount: (int) ($effectivenessReport['total_resolved_outcomes'] ?? 0)
        );

        return new SearchResponseReadinessDashboard(
            status: $status,
            headline: $headline,
            resolvedEvidenceCount: (int) ($effectivenessReport['total_resolved_outcomes'] ?? 0),
            pendingAudits: $pendingAudits,
            bestPolicyBySuccess: $bestPolicyBySuccess,
            bestPolicyByProgress: $bestPolicyByProgress,
            latestOutcome: $latestOutcome,
            activationGate: $activationGate,
            blockingReasons: $blockingReasons,
            policyRows: $policies
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $policies
     * @return array<string, mixed>|null
     */
    private function findPolicyRow(array $policies, mixed $policyName): ?array
    {
        if (! is_string($policyName) || $policyName === '') {
            return null;
        }

        foreach ($policies as $policy) {
            if (($policy['policy'] ?? null) === $policyName) {
                return $policy;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $activationGate
     * @param  array<string, mixed>|null  $latestOutcome
     * @return array{0: string, 1: string}
     */
    private function resolveStatus(
        ?array $activationGate,
        ?array $latestOutcome,
        int $pendingAudits,
        int $resolvedEvidenceCount
    ): array {
        if (($activationGate['eligible_as_candidate'] ?? false) === true) {
            $candidate = (string) ($activationGate['candidate_policy'] ?? 'policy');

            return ['candidate_ready', "Diagnostic candidate ready: {$candidate}"];
        }

        if (($latestOutcome['targets_satisfied'] ?? false) === true) {
            return ['evidence_positive', 'Shadow outcomes are matching activation goals'];
        }

        if ($resolvedEvidenceCount > 0 || $pendingAudits > 0) {
            return ['collecting_evidence', 'Collecting evidence before any real activation'];
        }

        return ['idle', 'No readiness evidence collected yet'];
    }
}
