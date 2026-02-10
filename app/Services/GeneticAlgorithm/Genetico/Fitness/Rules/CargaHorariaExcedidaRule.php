<?php

namespace App\Services\GeneticAlgorithm\Genetico\Fitness\Rules;

use App\Models\Aula;
use App\Models\ConfiguracaoHorario;
use App\Models\RestricaoTempo;
use App\Services\GeneticAlgorithm\Genetico\DTO\GeneticAlgorithmConfigDTO;
use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;
use App\Services\GeneticAlgorithm\Genetico\Fitness\HardRuleInterface;
use Illuminate\Support\Collection;

class CargaHorariaExcedidaRule implements FitnessRuleInterface, HardRuleInterface
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

        // Agrupa as aulas por professor para calcular a carga horária
        $cargaHorariaPorProfessor = new Collection(); // [professor_id => total_tempos_alocados]
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->professor) continue;
            $cargaHorariaPorProfessor[$gene->professor->id] = ($cargaHorariaPorProfessor[$gene->professor->id] ?? 0) + $gene->duracaoTempos;
        }

        // Verifica se algum professor excedeu sua carga horária máxima
        foreach ($cargaHorariaPorProfessor as $professorId => $temposAlocados) {
            $professor = $this->aulas->firstWhere('professor_id', $professorId)?->professor; // Pega o professor de uma das aulas
            if ($professor && $professor->carga_horaria_maxima && $temposAlocados > $professor->carga_horaria_maxima) {
                $excesso = $temposAlocados - $professor->carga_horaria_maxima;
                $penalidade += $excesso; // Penalidade proporcional ao excesso
                $conflicts[] = "Professor {$professor->nome} excedeu a carga horária máxima em {$excesso} tempos (Alocado: {$temposAlocados}, Máximo: {$professor->carga_horaria_maxima}).";
            }
        }

        // Agrupa as aulas por turma para calcular a carga horária
        $cargaHorariaPorTurma = new Collection(); // [turma_id => total_tempos_alocados]
        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty() || !$gene->turma) continue;
            $cargaHorariaPorTurma[$gene->turma->id] = ($cargaHorariaPorTurma[$gene->turma->id] ?? 0) + $gene->duracaoTempos;
        }

        // Verifica se alguma turma excedeu sua carga horária máxima (se houver)
        // Assumindo que a carga horária máxima da turma está na configuracaoHorario ou na própria Turma
        foreach ($cargaHorariaPorTurma as $turmaId => $temposAlocados) {
            $turma = $this->aulas->firstWhere('turma_id', $turmaId)?->turma;
            if ($turma && $turma->carga_horaria_maxima && $temposAlocados > $turma->carga_horaria_maxima) {
                $excesso = $temposAlocados - $turma->carga_horaria_maxima;
                $penalidade += $excesso;
                $conflicts[] = "Turma {$turma->nome} excedeu a carga horária máxima em {$excesso} tempos (Alocado: {$temposAlocados}, Máximo: {$turma->carga_horaria_maxima}).";
            }
        }

        return new RuleResult($penalidade, $conflicts);
    }

    public function getName(): string
    {
        return 'Carga Horária Excedida';
    }
}
