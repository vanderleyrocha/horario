<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Landscape;

final class SearchResponseActivationImpactReport
{
    /**
     * @param  array<string, mixed>  $trendComparison
     * @param  array<int, array<string, mixed>>  $events
     */
    public function __construct(
        public readonly string $status,
        public readonly string $headline,
        public readonly int $windowSize,
        public readonly int $analyzedActivationEvents,
        public readonly int $ignoredEdgeActivationEvents,
        public readonly int $positiveImpactEvents,
        public readonly int $mixedImpactEvents,
        public readonly int $negativeImpactEvents,
        public readonly ?string $betterTrendContext,
        public readonly array $trendComparison,
        public readonly array $events,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headline' => $this->headline,
            'window_size' => $this->windowSize,
            'analyzed_activation_events' => $this->analyzedActivationEvents,
            'ignored_edge_activation_events' => $this->ignoredEdgeActivationEvents,
            'positive_impact_events' => $this->positiveImpactEvents,
            'mixed_impact_events' => $this->mixedImpactEvents,
            'negative_impact_events' => $this->negativeImpactEvents,
            'better_trend_context' => $this->betterTrendContext,
            'trend_comparison' => $this->trendComparison,
            'events' => $this->events,
        ];
    }
}
