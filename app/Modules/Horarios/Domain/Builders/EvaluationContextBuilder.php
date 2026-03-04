<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Builders;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;

final class EvaluationContextBuilder {
    /**
     * @param array<int,int> $cargaEsperada
     * @param array<int,int[]> $diasPreferidos
     * @param array<int,int[]> $temposPreferidos
     */
    public function build(Cromossomo $cromossomo, array $cargaEsperada = [], array $diasPreferidos = [], array $temposPreferidos = []): EvaluationContext {
        return new EvaluationContext(
            cromossomo: $cromossomo,
            cargaEsperada: $cargaEsperada,
            diasPreferidos: $diasPreferidos,
            temposPreferidos: $temposPreferidos,
        );
    }
}
