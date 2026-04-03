<?php

namespace App\Modules\Horarios\Domain\ValueObjects;

class ProfessorLoad
{
    public function __construct(
        public readonly int $professorId,
        public readonly int $currentLoad,
    ) {}
}
