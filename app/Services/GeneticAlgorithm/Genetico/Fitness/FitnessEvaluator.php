<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\FitnessRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\HardRuleInterface;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\SoftRuleInterface;

final class FitnessEvaluator
{

    public function __construct(private readonly FitnessWeights $weights, private readonly array $rules) {}

    public function evaluate(Cromossomo $cromossomo, EvaluationContext $context ): FitnessResult 
    {
        $hardPenalty = 0.0;
        $softPenalty = 0.0;

        foreach ($this->rules as $rule) {

            $result = $rule->evaluate($context);

            $weight = $this->weights->get($rule::class);

            $weighted = $result->getPenalty() * $weight;

            if ($rule instanceof HardRuleInterface) {
                $hardPenalty += $weighted;
            }

            if ($rule instanceof SoftRuleInterface) {
                $softPenalty += $weighted;
            }
        }

        $total = $hardPenalty + $softPenalty;

        $cromossomo->setFitness($total);

        return new FitnessResult(
            totalPenalty: $total,
            hardPenalty: $hardPenalty,
            softPenalty: $softPenalty
        );
    }
}