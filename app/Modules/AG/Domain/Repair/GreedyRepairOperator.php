<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Repair;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Core\Entities\Gene;

final class GreedyRepairOperator {
    public function __construct(private readonly array $horariosDisponiveis, private readonly int $aulasPorDia) {
    }

    public function repair(Cromossomo $cromossomo): Cromossomo {
        $child = $cromossomo->copy();

        foreach ($child->genes() as $index => $gene) {

            if (!$this->isGeneValido($child, $gene, $index)) {

                $novoGene = $this->realocarGene($child, $gene, $index);

                if ($novoGene !== null) {
                    $child->replaceGene($index, $novoGene);
                }
            }
        }

        return $child;
    }

    /* ============================================================
     |  VALIDAÇÃO
     ============================================================ */

    private function isGeneValido(Cromossomo $cromossomo, Gene $gene, int $index): bool {
        return $this->slotLivreSemEleMesmo($cromossomo, $gene, $index);
    }

    private function realocarGene(Cromossomo $cromossomo, Gene $gene, int $index): ?Gene {

        $slots = $this->horariosDisponiveis;
        shuffle($slots);

        foreach ($slots as $slot) {

            $dia = $slot['dia'];
            $tempo = $slot['tempo'];
            $duracao = $gene->duracaoTempos();

            if ($tempo + $duracao - 1 > $this->aulasPorDia) {
                continue;
            }

            $tentativa = $gene->withDiaPeriodo($dia, $tempo);

            if ($this->slotLivreSemEleMesmo($cromossomo, $tentativa, $index)) {
                return $tentativa;
            }
        }

        return null;
    }

    /**
     * Verifica se o slot está livre ignorando o próprio gene
     */
    private function slotLivreSemEleMesmo(Cromossomo $cromossomo, Gene $gene, int $index): bool {

        $profIndex = $cromossomo->professorIndex();
        $turmaIndex = $cromossomo->turmaIndex();

        $genes = $cromossomo->genes();
        $geneOriginal = $genes[$index];

        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $periodoInicial = $gene->periodoDia();
        $duracao = $gene->duracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            $tempo = $periodoInicial + $i;

            // ignora ocupação do próprio gene
            if (
                isset($profIndex[$prof][$dia][$tempo]) &&
                !($geneOriginal->professorId() === $prof &&
                    $geneOriginal->diaSemana() === $dia &&
                    $tempo >= $geneOriginal->periodoDia() &&
                    $tempo < $geneOriginal->periodoDia() + $geneOriginal->duracaoTempos())
            ) {
                return false;
            }

            if (
                isset($turmaIndex[$turma][$dia][$tempo]) &&
                !($geneOriginal->turmaId() === $turma &&
                    $geneOriginal->diaSemana() === $dia &&
                    $tempo >= $geneOriginal->periodoDia() &&
                    $tempo < $geneOriginal->periodoDia() + $geneOriginal->duracaoTempos())
            ) {
                return false;
            }
        }

        return true;
    }
}
