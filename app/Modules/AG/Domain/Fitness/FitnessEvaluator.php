<?php

namespace App\Modules\AG\Domain\Fitness;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\Contracts\RuleInterface;

final class FitnessEvaluator {
    /**
     * @param RuleInterface[] $rules
     */
    public function __construct(private readonly FitnessWeights $weights, private readonly array $rules) {
    }

    public function evaluate(Cromossomo $cromossomo, EvaluationContext $context): FitnessResult {

        $hardPenalty = 0.0;
        $softPenalty = 0.0;

        foreach ($this->rules as $rule) {

            if (!$rule instanceof RuleInterface) {
                throw new \InvalidArgumentException('All rules must implement RuleInterface');
            }

            $result = $rule->evaluate($context);

            $basePenalty = max(0.0, $result->penalty());

            if ($basePenalty === 0.0) {
                continue;
            }

            $weight = $this->weights->get($rule::class);

            $weightedPenalty = $basePenalty * $weight;

            if ($rule->isHard()) {
                $hardPenalty += $weightedPenalty;
            } else {
                $softPenalty += $weightedPenalty;
            }
        }

        $totalPenalty = $hardPenalty + $softPenalty;

        /**
         * Modelo de Score:
         * 100 = solução perfeita
         * Penalidade reduz score
         */
        $score = max(0.0, 100.0 - $totalPenalty);

        $cromossomo->setFitness($score);

        return new FitnessResult(
            score: $score,
            totalPenalty: $totalPenalty,
            hardPenalty: $hardPenalty,
            softPenalty: $softPenalty
        );
    }
}
