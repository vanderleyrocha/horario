<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules;

use App\Models\Aula;
use App\Models\ConfiguracaoHorario;
use App\Models\RestricaoTempo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;
use App\Services\GeneticAlgorithm\Genetico\Fitness\SoftRuleInterface;
use Illuminate\Support\Collection;

class DistribuicaoRule implements FitnessRuleInterface, SoftRuleInterface
{
    private ConfiguracaoHorario $configuracaoHorario;
    /** @var Collection<int, Aula> */
    private Collection $aulas;
    /** @var Collection<string, Collection<int, RestricaoTempo>> */
    private Collection $restricoes;
    private GeneticAlgorithmConfigDTO $configAG;

    public function setContext(
        ConfiguracaoHorario $configuracaoHorario,
        Collection $aulas,
        Collection $restricoes,
        GeneticAlgorithmConfigDTO $configAG
    ): void {
        $this->configuracaoHorario = $configuracaoHorario;
        $this->aulas = $aulas;
        $this->restricoes = $restricoes;
        $this->configAG = $configAG;
    }

    public function apply(Cromossomo $cromossomo): RuleResult
    {
        $penalidade = 0.0;
        $conflicts = [];

        // Objetivo: Distribuir as aulas de uma disciplina ao longo da semana para uma turma
        // Evitar concentrar muitas aulas da mesma disciplina em poucos dias.

        // Agrupa os genes por turma e disciplina
        $aulasPorTurmaDisciplina = new Collection();
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->turma || !$gene->disciplina) continue;
            $aulasPorTurmaDisciplina[$gene->turma->id][$gene->disciplina->id][] = $gene;
        }

        foreach ($aulasPorTurmaDisciplina as $turmaId => $disciplinas) {
            foreach ($disciplinas as $disciplinaId => $genesDaDisciplina) {
                $diasComAula = Collection::make($genesDaDisciplina)->unique(fn(Gene $gene) => $gene->diaSemana)->count();
                $totalAulas = count($genesDaDisciplina);

                if ($totalAulas > 0) {
                    // Penaliza se o número de dias com aula for muito menor que o total de aulas
                    // Ex: 3 aulas da mesma disciplina em 1 dia é pior que 3 aulas em 3 dias diferentes.
                    // Uma penalidade simples pode ser (totalAulas - diasComAula)
                    $penalidadePorConcentracao = $totalAulas - $diasComAula;
                    $penalidade += $penalidadePorConcentracao * 0.5; // Penalidade menor para distribuição

                    if ($penalidadePorConcentracao > 0) {
                        $turma = $this->aulas->firstWhere('turma_id', $turmaId)?->turma;
                        $disciplina = $this->aulas->firstWhere('disciplina_id', $disciplinaId)?->disciplina;
                        $conflicts[] = "Aulas da disciplina {$disciplina->nome} para a turma {$turma->nome} estão concentradas em poucos dias ({$diasComAula} dias para {$totalAulas} aulas).";
                    }
                }
            }
        }

        return new RuleResult($penalidade, $conflicts);
    }

    public function getName(): string
    {
        return 'Distribuição de Aulas';
    }
}
