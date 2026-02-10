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

class BloqueiosHardRule implements FitnessRuleInterface, HardRuleInterface
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

        foreach ($cromossomo->genes as $gene) {
            if ($gene->isEmpty()) continue;

            // Verificar restrições de professor
            if ($gene->professor) {
                $key = "professor_{$gene->professor->id}";
                if ($this->restricoes->has($key)) {
                    foreach ($this->restricoes[$key] as $restricao) {
                        if ($restricao->dia_semana == $gene->diaSemana && $restricao->tempo == $gene->periodoDia && $restricao->tipo == 'hard') {
                            $penalidade += 1.0; // Penalidade por violar bloqueio hard
                            $conflicts[] = "Professor {$gene->professor->nome} alocado em horário bloqueado (Dia {$gene->diaSemana}, Período {$gene->periodoDia}).";
                        }
                    }
                }
            }

            // Verificar restrições de turma
            if ($gene->turma) {
                $key = "turma_{$gene->turma->id}";
                if ($this->restricoes->has($key)) {
                    foreach ($this->restricoes[$key] as $restricao) {
                        if ($restricao->dia_semana == $gene->diaSemana && $restricao->tempo == $gene->periodoDia && $restricao->tipo == 'hard') {
                            $penalidade += 1.0; // Penalidade por violar bloqueio hard
                            $conflicts[] = "Turma {$gene->turma->nome} alocada em horário bloqueado (Dia {$gene->diaSemana}, Período {$gene->periodoDia}).";
                        }
                    }
                }
            }

            // Verificar restrições de disciplina (se aplicável, ex: disciplina não pode ser dada em certo dia/tempo)
            // Isso dependeria de como as restrições de disciplina são modeladas.
            // Exemplo:
            /*
            if ($gene->disciplina) {
                $key = "disciplina_{$gene->disciplina->id}";
                if ($this->restricoes->has($key)) {
                    foreach ($this->restricoes[$key] as $restricao) {
                        if ($restricao->dia_semana == $gene->diaSemana && $restricao->tempo == $gene->periodoDia && $restricao->tipo == 'hard') {
                            $penalidade += 1.0;
                            $conflicts[] = "Disciplina {$gene->disciplina->nome} alocada em horário bloqueado (Dia {$gene->diaSemana}, Período {$gene->periodoDia}).";
                        }
                    }
                }
            }
            */
        }

        return new RuleResult($penalidade, $conflicts);
    }

    public function getName(): string
    {
        return 'Bloqueios Hard';
    }
}
