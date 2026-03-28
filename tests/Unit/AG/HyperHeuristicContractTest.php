<?php

declare(strict_types=1);

use App\Modules\AG\Domain\HyperHeuristic\OperatorPerformanceTracker;
use App\Modules\AG\Domain\HyperHeuristic\Strategies\EpsilonGreedySelector;
use App\Modules\AG\Domain\HyperHeuristic\Strategies\SoftmaxSelector;
use App\Modules\AG\Domain\HyperHeuristic\Strategies\UCB1Selector;

it('publishes a unified operator statistics contract', function (): void {
    $tracker = new OperatorPerformanceTracker;
    $tracker->registerOperator('StructuredSwap');
    $tracker->registerUse('StructuredSwap');
    $tracker->registerUse('StructuredSwap');
    $tracker->recordReward('StructuredSwap', 6.0);

    $stats = $tracker->getOperatorStatistics();

    expect($stats['StructuredSwap'])->toMatchArray([
        'uses' => 2,
        'reward' => 6.0,
        'average' => 3.0,
        'average_reward' => 3.0,
        'mean_reward' => 3.0,
    ])->and($stats['StructuredSwap']['score'])->toBeFloat();
});

it('keeps epsilon greedy compatible with the unified tracker statistics', function (): void {
    $selector = new EpsilonGreedySelector(epsilon: 0.0);

    $selected = $selector->select([
        'StructuredSwap' => [
            'score' => 3.2,
            'uses' => 4,
            'reward' => 8.0,
            'mean_reward' => 2.0,
        ],
        'GeneSwap' => [
            'score' => 1.1,
            'uses' => 4,
            'reward' => 3.0,
            'mean_reward' => 0.75,
        ],
    ]);

    expect($selected)->toBe('StructuredSwap');
});

it('allows softmax to consume the unified tracker statistics', function (): void {
    $selector = new SoftmaxSelector(temperature: 0.15);

    $selected = $selector->select([
        'StructuredSwap' => [
            'score' => 4.0,
            'uses' => 3,
            'reward' => 9.0,
            'mean_reward' => 3.0,
        ],
    ]);

    expect($selected)->toBe('StructuredSwap');
});

it('allows ucb1 to consume the unified tracker statistics and prefer the best operator', function (): void {
    $selector = new UCB1Selector(exploration: 2.0);

    $selected = $selector->select([
        'StructuredSwap' => [
            'score' => 3.0,
            'uses' => 4,
            'reward' => 12.0,
            'mean_reward' => 3.0,
        ],
        'GeneSwap' => [
            'score' => 1.5,
            'uses' => 4,
            'reward' => 4.0,
            'mean_reward' => 1.0,
        ],
    ]);

    expect($selected)->toBe('StructuredSwap');
});
