<?php

namespace App\Modules\Horarios\Domain\Evaluation;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class EvaluationContext {

    public function __construct(
        private readonly Cromossomo $cromossomo,
        private readonly array $cargaEsperada = [],
        private readonly array $diasPreferidos = [],
        private readonly array $temposPreferidos = []
    ) {
    }

    /* ============================================================
     | Estrutura base (delegação)
     ============================================================ */

    public function genes(): array {
        return $this->cromossomo->genes();
    }

    public function professorIndex(): array {
        return $this->cromossomo->professorIndex();
    }

    public function turmaIndex(): array {
        return $this->cromossomo->turmaIndex();
    }

    public function cargaProfessor(): array {
        return $this->cromossomo->cargaProfessor();
    }

    public function cargaTurma(): array {
        return $this->cromossomo->cargaTurma();
    }

    /* ============================================================
     | Derivações estruturais adicionais
     ============================================================ */

    /**
     * Retorna mapa: turmaId => diaSemana => quantidade
     */
    public function cargaTurmaPorDia(): array {

        $map = [];

        foreach ($this->genes() as $gene) {

            $map[$gene->turmaId()][$gene->diaSemana()] =
                ($map[$gene->turmaId()][$gene->diaSemana()] ?? 0)
                + $gene->duracaoTempos();
        }

        return $map;
    }

    /**
     * Retorna mapa: aulaId => lista de períodos ordenados
     */
    public function alocacoesPorAula(): array {

        $map = [];

        foreach ($this->genes() as $gene) {

            for ($i = 0; $i < $gene->duracaoTempos(); $i++) {

                $periodo = $gene->periodoDia() + $i;

                $map[$gene->aulaId()][] = [
                    'dia' => $gene->diaSemana(),
                    'periodo' => $periodo,
                ];
            }
        }

        // ordenação estrutural
        foreach ($map as &$slots) {
            usort($slots, function ($a, $b) {
                return [$a['dia'], $a['periodo']]
                    <=> [$b['dia'], $b['periodo']];
            });
        }

        return $map;
    }

    /**
     * Total de genes
     */
    public function totalGenes(): int {
        return count($this->genes());
    }

    /* ============================================================
     | Dados de Configuração
     ============================================================ */

    public function cargaEsperada(): array {
        return $this->cargaEsperada;
    }

    public function diasPreferidos(): array {
        return $this->diasPreferidos;
    }

    public function temposPreferidos(): array {
        return $this->temposPreferidos;
    }
}
