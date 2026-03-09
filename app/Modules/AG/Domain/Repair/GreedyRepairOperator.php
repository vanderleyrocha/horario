<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Repair;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class GreedyRepairOperator {
    public function repair(Cromossomo $chromosome, ScheduleData $data): Cromossomo {

        $child = $chromosome->copy();

        foreach ($child->genes() as $index => $gene) {

            if (!$this->isValid($child, $gene)) {

                $candidate = $this->relocateGene(
                    $child,
                    $gene,
                    $data
                );

                if ($candidate !== null) {
                    $child->replaceGene($index, $candidate);
                }
            }
        }

        return $child;
    }

    private function relocateGene(Cromossomo $cromossomo, Gene $gene, ScheduleData $data): ?Gene {

        $profSlots = $data->availableSlotsByProfessor[$gene->professorId()] ?? [];
        $classSlots = $data->availableSlotsByClass[$gene->turmaId()] ?? [];

        $possible = array_intersect_key(
            $profSlots,
            $classSlots
        );

        foreach ($possible as $slot) {

            $candidate = $gene->withDiaPeriodo(
                $slot['day'],
                $slot['period']
            );

            if ($this->isValid($cromossomo, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isValid(Cromossomo $cromossomo, Gene $gene): bool {

        $profIndex = $cromossomo->professorIndex();
        $turmaIndex = $cromossomo->turmaIndex();

        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $periodo = $gene->periodoDia();

        return !isset($profIndex[$prof][$dia][$periodo]) && !isset($turmaIndex[$turma][$dia][$periodo]);
    }
}
