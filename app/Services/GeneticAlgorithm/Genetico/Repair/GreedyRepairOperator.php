<?php

declare(strict_types=1);

namespace App\Services\GeneticAlgorithm\Genetico\Repair;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;

final class GreedyRepairOperator {

    public function repair(Cromossomo $cromossomo, array $horariosDisponiveis, int $aulasPorDia): void {

        $genes = $cromossomo->getGenes();

        foreach ($genes as $index => $gene) {

            if (!$this->isGeneValido($cromossomo, $gene)) {

                $novoGene = $this->realocarGene($cromossomo, $gene, $horariosDisponiveis, $aulasPorDia);

                if ($novoGene !== null) {
                    $cromossomo->replaceGene($index, $novoGene);
                }
            }
        }
    }

    private function isGeneValido(Cromossomo $cromossomo, Gene $gene): bool {
        $profIndex = $cromossomo->getProfessorHorarioIndex();
        $turmaIndex = $cromossomo->getTurmaHorarioIndex();

        $prof = $gene->getProfessorId();
        $turma = $gene->getTurmaId();
        $dia = $gene->getDiaSemana();
        $periodo = $gene->getPeriodoDia();

        return ($profIndex[$prof][$dia][$periodo] ?? 0) <= 1 &&
            ($turmaIndex[$turma][$dia][$periodo] ?? 0) <= 1;
    }

    private function realocarGene(Cromossomo $cromossomo, Gene $gene, array $horariosDisponiveis, int $aulasPorDia): ?Gene {

        shuffle($horariosDisponiveis);

        foreach ($horariosDisponiveis as $slot) {

            $dia = $slot['dia'];
            $tempo = $slot['tempo'];

            if ($tempo + $gene->getDuracaoTempos() - 1 > $aulasPorDia) {
                continue;
            }

            $tentativa = $gene->withDiaPeriodo($dia, $tempo);

            if ($this->slotLivre($cromossomo, $tentativa)) {
                return $tentativa;
            }
        }

        return null;
    }

    private function slotLivre(Cromossomo $cromossomo, Gene $gene): bool {
        $profIndex = $cromossomo->getProfessorHorarioIndex();
        $turmaIndex = $cromossomo->getTurmaHorarioIndex();

        $prof = $gene->getProfessorId();
        $turma = $gene->getTurmaId();
        $dia = $gene->getDiaSemana();
        $periodo = $gene->getPeriodoDia();
        $duracao = $gene->getDuracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            if (($profIndex[$prof][$dia][$periodo + $i] ?? 0) > 0) {
                return false;
            }

            if (($turmaIndex[$turma][$dia][$periodo + $i] ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }
}
