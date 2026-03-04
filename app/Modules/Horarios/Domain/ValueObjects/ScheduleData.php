<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\ValueObjects;

final class ScheduleData {
    public function __construct(

        /* ============================================================
         | ENTIDADES
         ============================================================ */

        public readonly array $lessons,
        public readonly array $professors,
        public readonly array $classes,
        public readonly array $timeSlots,

        /* ============================================================
         | RESTRIÇÕES
         ============================================================ */

        public readonly array $restrictions,

        /* ============================================================
         | ÍNDICES ESTRUTURAIS
         ============================================================ */

        public readonly array $lessonsByProfessor,
        public readonly array $lessonsByClass,

        public readonly array $restrictionsByProfessor,
        public readonly array $restrictionsByClass,

        public readonly array $expectedLoadByLesson,

        public readonly array $availableSlotsByProfessor,
        public readonly array $availableSlotsByClass,

        /* ============================================================
         | MÉTRICAS GLOBAIS
         ============================================================ */

        public readonly int $totalTimeSlots,

        public readonly int $totalLessons,
        public readonly int $totalProfessors,
        public readonly int $totalClasses,

    ) {
    }
}
