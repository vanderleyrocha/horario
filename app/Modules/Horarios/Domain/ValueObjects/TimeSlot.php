<?php

namespace App\Modules\Horarios\Domain\ValueObjects;

class TimeSlot
{
    public function __construct(
        public readonly int $id,
        public readonly int $day,
        public readonly int $lessonNumber,
    ) {}
}
