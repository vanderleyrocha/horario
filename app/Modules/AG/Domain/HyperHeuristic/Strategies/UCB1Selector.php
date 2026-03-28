<?php

namespace App\Modules\AG\Domain\HyperHeuristic\Strategies;

use App\Modules\AG\Domain\HyperHeuristic\OperatorSelectionStrategy;

class UCB1Selector implements OperatorSelectionStrategy
{
    private float $exploration;

    public function __construct(float $exploration = 2.0)
    {
        $this->exploration = $exploration;
    }

    public function select(array $operators): string
    {
        if ($operators === []) {
            throw new \RuntimeException('UCB1Selector recebeu estatisticas vazias.');
        }

        $totalUses = array_sum(array_column($operators, 'uses'));
        $totalUses = max(1, (int) $totalUses);

        $bestOperator = null;
        $bestScore = -INF;

        foreach ($operators as $name => $data) {

            if ($data['uses'] === 0) {
                return $name;
            }

            $meanReward = $this->resolveMeanReward($data);
            $score = $meanReward + $this->exploration * sqrt(log($totalUses) / $data['uses']);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestOperator = $name;
            }
        }

        return $bestOperator;
    }

    private function resolveMeanReward(array $data): float
    {
        if (isset($data['mean_reward'])) {
            return (float) $data['mean_reward'];
        }

        if (isset($data['average_reward'])) {
            return (float) $data['average_reward'];
        }

        if (isset($data['average'])) {
            return (float) $data['average'];
        }

        if (isset($data['reward'], $data['uses']) && (int) $data['uses'] > 0) {
            return (float) $data['reward'] / (int) $data['uses'];
        }

        return 0.0;
    }
}
