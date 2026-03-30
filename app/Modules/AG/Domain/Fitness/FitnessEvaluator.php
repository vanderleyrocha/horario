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

        // ✅ AÇÃO 01: Evitar saturação de score viável usando logaritmo
        // Problema: fórmula original (50 + 50/(1+soft)) satura em ~50 para soft penalties altas
        // Exemplo ruim: para 157 aulas weekly + DistributionRule*2 = soft_penalty=314 → score≈50.16
        // Solução: usar logaritmo para normalizar soft penalty e manter gradiente
        // log(1 + soft/50) normaliza soft penalties na escala esperada
        $softComponent = $softPenalty > 0.0
            ? log(1.0 + ($softPenalty / 50.0))  // Desloca soft penalties para escala logarítmica
            : 0.0;

        return 50.0 + (50.0 / (1.0 + $softComponent));
        // Agora: soft=0 → score=100, soft=50 → score≈79.5, soft=314 → score≈66.7
        // Mantém diferenciação mesmo com penalidades 6x maiores ✅
    }
}
