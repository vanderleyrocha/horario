<?php

namespace App\Modules\AG\Domain\Fitness;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;

final class EvaluationContext {
    /**
     * @param array<int,int> $cargaEsperada
     * @param array<int,int[]> $diasPreferidos
     * @param array<int,int[]> $temposPreferidos
     */
    public function __construct(
        private readonly Cromossomo $cromossomo,
        private readonly array $cargaEsperada = [],
        private readonly array $diasPreferidos = [],
        private readonly array $temposPreferidos = []
    ) {
    }

    /* ============================================================
     |  ACESSO ESTRUTURAL (Delegação ao Cromossomo)
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
     |  DADOS DE CONTEXTO (Configuração)
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
