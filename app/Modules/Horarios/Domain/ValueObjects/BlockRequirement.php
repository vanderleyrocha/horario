<?php

namespace App\Modules\Horarios\Domain\ValueObjects;

class BlockRequirement {
    public function __construct(
        public readonly int $classId,
        public readonly int $professorId,
    ) {
    }
}
