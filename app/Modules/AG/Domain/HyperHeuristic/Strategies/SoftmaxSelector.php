<?php

namespace App\Modules\AG\Domain\HyperHeuristic\Strategies;

use App\Modules\AG\Domain\HyperHeuristic\OperatorSelectionStrategy;

class SoftmaxSelector implements OperatorSelectionStrategy
{
    private float $temperature;

    public function __construct(float $temperature = 0.2)
    {
        $this->temperature = $temperature;
    }

    public function select(array $operators): string
    {
        if ($operators === []) {
            throw new \RuntimeException('SoftmaxSelector recebeu estatisticas vazias.');
        }

        $weights = [];
        $sum = 0.0;

        foreach ($operators as $name => $data) {
            $reward = $this->resolveMeanReward($data);
            $weight = exp($reward / $this->temperature);

            $weights[$name] = $weight;
            $sum += $weight;
        }

        $rand = mt_rand() / mt_getrandmax();

        $acc = 0;

        foreach ($weights as $name => $weight) {

            $acc += $weight / $sum;

            if ($rand <= $acc) {
                return $name;
            }
        }

        return array_key_first($operators);
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
