<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Builders;

use App\Models\Horario;
use App\Modules\Horarios\Domain\ValueObjects\ClassData;
use App\Modules\Horarios\Domain\ValueObjects\LessonData;
use App\Modules\Horarios\Domain\ValueObjects\ProfessorData;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;
use App\Modules\Horarios\Domain\ValueObjects\TimeSlot;

final class ScheduleDataBuilder
{
    public function build(Horario $horario): ScheduleData
    {
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
            $duracao = $aula->getDuracaoTempos();

            $lesson = new LessonData(
                id: $aula->id,
                professorId: $aula->professor_id,
                classId: $aula->turma_id,
                disciplinaId: $aula->disciplina_id,
                requiredSlots: $duracao,
                weeklyOccurrences: (int) $aula->aulas_semana,
                requiresConsecutive: (bool) $aula->aulas_consecutivas,
                preferredDays: is_array($aula->dias_preferidos) ? $aula->dias_preferidos : [],
                preferredPeriods: is_array($aula->tempos_preferidos) ? $aula->tempos_preferidos : [],
                maxPerDay: $aula->max_aulas_dia ? (int) $aula->max_aulas_dia : null,
            );

            $lessons[$aula->id] = $lesson;
            $expectedLoadByLesson[$aula->turma_id] = ($expectedLoadByLesson[$aula->turma_id] ?? 0)
                + ((int) $aula->aulas_semana * $duracao);

            $lessonsByProfessor[$aula->professor_id][] = $aula->id;
            $lessonsByClass[$aula->turma_id][] = $aula->id;

            if (!isset($professors[$aula->professor_id])) {
                $professors[$aula->professor_id] = new ProfessorData(
                    id: $aula->professor_id,
                    maxWeeklyLoad: $aula->professor->carga_maxima ?? 40,
                );
            }

            if (!isset($classes[$aula->turma_id])) {
                $classes[$aula->turma_id] = new ClassData(
                    id: $aula->turma_id,
                    maxDailyLessons: $aula->turma->max_aulas_dia ?? 6,
                );
            }
        }

        $timeSlots = $this->buildTimeSlots((int) $config->dias_semana, (int) $config->aulas_por_dia);

        [
            $restrictions,
            $restrictionsByProfessor,
            $restrictionsByClass
        ] = $this->buildRestrictions($horario);

        $availableSlotsByProfessor = $this->buildAvailability($professors, $timeSlots, $restrictionsByProfessor);
        $availableSlotsByClass = $this->buildAvailability($classes, $timeSlots, $restrictionsByClass);

        return new ScheduleData(
            lessons: $lessons,
            professors: $professors,
            classes: $classes,
            timeSlots: $timeSlots,
            restrictions: $restrictions,
            lessonsByProfessor: $lessonsByProfessor,
            lessonsByClass: $lessonsByClass,
            restrictionsByProfessor: $restrictionsByProfessor,
            restrictionsByClass: $restrictionsByClass,
            expectedLoadByLesson: $expectedLoadByLesson,
            availableSlotsByProfessor: $availableSlotsByProfessor,
            availableSlotsByClass: $availableSlotsByClass,
            totalTimeSlots: count($timeSlots),
            totalLessons: count($lessons),
            totalProfessors: count($professors),
            totalClasses: count($classes),
        );
    }

    private function buildTimeSlots(int $diasSemana, int $aulasPorDia): array
    {
        $slots = [];
        $id = 0;

        for ($dia = 1; $dia <= $diasSemana; $dia++) {
            for ($periodo = 1; $periodo <= $aulasPorDia; $periodo++) {
                $slots[$id] = new TimeSlot(id: $id, day: $dia, lessonNumber: $periodo);
                $id++;
            }
        }

        return $slots;
    }

    private function buildRestrictions(Horario $horario): array
    {
        $restrictions = [];
        $byProfessor = [];
        $byClass = [];

        $restricoes = $horario->restricoes_tempo ?? $horario->restricoesTempo ?? [];

        foreach ($restricoes as $r) {
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

    private function buildAvailability(array $entities, array $timeSlots, array $restrictions): array
    {
        $availability = [];

        foreach ($entities as $entityId => $entity) {
            foreach ($timeSlots as $slot) {
                if (isset($restrictions[$entityId][$slot->day][$slot->lessonNumber])) {
                    continue;
                }

                $availability[$entityId][] = $slot->id;
            }
        }

        return $availability;
    }
}
