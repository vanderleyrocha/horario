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

class MaxAulasDiaRule implements FitnessRuleInterface, SoftRuleInterface
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

        // Penaliza professores com muitas aulas em um único dia
        $aulasPorProfessorDia = new Collection();
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->professor) continue;
            $aulasPorProfessorDia[$gene->professor->id][$gene->diaSemana][] = $gene;
        }

        foreach ($aulasPorProfessorDia as $professorId => $dias) {
            foreach ($dias as $diaSemana => $genesDoDia) {
                $totalAulasNoDia = Collection::make($genesDoDia)->sum(fn(Gene $gene) => $gene->duracaoTempos);
                if ($totalAulasNoDia > $this->configAG->maxAulasSeguidas) { // Usando maxAulasSeguidas como limite por dia
                    $excesso = $totalAulasNoDia - $this->configAG->maxAulasSeguidas;
                    $penalidade += $excesso * 0.5; // Penalidade menor para soft rule
                    $professor = $this->aulas->firstWhere('professor_id', $professorId)?->professor;
                    $conflicts[] = "Professor {$professor->nome} tem {$totalAulasNoDia} tempos de aula no Dia {$diaSemana}, excedendo o máximo de {$this->configAG->maxAulasSeguidas}.";
                }
            }
        }

        // Penaliza turmas com muitas aulas em um único dia (lógica similar)
        $aulasPorTurmaDia = new Collection();
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->turma) continue;
            $aulasPorTurmaDia[$gene->turma->id][$gene->diaSemana][] = $gene;
        }

        foreach ($aulasPorTurmaDia as $turmaId => $dias) {
            foreach ($dias as $diaSemana => $genesDoDia) {
                $totalAulasNoDia = Collection::make($genesDoDia)->sum(fn(Gene $gene) => $gene->duracaoTempos);
                if ($totalAulasNoDia > $this->configAG->maxAulasSeguidas) {
                    $excesso = $totalAulasNoDia - $this->configAG->maxAulasSeguidas;
                    $penalidade += $excesso * 0.5;
                    $turma = $this->aulas->firstWhere('turma_id', $turmaId)?->turma;
                    $conflicts[] = "Turma {$turma->nome} tem {$totalAulasNoDia} tempos de aula no Dia {$diaSemana}, excedendo o máximo de {$this->configAG->maxAulasSeguidas}.";
                }
            }
        }

        return new RuleResult($penalidade, $conflicts);
    }

    public function getName(): string
    {
        return 'Máximo de Aulas por Dia';
    }
}
