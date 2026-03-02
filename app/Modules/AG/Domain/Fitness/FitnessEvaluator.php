<?php

namespace App\Modules\AG\Domain\Fitness;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Fitness\Rules\HardRuleInterface;
use App\Modules\AG\Domain\Fitness\Rules\SoftRuleInterface;

final class FitnessEvaluator {
    /**
     * @param array<\App\Modules\AG\Domain\Fitness\Rules\FitnessRuleInterface> $rules
     */
    public function __construct(
        private readonly FitnessWeights $weights,
        private readonly array $rules
    ) {
    }

    public function evaluate(Cromossomo $cromossomo): FitnessResult {
        $context = new EvaluationContext($cromossomo);

        $hardPenalty = 0.0;
        $softPenalty = 0.0;

        foreach ($this->rules as $rule) {

            $result = $rule->evaluate($context);

            $penalty = max(0.0, $result->getPenalty());
            $weight  = $this->weights->get($rule::class);

            $weightedPenalty = $penalty * $weight;

            if ($rule instanceof HardRuleInterface) {
                $hardPenalty += $weightedPenalty;
            }

            if ($rule instanceof SoftRuleInterface) {
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

        // Persistimos fitness diretamente no cromossomo
        $cromossomo->setFitness($score);

        return new FitnessResult(
            score: $score,
            totalPenalty: $totalPenalty,
            hardPenalty: $hardPenalty,
            softPenalty: $softPenalty
        );
    }
}
