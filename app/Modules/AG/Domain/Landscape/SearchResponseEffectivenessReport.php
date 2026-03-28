<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseEffectivenessReport
{
    /**
     * @param  array<int, array<string, mixed>>  $policies
     */
    public function __construct(
        public readonly int $totalResolvedOutcomes,
        public readonly ?string $bestPolicyBySuccess,
        public readonly ?string $bestPolicyByProgress,
        public readonly array $policies
    ) {}

    public function toArray(): array
    {
        return [
            'total_resolved_outcomes' => $this->totalResolvedOutcomes,
            'best_policy_by_success' => $this->bestPolicyBySuccess,
            'best_policy_by_progress' => $this->bestPolicyByProgress,
            'policies' => $this->policies,
        ];
    }
}
