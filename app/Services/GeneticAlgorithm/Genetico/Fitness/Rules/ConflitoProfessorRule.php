<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules;

use App\Models\Aula;
use App\Models\ConfiguracaoHorario; // ✅ ADICIONADO
use App\Models\Professor; // Adicionado para buscar nome
use App\Models\RestricaoTempo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene; // ✅ ADICIONADO
use App\Services\GeneticAlgorithm\Genetico\Fitness\HardRuleInterface; // ✅ ADICIONADO
use Illuminate\Support\Collection;

class ConflitoProfessorRule implements FitnessRuleInterface, HardRuleInterface
{
    private ConfiguracaoHorario $configuracaoHorario;
 
    private Collection $aulas;
 
    private Collection $restricoes;
    private GeneticAlgorithmConfigDTO $configAG;

    // ✅ ADICIONADO: Método setContext para receber as dependências
    public function setContext(ConfiguracaoHorario $configuracaoHorario, Collection $aulas, Collection $restricoes, GeneticAlgorithmConfigDTO $configAG): void {
        $this->configuracaoHorario = $configuracaoHorario;
        $this->aulas = $aulas;
        $this->restricoes = $restricoes;
        $this->configAG = $configAG;
    }

    public function apply(Cromossomo $cromossomo): RuleResult
    {
        $penalidade = 0.0;
        $conflicts = [];

        // Agrupa os genes por dia, período e professor
        $alocacoesPorProfessorDiaTempo = new Collection();
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->professor) continue;

            // Para aulas com duração maior que 1 tempo, verifica cada slot ocupado
            for ($i = 0; $i < $gene->duracaoTempos; $i++) {
                $currentPeriodo = $gene->periodoDia + $i;
                $key = "{$gene->professor->id}_{$gene->diaSemana}_{$currentPeriodo}";
                $alocacoesPorProfessorDiaTempo[$key][] = $gene;
            }
        }

        foreach ($alocacoesPorProfessorDiaTempo as $key => $alocacoes) {
            if (count($alocacoes) > 1) {
                // Conflito: professor alocado em mais de uma aula no mesmo slot de tempo
                $violacoes = count($alocacoes) - 1;
                $penalidade += $violacoes; // Cada violação adiciona penalidade

                // Detalhes do conflito
                [$professorId, $dia, $tempo] = explode('_', $key);
                $professor = Professor::find($professorId); // Busca o professor para o nome
                $aulasConflitantes = Collection::make($alocacoes)->map(fn($g) => $g->aula->disciplina->nome . ' (' . $g->turma->nome . ')')->implode(', ');
                $alocados = $violacoes + 1;
                $conflicts[] = "Professor {$professor->nome} alocado em {$alocados} aulas ({$aulasConflitantes}) no Dia {$dia}, Período {$tempo}.";
            }
        }

        // Retorna a penalidade total encontrada por esta regra
        return new RuleResult($penalidade, $conflicts);
    }

    public function getName(): string
    {
        return 'Conflito de Professor';
    }
}
