<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules;

use App\Models\Aula;
use App\Models\ConfiguracaoHorario;
use App\Models\RestricaoTempo;
use App\Models\Turma; // Adicionado para buscar nome
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;
use App\Services\GeneticAlgorithm\Genetico\Fitness\HardRuleInterface;
use Illuminate\Support\Collection;

class ConflitoTurmaRule implements FitnessRuleInterface, HardRuleInterface
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

        // Agrupa os genes por dia, período e turma
        $alocacoesPorTurmaDiaTempo = new Collection();
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->turma) continue;

            // Para aulas com duração maior que 1 tempo, verifica cada slot ocupado
            for ($i = 0; $i < $gene->duracaoTempos; $i++) {
                $currentPeriodo = $gene->periodoDia + $i;
                $key = "{$gene->turma->id}_{$gene->diaSemana}_{$currentPeriodo}";
                $alocacoesPorTurmaDiaTempo[$key][] = $gene;
            }
        }

        foreach ($alocacoesPorTurmaDiaTempo as $key => $alocacoes) {
            if (count($alocacoes) > 1) {
                // Conflito: turma alocada em mais de uma aula no mesmo slot de tempo
                $violacoes = count($alocacoes) - 1;
                $penalidade += $violacoes; // Cada violação adiciona penalidade

                // Detalhes do conflito
                [$turmaId, $dia, $tempo] = explode('_', $key);
                $turma = Turma::find($turmaId); // Busca a turma para o nome
                $aulasConflitantes = Collection::make($alocacoes)->map(fn($g) => $g->aula->disciplina->nome . ' (' . $g->professor->nome . ')')->implode(', ');
                $alocadasEm = $violacoes + 1;
                $conflicts[] = "Turma {$turma->nome} alocada em {$alocadasEm} aulas ({$aulasConflitantes}) no Dia {$dia}, Período {$tempo}.";
            }
        }

        return new RuleResult($penalidade, $conflicts);
    }

    public function getName(): string
    {
        return 'Conflito de Turma';
    }
}
