<?php

namespace App\Modules\AG\Domain\Fitness;

use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\Delta\DeltaFitnessEvaluator;
use App\Modules\AG\Domain\Fitness\Delta\Dependency\RuleDependencyBuilder;
use App\Modules\AG\Domain\Fitness\Delta\Dependency\RuleDependencyGraph;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;

final class FitnessEvaluator
{
    /**
     * Cache por assinatura estrutural
     */
    private array $cache = [];

    private DeltaFitnessEvaluator $deltaEvaluator;

    /**
     * Rule Dependency Graph
     */
    private RuleDependencyGraph $dependencyGraph;

    public function __construct(private readonly FitnessWeights $weights, private readonly array $rules)
    {

        $this->deltaEvaluator = new DeltaFitnessEvaluator($weights);

        $this->dependencyGraph = RuleDependencyBuilder::build($rules);
    }

    public function evaluate(Cromossomo $cromossomo, EvaluationContext $context): FitnessResult
    {

        $signature = $cromossomo->signature();

        /**
         * Cache por assinatura estrutural
         */
        if (isset($this->cache[$signature])) {

            $cached = $this->cache[$signature];

            $cromossomo->setFitness($cached->score());

            return $cached;
        }

        $hardPenalty = 0.0;
        $softPenalty = 0.0;

        foreach ($this->rules as $rule) {

            $result = $rule->evaluate($context);

            $penalty = $result->penalty();

            if ($penalty === 0.0) {
                continue;
            }

            $weight = $this->weights->get($rule::class);

            $weighted = $penalty * $weight;

            if ($rule->isHard()) {
                $hardPenalty += $weighted;
            } else {
                $softPenalty += $weighted;
            }
        }

        $totalPenalty = $hardPenalty + $softPenalty;
        $score = $this->computeLexicographicScore($hardPenalty, $softPenalty);

        $cromossomo->setFitness($score);

        $result = new FitnessResult(score: $score, totalPenalty: $totalPenalty, hardPenalty: $hardPenalty, softPenalty: $softPenalty);

        $this->cache[$signature] = $result;

        return $result;
    }

    /**
     * Avaliação incremental usando Delta + Rule Dependency Graph
     */
    public function evaluateDelta(Cromossomo $cromossomo, EvaluationContext $context, AffectedRegion $region, FitnessResult $previous): FitnessResult
    {

        /**
         * Determina quais regras são afetadas
         */
        $affectedRuleClasses =
            $this->dependencyGraph
                ->affectedRules($region);

        /**
         * Filtra as regras realmente necessárias
         */
        $affectedRules = [];

        foreach ($this->rules as $rule) {

            if (in_array($rule::class, $affectedRuleClasses, true)) {
                $affectedRules[] = $rule;
            }
        }

        /**
         * Se nenhuma regra foi detectada (fallback seguro)
         */
        if (empty($affectedRules)) {
            return $this->evaluate($cromossomo, $context);
        }

        /**
         * Executa Delta Fitness apenas nas regras afetadas
         */
        $result =
            $this->deltaEvaluator->evaluateDelta($previous, $context, $region, $affectedRules);

        $cromossomo->setFitness($result->score());

        return $result;
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }

    private function computeLexicographicScore(float $hardPenalty, float $softPenalty): float
    {
        /*
         * Objetivo lexicográfico:
         * - Soluções sem conflito hard sempre ficam na faixa [50, 100].
         * - Soluções com conflito hard sempre ficam na faixa [0, 50).
         * Assim, qualquer solução viável domina qualquer inviável.
         */
        if ($hardPenalty > 0.0) {
            $hardComponent = $hardPenalty;
            $softComponent = $softPenalty * 0.10;

            return min(49.999, 49.999 / (1.0 + $hardComponent + $softComponent));
        }

        return 50.0 + max(0.0, 50.0 - $softPenalty);
    }
}
