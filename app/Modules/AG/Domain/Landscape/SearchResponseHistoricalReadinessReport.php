<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseHistoricalReadinessReport
{
    /**
     * @param  array<string, mixed>|null  $bestExecutionBySuccess
     * @param  array<string, mixed>|null  $bestExecutionByProgress
     * @param  array<int, array<string, mixed>>  $policyRows
     * @param  array<int, array<string, mixed>>  $executions
     */
    public function __construct(
        public readonly string $status,
        public readonly string $headline,
        public readonly int $comparedExecutionsCount,
        public readonly int $executionsWithReadiness,
        public readonly int $candidateReadyExecutions,
        public readonly int $worseningExecutions,
        public readonly int $improvingExecutions,
        public readonly int $realActivationWhileWorsening,
        public readonly int $realActivationWhileImproving,
        public readonly ?array $bestExecutionBySuccess,
        public readonly ?array $bestExecutionByProgress,
        public readonly array $policyRows,
        public readonly array $executions,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headline' => $this->headline,
            'compared_executions_count' => $this->comparedExecutionsCount,
            'executions_with_readiness' => $this->executionsWithReadiness,
            'candidate_ready_executions' => $this->candidateReadyExecutions,
            'worsening_executions' => $this->worseningExecutions,
            'improving_executions' => $this->improvingExecutions,
            'real_activation_while_worsening' => $this->realActivationWhileWorsening,
            'real_activation_while_improving' => $this->realActivationWhileImproving,
            'best_execution_by_success' => $this->bestExecutionBySuccess,
            'best_execution_by_progress' => $this->bestExecutionByProgress,
            'policy_rows' => $this->policyRows,
            'executions' => $this->executions,
        ];
    }
}
