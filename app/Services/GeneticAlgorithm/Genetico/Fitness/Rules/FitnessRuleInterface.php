<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules;

use App\Services\GeneticAlgorithm\Genetico\Fitness\EvaluationContext;

interface FitnessRuleInterface
{
    public function evaluate(EvaluationContext $context): RuleResult;
}