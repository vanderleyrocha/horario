<?php

namespace App\Modules\Horarios\Domain\ValueObjects;

final class ProfessorData
{
    public function __construct(
        public readonly int $id,
        public readonly int $maxWeeklyLoad,
    ) {}
}
