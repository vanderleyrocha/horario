<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness;

use App\Models\ConfiguracaoHorario; // ✅ ALTERADO: De Horario para ConfiguracaoHorario
use App\Models\Aula;
use App\Models\RestricaoTempo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\FitnessRuleInterface; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Fitness\HardRuleInterface; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Fitness\SoftRuleInterface; // ✅ ADICIONADO
use Illuminate\Support\Collection;

class FitnessEvaluator
{
    private array $rules;
    private FitnessWeights $weights;

    private ConfiguracaoHorario $configuracaoHorario;
    
    private Collection $aulas;
    
    private Collection $restricoes;
    private GeneticAlgorithmConfigDTO $configAG;

    public function __construct(array $rules, FitnessWeights $weights)
    {
        $this->rules = $rules;
        $this->weights = $weights;
    }

    public function setContext(ConfiguracaoHorario $configuracaoHorario, Collection $aulas, Collection $restricoes, GeneticAlgorithmConfigDTO $configAG): void {
        $this->configuracaoHorario = $configuracaoHorario;
        $this->aulas = $aulas;
        $this->restricoes = $restricoes;
        $this->configAG = $configAG;

        // Passar o contexto para cada regra de fitness
        foreach ($this->rules as $rule) {
            if (method_exists($rule, 'setContext')) {
                $rule->setContext($configuracaoHorario, $aulas, $restricoes, $configAG);
            }
        }
    }

    public function evaluate(Cromossomo $cromossomo): FitnessResult
    {
        if (!isset($this->configuracaoHorario, $this->aulas, $this->restricoes, $this->configAG)) {
            throw new \Exception("FitnessEvaluator context not set. Call setContext() before evaluate().");
        }

        $totalPenalidadesHard = 0.0;
        $totalPenalidadesSoft = 0.0;
        $ruleScores = [];
        $conflicts = [];

        foreach ($this->rules as $rule) {
            /** @var RuleResult $ruleResult */
            $ruleResult = $rule->apply($cromossomo);
            $weight = $this->weights->getWeight($rule::class);

            // O score da regra é a penalidade que ela encontrou
            $penalidadeDaRegra = abs($ruleResult->score); // Usamos abs pois o score pode ser negativo

            if ($rule instanceof HardRuleInterface) {
                $totalPenalidadesHard += ($penalidadeDaRegra * $weight);
            } elseif ($rule instanceof SoftRuleInterface) {
                $totalPenalidadesSoft += ($penalidadeDaRegra * $weight);
            }

            $ruleScores[$rule::class] = $penalidadeDaRegra * $weight; // Armazena a penalidade ponderada

            if (!empty($ruleResult->conflicts)) {
                $conflicts[$rule::class] = $ruleResult->conflicts;
            }
        }

        // Se houver qualquer violação de regra hard, o fitness é 0
        if ($totalPenalidadesHard > 0) {
            $finalFitnessScore = 0.0;
        } else {
            // O score base é 100, e as penalidades soft o reduzem
            $scoreBase = 100.0;
            $finalFitnessScore = $scoreBase - $totalPenalidadesSoft;
        }

        // Garantir que o fitness esteja entre 0 e 100
        $finalFitnessScore = max(0.0, min(100.0, $finalFitnessScore));

        $fitnessResult = new FitnessResult($finalFitnessScore, $ruleScores, $conflicts);
        $cromossomo->setFitnessResult($fitnessResult);
        return $fitnessResult;
    }

    public function getTotalHardPenalties(Cromossomo $cromossomo): float
    {
        if (!$cromossomo->fitnessResult) {
            $this->evaluate($cromossomo); // Garante que o fitness foi avaliado
        }
        $total = 0.0;
        foreach ($cromossomo->fitnessResult->ruleScores as $ruleClass => $score) {
            // Verifica se a regra é uma HardRule
            if (in_array(HardRuleInterface::class, class_implements($ruleClass))) {
                $total += $score;
            }
        }
        return $total;
    }

    public function getTotalSoftPenalties(Cromossomo $cromossomo): float
    {
        if (!$cromossomo->fitnessResult) {
            $this->evaluate($cromossomo); // Garante que o fitness foi avaliado
        }
        $total = 0.0;
        foreach ($cromossomo->fitnessResult->ruleScores as $ruleClass => $score) {
            // Verifica se a regra é uma SoftRule
            if (in_array(SoftRuleInterface::class, class_implements($ruleClass))) {
                $total += $score;
            }
        }
        return $total;
    }
}
