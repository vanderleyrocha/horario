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

class JanelasRule implements FitnessRuleInterface, SoftRuleInterface
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

        if ($this->configAG->permitirJanelas) {
            return new RuleResult(0.0); // Se janelas são permitidas, não há penalidade
        }

        // Penaliza janelas para professores
        $aulasPorProfessorDia = new Collection();
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->professor) continue;
            $aulasPorProfessorDia[$gene->professor->id][$gene->diaSemana][] = $gene;
        }

        foreach ($aulasPorProfessorDia as $professorId => $dias) {
            foreach ($dias as $diaSemana => $genesDoDia) {
                usort($genesDoDia, fn(Gene $a, Gene $b) => $a->periodoDia <=> $b->periodoDia);

                if (count($genesDoDia) < 2) continue; // Precisa de pelo menos duas aulas para ter janela

                $primeiraAula = $genesDoDia->first();
                $ultimaAula = $genesDoDia->last();

                // Calcula o "gap" entre a primeira e a última aula, considerando a duração
                $inicioPrimeira = $primeiraAula->periodoDia;
                $fimUltima = $ultimaAula->periodoDia + $ultimaAula->duracaoTempos - 1; // Último slot ocupado

                $slotsOcupados = new Collection();
                foreach ($genesDoDia as $gene) {
                    for ($i = 0; $i < $gene->duracaoTempos; $i++) {
                        $slotsOcupados->add($gene->periodoDia + $i);
                    }
                }

                $janelas = 0;
                for ($tempo = $inicioPrimeira + 1; $tempo < $fimUltima; $tempo++) {
                    if (!$slotsOcupados->contains($tempo)) {
                        // Verifica se este slot é um intervalo oficial
                        $ehIntervaloOficial = false;
                        foreach ($this->configAG->horariosIntervalos as $intervaloPosicao) {
                            if ($intervaloPosicao === $tempo) { // Intervalo na posição do slot
                                $ehIntervaloOficial = true;
                                break;
                            }
                        }
                        if (!$ehIntervaloOficial) {
                            $janelas++;
                        }
                    }
                }

                if ($janelas > 0) {
                    $penalidade += $janelas * 1.0; // Penalidade por cada slot de janela
                    $professor = $this->aulas->firstWhere('professor_id', $professorId)?->professor;
                    $conflicts[] = "Professor {$professor->nome} tem janela de {$janelas} tempos no Dia {$diaSemana}.";
                }
            }
        }

        // Penaliza janelas para turmas (lógica similar)
        $aulasPorTurmaDia = new Collection();
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->turma) continue;
            $aulasPorTurmaDia[$gene->turma->id][$gene->diaSemana][] = $gene;
        }

        foreach ($aulasPorTurmaDia as $turmaId => $dias) {
            foreach ($dias as $diaSemana => $genesDoDia) {
                usort($genesDoDia, fn(Gene $a, Gene $b) => $a->periodoDia <=> $b->periodoDia);

                if (count($genesDoDia) < 2) continue;

                $primeiraAula = $genesDoDia->first();
                $ultimaAula = $genesDoDia->last();

                $inicioPrimeira = $primeiraAula->periodoDia;
                $fimUltima = $ultimaAula->periodoDia + $ultimaAula->duracaoTempos - 1;

                $slotsOcupados = new Collection();
                foreach ($genesDoDia as $gene) {
                    for ($i = 0; $i < $gene->duracaoTempos; $i++) {
                        $slotsOcupados->add($gene->periodoDia + $i);
                    }
                }

                $janelas = 0;
                for ($tempo = $inicioPrimeira + 1; $tempo < $fimUltima; $tempo++) {
                    if (!$slotsOcupados->contains($tempo)) {
                        $ehIntervaloOficial = false;
                        foreach ($this->configAG->horariosIntervalos as $intervaloPosicao) {
                            if ($intervaloPosicao === $tempo) {
                                $ehIntervaloOficial = true;
                                break;
                            }
                        }
                        if (!$ehIntervaloOficial) {
                            $janelas++;
                        }
                    }
                }

                if ($janelas > 0) {
                    $penalidade += $janelas * 1.0;
                    $turma = $this->aulas->firstWhere('turma_id', $turmaId)?->turma;
                    $conflicts[] = "Turma {$turma->nome} tem janela de {$janelas} tempos no Dia {$diaSemana}.";
                }
            }
        }

        return new RuleResult($penalidade, $conflicts);
    }

    public function getName(): string
    {
        return 'Janelas';
    }
}
