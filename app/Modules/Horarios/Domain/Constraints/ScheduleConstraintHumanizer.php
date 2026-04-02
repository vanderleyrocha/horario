<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints;

use App\Modules\Horarios\Domain\Constraints\Entities\MutualExclusionConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\ScheduleConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\SyncSameTimeslotConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\TimePlacementConstraint;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Enums\TimePlacementMode;

final class ScheduleConstraintHumanizer
{
    public function describe(ScheduleConstraint $constraint): string
    {
        return match (true) {
            $constraint instanceof SyncSameTimeslotConstraint => $this->describeSyncSameTimeslot($constraint),
            $constraint instanceof MutualExclusionConstraint => $this->describeMutualExclusion($constraint),
            $constraint instanceof TimePlacementConstraint => $this->describeTimePlacement($constraint),
            default => sprintf('Constraint do tipo %s.', $constraint->type()->value),
        };
    }

    public function typeLabel(ConstraintType $type): string
    {
        return match ($type) {
            ConstraintType::SYNC_SAME_TIMESLOT => 'Sincronismo no mesmo horário',
            ConstraintType::MUTUAL_EXCLUSION => 'Exclusão mútua',
            ConstraintType::TIME_PLACEMENT => 'Posicionamento temporal',
        };
    }

    public function levelLabel(ConstraintLevel $level): string
    {
        return match ($level) {
            ConstraintLevel::HARD => 'Obrigatória',
            ConstraintLevel::SOFT => 'Preferencial',
        };
    }

    public function statusLabel(bool $isActive): string
    {
        return $isActive ? 'Ativa' : 'Inativa';
    }

    public function summarize(ScheduleConstraint $constraint): array
    {
        return [
            'name' => $constraint->name(),
            'type_label' => $this->typeLabel($constraint->type()),
            'level_label' => $this->levelLabel($constraint->level()),
            'status_label' => $this->statusLabel($constraint->isActive()),
            'description' => $this->describe($constraint),
        ];
    }

    private function describeSyncSameTimeslot(SyncSameTimeslotConstraint $constraint): string
    {
        if ($constraint->rightGroup() === null) {
            return sprintf(
                'Sincroniza internamente %s no mesmo dia e tempo, usando ocorrencia %s e correspondencia %s.',
                $this->formatLessonGroup($constraint->leftGroup()->lessonIds(), 'grupo principal'),
                $this->humanizeToken($constraint->occurrenceMode()->value),
                $this->humanizeToken($constraint->matchMode()->value),
            );
        }

        return sprintf(
            'Sincroniza %s com %s no mesmo dia e tempo, usando ocorrencia %s e correspondencia %s.',
            $this->formatLessonGroup($constraint->leftGroup()->lessonIds(), 'grupo esquerdo'),
            $this->formatLessonGroup($constraint->rightGroup()->lessonIds(), 'grupo direito'),
            $this->humanizeToken($constraint->occurrenceMode()->value),
            $this->humanizeToken($constraint->matchMode()->value),
        );
    }

    private function describeMutualExclusion(MutualExclusionConstraint $constraint): string
    {
        if ($constraint->rightGroup() === null) {
            return sprintf(
                'Impede coincidencia de horario dentro de %s.',
                $this->formatLessonGroup($constraint->leftGroup()->lessonIds(), 'grupo principal'),
            );
        }

        return sprintf(
            'Impede coincidencia de horario entre %s e %s.',
            $this->formatLessonGroup($constraint->leftGroup()->lessonIds(), 'grupo esquerdo'),
            $this->formatLessonGroup($constraint->rightGroup()->lessonIds(), 'grupo direito'),
        );
    }

    private function describeTimePlacement(TimePlacementConstraint $constraint): string
    {
        $verb = match ($constraint->mode()) {
            TimePlacementMode::REQUIRED => 'Exige',
            TimePlacementMode::PREFERRED => 'Prefere',
            TimePlacementMode::FORBIDDEN => 'Impede',
        };

        $scope = $constraint->mode() === TimePlacementMode::FORBIDDEN
            ? 'nos periodos bloqueados'
            : 'na janela configurada';

        return sprintf(
            '%s que %s fique %s: %s.',
            $verb,
            $this->formatLessonGroup($constraint->targetGroup()->lessonIds(), 'grupo alvo'),
            $scope,
            $this->formatWindow($constraint),
        );
    }

    /**
     * @param list<int> $lessonIds
     */
    private function formatLessonGroup(array $lessonIds, string $label): string
    {
        $count = count($lessonIds);
        $prefix = $count === 1 ? 'aula' : 'aulas';

        return sprintf(
            '%s (%s) com IDs %s',
            $label,
            $prefix,
            implode(', ', $lessonIds),
        );
    }

    private function formatWindow(TimePlacementConstraint $constraint): string
    {
        $parts = [];

        if ($constraint->window()->days() !== []) {
            $parts[] = 'dias ' . implode(', ', $constraint->window()->days());
        }

        if ($constraint->window()->periods() !== []) {
            $parts[] = 'tempos ' . implode(', ', $constraint->window()->periods());
        }

        return implode(' e ', $parts);
    }

    private function humanizeToken(string $value): string
    {
        return mb_strtolower(str_replace('_', ' ', $value));
    }
}
