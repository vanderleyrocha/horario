<?php

namespace App\Modules\Horarios\Domain\Evaluation;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class EvaluationContext {

    public function __construct(
        private readonly Cromossomo $cromossomo,
        private readonly ScheduleData $data,
        private readonly array $cargaEsperada = [],
        private readonly array $diasPreferidos = [],
        private readonly array $temposPreferidos = []
    ) {
    }

    /* ============================================================
     | ACESSO AO DOMÍNIO
     ============================================================ */

    public function data(): ScheduleData {
        return $this->data;
    }

    /* ============================================================
     | Estrutura base (delegação)
     ============================================================ */

    public function gene(int $index): Gene {
        return $this->genes()[$index];
    }

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

    public function cargaTurmaPorDia(): array {
        $map = [];

        foreach ($this->genes() as $gene) {

            $map[$gene->turmaId()][$gene->diaSemana()] =
                ($map[$gene->turmaId()][$gene->diaSemana()] ?? 0)
                + $gene->duracaoTempos();
        }

        return $map;
    }

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

        foreach ($map as &$slots) {
            usort(
                $slots,
                fn($a, $b) =>
                [$a['dia'], $a['periodo']] <=> [$b['dia'], $b['periodo']]
            );
        }

        return $map;
    }

    public function totalGenes(): int {
        return count($this->genes());
    }

    /* ============================================================
     | Dados de configuração
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

    public function cromossomo(): Cromossomo {
        return $this->cromossomo;
    }
}
