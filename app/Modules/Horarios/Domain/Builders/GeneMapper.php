<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Builders;

use App\Modules\AG\Domain\Representation\Entities\Gene;

final class GeneMapper {
    public function toArray(Gene $gene): array {
        return [
            'aula_id' => $gene->aulaId(),
            'professor_id' => $gene->professorId(),
            'turma_id' => $gene->turmaId(),
            'disciplina_id' => $gene->disciplinaId(),
            'dia_semana' => $gene->diaSemana(),
            'periodo' => $gene->periodoDia(),
            'duracao' => $gene->duracaoTempos(),
        ];
    }
}
