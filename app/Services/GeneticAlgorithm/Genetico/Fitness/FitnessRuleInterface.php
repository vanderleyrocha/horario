<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness;

use App\Models\ConfiguracaoHorario;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Fitness\Rules\RuleResult;
use Illuminate\Support\Collection;

interface FitnessRuleInterface
{
    public function apply(Cromossomo $cromossomo): RuleResult;
    public function getName(): string;
    public function setContext(ConfiguracaoHorario $configuracaoHorario, Collection $aulas, Collection $restricoes, GeneticAlgorithmConfigDTO $configAG): void;
}
