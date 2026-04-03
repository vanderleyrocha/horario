<?php

namespace App\Modules\Horarios\Domain\ValueObjects;

final class ClassData
{
    public function __construct(
        public readonly int $id,
        public readonly int $maxDailyLessons,
    ) {}
}
