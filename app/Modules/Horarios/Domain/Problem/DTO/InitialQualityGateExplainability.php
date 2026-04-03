<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Problem\DTO;

final class InitialQualityGateExplainability
{
    /**
     * @param array<int, string> $rejectionReasons
     * @param array<string, bool|float|int|string|null> $supportingSignals
     */
    public function __construct(
        private readonly string $decision,
        private readonly ?string $dominantReason,
        private readonly array $rejectionReasons,
        private readonly array $supportingSignals,
    ) {
    }

    public static function fromThresholdComparison(
        bool $passes,
        float $hardPenalty,
        float $maxHardPenalty,
        int $hardConflictAllocations,
        int $maxHardConflictAllocations,
        bool $scoreBelowViableThreshold,
    ): self {
        $rejectionReasons = [];

        if ($hardPenalty > $maxHardPenalty) {
            $rejectionReasons[] = 'hard_penalty_above_limit';
        }

        if ($hardConflictAllocations > $maxHardConflictAllocations) {
            $rejectionReasons[] = 'hard_conflicts_above_limit';
        }

        $hardPenaltyExcessRatio = round($hardPenalty / max(0.001, $maxHardPenalty), 4);
        $hardConflictsExcessRatio = round($hardConflictAllocations / max(1, $maxHardConflictAllocations), 4);

        $dominantReason = null;

        if (! $passes) {
            if ($hardPenaltyExcessRatio >= $hardConflictsExcessRatio && $hardPenaltyExcessRatio > 1.0) {
                $dominantReason = 'hard_penalty_above_limit';
            } elseif ($hardConflictsExcessRatio > 1.0) {
                $dominantReason = 'hard_conflicts_above_limit';
            } else {
                $dominantReason = 'gate_rejection_unknown';
            }
        }

        return new self(
            decision: $passes ? 'accepted' : 'rejected',
            dominantReason: $dominantReason,
            rejectionReasons: $rejectionReasons,
            supportingSignals: [
                'score_below_viable_threshold' => $scoreBelowViableThreshold,
                'hard_penalty_excess_ratio' => $hardPenaltyExcessRatio,
                'hard_conflicts_excess_ratio' => $hardConflictsExcessRatio,
            ],
        );
    }

    public function decision(): string
    {
        return $this->decision;
    }

    public function dominantReason(): ?string
    {
        return $this->dominantReason;
    }

    /**
     * @return array<int, string>
     */
    public function rejectionReasons(): array
    {
        return $this->rejectionReasons;
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function supportingSignals(): array
    {
        return $this->supportingSignals;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision,
            'dominant_reason' => $this->dominantReason,
            'rejection_reasons' => $this->rejectionReasons,
            'supporting_signals' => $this->supportingSignals,
        ];
    }
}
