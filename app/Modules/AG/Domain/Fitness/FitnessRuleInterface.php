<?php

namespace App\Modules\AG\Domain\Fitness;

use App\Models\ConfiguracaoHorario;
use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Fitness\Rules\RuleResult;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use Illuminate\Support\Collection;

interface FitnessRuleInterface {
    public function apply(Cromossomo $cromossomo): RuleResult;
    public function getName(): string;
    public function setContext(ConfiguracaoHorario $configuracaoHorario, Collection $aulas, Collection $restricoes, GeneticAlgorithmConfigDTO $configAG): void;
}
