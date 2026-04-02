<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Repair;

use App\Modules\AG\Domain\Repair\Contracts\RepairHeuristicExtension;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

final class CustomConstraintRepairExtension implements RepairHeuristicExtension
{
    public function augmentRepairTargets(Cromossomo $chromosome, ScheduleData $data): array
    {
        return [];
    }

    public function filterCandidateStartSlots(Gene $gene, ScheduleData $data, array $slotIds): array
    {
        return $slotIds;
    }

    public function candidateRankingPenalty(
        Cromossomo $chromosome,
        Gene $candidate,
        int $sourceGeneIndex,
        array $violationTypes,
        ScheduleData $data,
    ): float {
        return 0.0;
    }

    public function countTargetViolations(
        Cromossomo $chromosome,
        int $geneIndex,
        array $violationTypes,
        ScheduleData $data,
    ): int {
        return 0;
    }
}
