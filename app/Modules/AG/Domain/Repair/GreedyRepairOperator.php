<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Repair;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class GreedyRepairOperator
{
    public function repair(Cromossomo $chromosome, ScheduleData $data): Cromossomo
    {
        $child = $chromosome->copy();

        $maxPasses = 3;

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $changed = false;

            foreach ($child->genes() as $index => $gene) {

                if ($this->isValid($child, $gene, $index)) {
                    continue;
                }

                $candidate = $this->relocateGene($child, $gene, $data, $index);

                if ($candidate === null) {
                    continue;
                }

                if (
                    $candidate->diaSemana() !== $gene->diaSemana() ||
                    $candidate->periodoDia() !== $gene->periodoDia()
                ) {
                    $child->replaceGene($index, $candidate);
                    $changed = true;
                }
            }

            if (! $changed) {
                break;
            }
        }

        return $child;
    }

    private function relocateGene(Cromossomo $cromossomo, Gene $gene, ScheduleData $data, int $sourceGeneIndex): ?Gene
    {
        $profSlots = $data->availableSlotsByProfessor[$gene->professorId()] ?? [];

        $classSlots = $data->availableSlotsByClass[$gene->turmaId()] ?? [];

        /*
        | interseção correta de slotIds
        */

        $possible = array_intersect($profSlots, $classSlots);

        foreach ($possible as $slotId) {

            if (!isset($data->timeSlots[$slotId])) {
                continue;
            }

            $slot = $data->timeSlots[$slotId];

            if (! $this->slotSupportsDuration($slot->lessonNumber, $gene->duracaoTempos(), $data)) {
                continue;
            }

            $candidate = $gene->withDiaPeriodo($slot->day, $slot->lessonNumber);

            if ($this->isValid($cromossomo, $candidate, $sourceGeneIndex)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isValid(Cromossomo $cromossomo, Gene $gene, ?int $sourceGeneIndex = null): bool
    {
        $professorPeriodoIndex = $cromossomo->professorPeriodoIndex();
        $turmaPeriodoIndex = $cromossomo->turmaPeriodoIndex();

        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $periodoInicial = $gene->periodoDia();

        for ($offset = 0; $offset < $gene->duracaoTempos(); $offset++) {
            $periodo = $periodoInicial + $offset;

            if (
                $this->hasExternalOccupation($professorPeriodoIndex, $prof, $dia, $periodo, $sourceGeneIndex) ||
                $this->hasExternalOccupation($turmaPeriodoIndex, $turma, $dia, $periodo, $sourceGeneIndex)
            ) {
                return false;
            }
        }

        return true;
    }

    private function hasExternalOccupation(array $indexMap, int $entityId, int $dia, int $periodo, ?int $sourceGeneIndex): bool
    {
        if (! isset($indexMap[$entityId][$dia][$periodo])) {
            return false;
        }

        foreach ($indexMap[$entityId][$dia][$periodo] as $occupiedGeneIndex) {
            if ($sourceGeneIndex !== null && (int) $occupiedGeneIndex === $sourceGeneIndex) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function slotSupportsDuration(int $initialPeriod, int $duration, ScheduleData $data): bool
    {
        return ($initialPeriod + $duration - 1) <= $this->maxLessonNumber($data);
    }

    private function maxLessonNumber(ScheduleData $data): int
    {
        if ($data->timeSlots === []) {
            return 0;
        }

        return max(array_map(
            static fn ($slot) => $slot->lessonNumber,
            $data->timeSlots
        ));
    }
}
