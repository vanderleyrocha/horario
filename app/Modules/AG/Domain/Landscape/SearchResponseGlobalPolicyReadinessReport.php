<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseGlobalPolicyReadinessReport
{
    /**
     * @param  array<string, mixed>|null  $leadingPolicyByNearGate
     * @param  array<string, mixed>|null  $leadingPolicyByCandidateReady
     * @param  array<int, array<string, mixed>>  $policyRows
     */
    public function __construct(
        public readonly string $status,
        public readonly string $headline,
        public readonly int $windowExecutionsCount,
        public readonly int $policiesCount,
        public readonly ?array $leadingPolicyByNearGate,
        public readonly ?array $leadingPolicyByCandidateReady,
        public readonly array $policyRows,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headline' => $this->headline,
            'window_executions_count' => $this->windowExecutionsCount,
            'policies_count' => $this->policiesCount,
            'leading_policy_by_near_gate' => $this->leadingPolicyByNearGate,
            'leading_policy_by_candidate_ready' => $this->leadingPolicyByCandidateReady,
            'policy_rows' => $this->policyRows,
        ];
    }
}
