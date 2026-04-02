<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Repair\Contracts;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

interface RepairHeuristicExtension
{
    /**
     * @return array<int, array{index:int,count:int,duration:int,peers:int[],violations:string[]}>
     */
    public function augmentRepairTargets(Cromossomo $chromosome, ScheduleData $data): array;

    /**
     * @param list<int> $slotIds
     * @return list<int>
     */
    public function filterCandidateStartSlots(Gene $gene, ScheduleData $data, array $slotIds): array;

    /**
     * @param string[] $violationTypes
     */
    public function candidateRankingPenalty(
        Cromossomo $chromosome,
        Gene $candidate,
        int $sourceGeneIndex,
        array $violationTypes,
        ScheduleData $data,
    ): float;

    /**
     * @param string[] $violationTypes
     */
    public function countTargetViolations(
        Cromossomo $chromosome,
        int $geneIndex,
        array $violationTypes,
        ScheduleData $data,
    ): int;
}
