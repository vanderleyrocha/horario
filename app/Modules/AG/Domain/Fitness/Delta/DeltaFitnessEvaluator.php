<?php

namespace App\Modules\AG\Domain\Fitness\Delta;

use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Fitness\FitnessWeights;
use App\Modules\AG\Domain\Fitness\Incremental\IncrementalRule;
use App\Modules\Horarios\Domain\Evaluation\Contracts\RuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;

final class DeltaFitnessEvaluator
{
    public function __construct(
        private readonly FitnessWeights $weights
    ) {}

    public function evaluateDelta(
        FitnessResult $previous,
        EvaluationContext $context,
        AffectedRegion $region,
        array $rules
    ): FitnessResult {

        $hardDelta = 0.0;
        $softDelta = 0.0;

        foreach ($rules as $rule) {

            if (! $rule instanceof RuleInterface) {
                continue;
            }

            $deltaPenalty = 0.0;

            /*
            |------------------------------------------------------------
            | Incremental evaluation
            |------------------------------------------------------------
            */

            if ($rule instanceof IncrementalRule) {

                $deltaPenalty =
                    $rule->evaluateIncremental($context, $region);
            }
            /*
            |------------------------------------------------------------
            | Delta evaluation
            |------------------------------------------------------------
            */ elseif ($rule instanceof DeltaEvaluableRule) {

                $deltaPenalty =
                    $rule->evaluateDelta($context, $region);
            }
            /*
            |------------------------------------------------------------
            | Full evaluation fallback
            |------------------------------------------------------------
            */ else {

                $result = $rule->evaluate($context);

                $deltaPenalty = $result->penalty();
            }

            if ($deltaPenalty === 0.0) {
                continue;
            }

            $weight = $this->weights->get($rule::class);

            $weighted = $deltaPenalty * $weight;

            if ($rule->isHard()) {
                $hardDelta += $weighted;
            } else {
                $softDelta += $weighted;
            }
        }

        $hard = max(0.0, $previous->hardPenalty() + $hardDelta);
        $soft = max(0.0, $previous->softPenalty() + $softDelta);

        $total = $hard + $soft;

        $score = max(0.0, 100.0 - $total);

        return new FitnessResult(
            score: $score,
            totalPenalty: $total,
            hardPenalty: $hard,
            softPenalty: $soft
        );
    }
}
