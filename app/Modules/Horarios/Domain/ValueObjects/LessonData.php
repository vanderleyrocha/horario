<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\ValueObjects;

final class LessonData
{
    public function __construct(
        public readonly int $id,
        public readonly int $professorId,
        public readonly int $classId,
        public readonly int $disciplinaId,
        public readonly int $requiredSlots,
        public readonly int $weeklyOccurrences,
        public readonly bool $requiresConsecutive,
        public readonly array $preferredDays = [],
        public readonly array $preferredPeriods = [],
        public readonly ?int $maxPerDay = null,
    ) {
    }
}
