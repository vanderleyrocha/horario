<?php

namespace App\Modules\Horarios\Domain\ValueObjects;

class TurmaLoad {
    public function __construct(
        public readonly int $turmaId,
        public readonly int $currentLoad,
    ) {
    }
}
