<?php

namespace App\Modules\AG\Domain\Fitness\Delta;

final class AffectedRegion {
    public function __construct(
        public readonly array $geneIndexes,
        public readonly array $professores,
        public readonly array $turmas,
        public readonly array $dias,
        public readonly array $periodos
    ) {
    }
}
