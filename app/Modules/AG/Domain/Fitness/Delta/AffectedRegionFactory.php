<?php

namespace App\Modules\AG\Domain\Fitness\Delta;

use App\Modules\AG\Domain\Representation\Entities\Gene;

final class AffectedRegionFactory {
    public static function fromGeneChange(
        int $index,
        Gene $old,
        Gene $new
    ): AffectedRegion {

        return new AffectedRegion(
            geneIndexes: [$index],

            professores: [
                $old->professorId(),
                $new->professorId()
            ],

            turmas: [
                $old->turmaId(),
                $new->turmaId()
            ],

            dias: [
                $old->diaSemana(),
                $new->diaSemana()
            ],

            periodos: [
                $old->periodoDia(),
                $new->periodoDia()
            ]
        );
    }
}
