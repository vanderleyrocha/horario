<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\ValueObjects;

final class LessonData {
    public function __construct(
        public readonly int $id,
        public readonly int $professorId,
        public readonly int $classId,
        public readonly int $disciplinaId,
        public readonly int $requiredSlots,
        public readonly bool $requiresConsecutive,

        /**
         * Preferências opcionais (soft constraints)
         */

        public readonly array $preferredDays = [],
        public readonly array $preferredPeriods = [],

        /**
         * Limite opcional de aulas por dia
         */
        public readonly ?int $maxPerDay = null,
    ) {
    }
}
