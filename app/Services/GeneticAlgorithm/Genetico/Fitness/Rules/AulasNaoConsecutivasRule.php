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

class AulasNaoConsecutivasRule implements FitnessRuleInterface, SoftRuleInterface {
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

    public function apply(Cromossomo $cromossomo): RuleResult {
        $penalidade = 0.0;
        $conflicts = [];

        // Agrupa os genes por turma e dia da semana
        $aulasPorTurmaDia = new Collection();
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->turma) continue;
            
            $turmaId = $gene->turma->id;
            $diaSemana = $gene->diaSemana;

            // Garante que o índice da turma exista e seja uma Collection
            if (!$aulasPorTurmaDia->has($turmaId)) {
                $aulasPorTurmaDia->put($turmaId, new Collection());
            }

            // Garante que o índice do dia da semana exista dentro da Collection da turma e seja um array
            // Usamos get() para pegar a Collection interna e put() para atualizar, ou podemos manipular diretamente o array subjacente
            $aulasDoDia = $aulasPorTurmaDia->get($turmaId)->get($diaSemana, []); // Pega o array, ou um array vazio se não existir
            $aulasDoDia[] = $gene; // Adiciona o gene
            $aulasPorTurmaDia->get($turmaId)->put($diaSemana, $aulasDoDia); // Atualiza a Collection interna
        }

        foreach ($aulasPorTurmaDia as $turmaId => $dias) {
            foreach ($dias as $diaSemana => $genesDoDia) {
                // Ordena as aulas do dia pelo período
                usort($genesDoDia, fn(Gene $a, Gene $b) => $a->periodoDia <=> $b->periodoDia);

                for ($i = 0; $i < count($genesDoDia) - 1; $i++) {
                    $geneAtual = $genesDoDia[$i];
                    $proximoGene = $genesDoDia[$i + 1];

                    // Verifica se as aulas são consecutivas (sem intervalo ou outra aula no meio)
                    // Considerando a duração das aulas
                    $fimAulaAtual = $geneAtual->periodoDia + $geneAtual->duracaoTempos;

                    // Se o início da próxima aula é exatamente o fim da aula atual, elas são consecutivas
                    if ($proximoGene->periodoDia === $fimAulaAtual) {
                        // Verifica se há um intervalo configurado entre esses períodos
                        $temIntervalo = false;
                        foreach ($this->configAG->horariosIntervalos as $intervaloPosicao) {
                            // Um intervalo na posição X significa que ele ocorre APÓS a aula X
                            // Então, se a aula atual termina no período Y e a próxima começa no Y+1,
                            // e há um intervalo após o período Y, então não é uma penalidade.
                            if ($intervaloPosicao === $fimAulaAtual) { // Intervalo após o término da aula atual
                                $temIntervalo = true;
                                break;
                            }
                        }

                        if (!$temIntervalo) {
                            $penalidade += 1.0; // Penalidade por aulas consecutivas sem intervalo
                            $conflicts[] = "Turma {$geneAtual->turma->nome} tem aulas consecutivas (Dia {$geneAtual->diaSemana}, Período {$geneAtual->periodoDia} e {$proximoGene->periodoDia}) sem intervalo.";
                        }
                    }
                }
            }
        }

        return new RuleResult($penalidade, $conflicts);
    }

    public function getName(): string {
        return 'Aulas Não Consecutivas';
    }
}
