<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseReadinessDashboard
{
    /**
     * @param  array<int, array<string, mixed>>  $policyRows
     * @param  string[]  $blockingReasons
     */
    public function __construct(
        public readonly string $status,
        public readonly string $headline,
        public readonly int $resolvedEvidenceCount,
        public readonly int $pendingAudits,
        public readonly ?array $bestPolicyBySuccess,
        public readonly ?array $bestPolicyByProgress,
        public readonly ?array $latestOutcome,
        public readonly ?array $activationGate,
        public readonly array $blockingReasons,
        public readonly array $policyRows
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headline' => $this->headline,
            'resolved_evidence_count' => $this->resolvedEvidenceCount,
            'pending_audits' => $this->pendingAudits,
            'best_policy_by_success' => $this->bestPolicyBySuccess,
            'best_policy_by_progress' => $this->bestPolicyByProgress,
            'latest_outcome' => $this->latestOutcome,
            'activation_gate' => $this->activationGate,
            'blocking_reasons' => $this->blockingReasons,
            'policy_rows' => $this->policyRows,
        ];
    }
}
