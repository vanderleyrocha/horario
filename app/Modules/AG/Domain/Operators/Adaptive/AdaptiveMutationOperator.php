<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Adaptive;

use App\Modules\AG\Domain\Operators\Mutation\Interfaces\AdaptiveOperatorInterface;
use App\Modules\AG\Domain\Operators\Mutation\MutationOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class AdaptiveMutationOperator implements AdaptiveOperatorInterface, MutationOperatorInterface
{
    private array $operators;

    private array $scores = [];

    private ?MutationOperatorInterface $lastOperator = null;

    public function __construct(MutationOperatorInterface ...$operators)
    {
        $this->operators = $operators;

        foreach ($operators as $op) {
            $this->scores[$op::class] = 1.0;
        }
    }

    public function mutate(Cromossomo $individual): Cromossomo
    {
        $operator = $this->selectOperator();

        $this->lastOperator = $operator;

        return $operator->mutate($individual);
    }

    private function selectOperator(): MutationOperatorInterface
    {
        $sum = array_sum($this->scores);

        $r = mt_rand() / mt_getrandmax() * $sum;

        $acc = 0;

        foreach ($this->operators as $op) {

            $acc += $this->scores[$op::class];

            if ($r <= $acc) {
                return $op;
            }
        }

        return $this->operators[array_rand($this->operators)];
    }

    public function recordImprovement(float $improvement): void
    {
        if (! $this->lastOperator) {
            return;
        }

        $class = $this->lastOperator::class;

        $this->scores[$class] =
            ($this->scores[$class] * 0.9)
            + ($improvement * 0.1);
    }
}
