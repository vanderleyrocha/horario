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
        $weights = [];
        $sum = 0;

        foreach ($operators as $name => $data) {

            $weight = exp($data['reward'] / $this->temperature);

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
}
