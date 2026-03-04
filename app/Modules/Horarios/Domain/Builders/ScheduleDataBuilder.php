<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Builders;

use App\Models\Horario;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ProfessorData;
use App\Modules\Horarios\Domain\ValueObjects\ClassData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

final class ScheduleDataBuilder {
    public function build(Horario $horario): ScheduleData {
        $config = $horario->configuracaoHorario;

        $aulas = $horario->aulas()
            ->with(['professor', 'turma', 'disciplina'])
            ->get();

        $lessons = [];
        $professors = [];
        $classes = [];

        $lessonsByProfessor = [];
        $lessonsByClass = [];
        $expectedLoadByLesson = [];

        foreach ($aulas as $aula) {

            $lesson = new LessonData(
                id: $aula->id,
                professorId: $aula->professor_id,
                classId: $aula->turma_id,
                disciplinaId: $aula->disciplina_id,
                requiredSlots: $aula->carga_horaria,
                requiresConsecutive: (bool) $aula->bloco_continuo,
            );

            $lessons[$aula->id] = $lesson;

            $expectedLoadByLesson[$aula->id] = $aula->carga_horaria;

            /* ============================================================
             | Índices por professor
             ============================================================ */

            $lessonsByProfessor[$aula->professor_id][] = $aula->id;

            /* ============================================================
             | Índices por turma
             ============================================================ */

            $lessonsByClass[$aula->turma_id][] = $aula->id;

            /* ============================================================
             | Professores
             ============================================================ */

            if (!isset($professors[$aula->professor_id])) {

                $professors[$aula->professor_id] = new ProfessorData(
                    id: $aula->professor_id,
                    maxWeeklyLoad: $aula->professor->carga_maxima ?? 40,
                );
            }

            /* ============================================================
             | Turmas
             ============================================================ */

            if (!isset($classes[$aula->turma_id])) {

                $classes[$aula->turma_id] = new ClassData(
                    id: $aula->turma_id,
                    maxDailyLessons: $aula->turma->max_aulas_dia ?? 6,
                );
            }
        }

        /* ============================================================
         | Time slots
         ============================================================ */

        $timeSlots = $this->buildTimeSlots(
            $config->dias_semana,
            $config->aulas_por_dia
        );

        /* ============================================================
         | Restrições estruturadas
         ============================================================ */

        [
            $restrictions,
            $restrictionsByProfessor,
            $restrictionsByClass
        ] = $this->buildRestrictions($horario);

        /* ============================================================
         | Disponibilidade pré-calculada
         ============================================================ */

        $availableSlotsByProfessor = $this->buildAvailability(
            $professors,
            $timeSlots,
            $restrictionsByProfessor
        );

        $availableSlotsByClass = $this->buildAvailability(
            $classes,
            $timeSlots,
            $restrictionsByClass
        );

        return new ScheduleData(

            /* ENTIDADES */

            lessons: $lessons,
            professors: $professors,
            classes: $classes,
            timeSlots: $timeSlots,

            /* RESTRIÇÕES */

            restrictions: $restrictions,

            /* ÍNDICES */

            lessonsByProfessor: $lessonsByProfessor,
            lessonsByClass: $lessonsByClass,

            restrictionsByProfessor: $restrictionsByProfessor,
            restrictionsByClass: $restrictionsByClass,

            expectedLoadByLesson: $expectedLoadByLesson,

            availableSlotsByProfessor: $availableSlotsByProfessor,
            availableSlotsByClass: $availableSlotsByClass,

            /* MÉTRICAS */

            totalTimeSlots: count($timeSlots),
            totalLessons: count($lessons),
            totalProfessors: count($professors),
            totalClasses: count($classes)
        );
    }

    private function buildTimeSlots(int $diasSemana, int $aulasPorDia): array {
        $slots = [];

        $id = 0;

        for ($dia = 1; $dia <= $diasSemana; $dia++) {

            for ($periodo = 1; $periodo <= $aulasPorDia; $periodo++) {

                $slots[$id] = new TimeSlot(
                    id: $id,
                    day: $dia,
                    lessonNumber: $periodo
                );

                $id++;
            }
        }

        return $slots;
    }

    private function buildRestrictions(Horario $horario): array {
        $restrictions = [];

        $byProfessor = [];
        $byClass = [];

        foreach ($horario->restricoesTempo as $r) {

            $restrictions[] = [
                'entity_type' => $r->tipo,
                'entity_id' => $r->entidade_id,
                'time_slot' => $r->time_slot,
                'is_mandatory' => (bool) $r->obrigatorio,
            ];

            $slot = $r->time_slot;

            $dia = $slot->day ?? null;
            $periodo = $slot->lessonNumber ?? null;

            if ($r->tipo === 'professor') {
                $byProfessor[$r->entidade_id][$dia][$periodo] = true;
            }

            if ($r->tipo === 'turma') {
                $byClass[$r->entidade_id][$dia][$periodo] = true;
            }
        }

        return [$restrictions, $byProfessor, $byClass];
    }

    private function buildAvailability(array $entities, array $timeSlots, array $restrictions): array {
        $availability = [];

        foreach ($entities as $entityId => $entity) {

            foreach ($timeSlots as $slot) {

                if (
                    isset($restrictions[$entityId][$slot->day][$slot->lessonNumber])
                ) {
                    continue;
                }

                $availability[$entityId][] = $slot->id;
            }
        }

        return $availability;
    }
}
