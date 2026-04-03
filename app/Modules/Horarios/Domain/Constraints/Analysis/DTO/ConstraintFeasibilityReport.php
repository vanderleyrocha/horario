<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Analysis\DTO;

final class ConstraintFeasibilityReport
{
    /**
     * @param  list<array<string, mixed>>  $blockingIssues
     * @param  list<array<string, mixed>>  $warnings
     */
    public function __construct(
        private readonly array $blockingIssues = [],
        private readonly array $warnings = [],
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function blockingIssues(): array
    {
        return $this->blockingIssues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasBlockingIssues(): bool
    {
        return $this->blockingIssues !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function riskContribution(): int
    {
        return min(100, (count($this->blockingIssues) * 25) + (count($this->warnings) * 8));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupedByConstraint(): array
    {
        $grouped = [];

        foreach ([
            'blocking' => $this->blockingIssues,
            'warning' => $this->warnings,
        ] as $severity => $issues) {
            foreach ($issues as $issue) {
                $constraintId = (int) ($issue['constraint_id'] ?? 0);

                if (! isset($grouped[$constraintId])) {
                    $grouped[$constraintId] = [
                        'constraint_id' => $constraintId,
                        'constraint_name' => $issue['constraint_name'] ?? null,
                        'constraint_type' => $issue['constraint_type'] ?? null,
                        'blocking_issues' => [],
                        'warnings' => [],
                    ];
                }

                $bucket = $severity === 'blocking' ? 'blocking_issues' : 'warnings';
                $grouped[$constraintId][$bucket][] = $issue;
            }
        }

        ksort($grouped);

        return $grouped;
    }

    public function toArray(): array
    {
        return [
            'has_blocking_issues' => $this->hasBlockingIssues(),
            'has_warnings' => $this->hasWarnings(),
            'risk_contribution' => $this->riskContribution(),
            'blocking_issues' => $this->blockingIssues,
            'warnings' => $this->warnings,
            'grouped_by_constraint' => $this->groupedByConstraint(),
        ];
    }
}
