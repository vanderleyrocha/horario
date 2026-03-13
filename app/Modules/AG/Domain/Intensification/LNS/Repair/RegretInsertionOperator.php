<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

class RegretInsertionOperator implements RepairOperatorInterface
{
    public function __construct(private readonly ScheduleData $data)
    {
    }

    public function repair(PartialSolution $partial): Cromossomo
    {
        $assigned = $partial->assigned();
        $unassigned = $partial->unassigned();

        $genes = $assigned;

        while (!empty($unassigned)) {

            $bestLessonIndex = null;
            $bestRegret = -INF;
            $bestGene = null;

            foreach ($unassigned as $index => $gene) {

                [$bestCost, $secondCost, $bestCandidate] =
                    $this->evaluateInsertionOptions($gene, $genes);

                $regret = $secondCost - $bestCost;

                if ($regret > $bestRegret) {

                    $bestRegret = $regret;
                    $bestLessonIndex = $index;
                    $bestGene = $bestCandidate;
                }
            }

            if ($bestGene === null) {
                break;
            }

            $genes[] = $bestGene;

            unset($unassigned[$bestLessonIndex]);
        }

        return new Cromossomo(array_values($genes));
    }

    private function evaluateInsertionOptions(Gene $gene, array $currentGenes): array
    {
        $slots = $this->data->timeSlots;

        $bestCost = INF;
        $secondCost = INF;

        $bestCandidate = null;

        foreach ($slots as $slot) {

            $candidate =
                $gene->withDiaPeriodo($slot['day'], $slot['period']);

            $cost =
                $this->estimateConflictCost($candidate, $currentGenes);

            if ($cost < $bestCost) {

                $secondCost = $bestCost;
                $bestCost = $cost;
                $bestCandidate = $candidate;
            } elseif ($cost < $secondCost) {

                $secondCost = $cost;
            }
        }

        return [$bestCost, $secondCost, $bestCandidate];
    }

    private function estimateConflictCost(Gene $candidate, array $genes): float
    {
        $cost = 0;

        foreach ($genes as $gene) {

            if ($candidate->conflictsWith($gene)) {
                $cost += 1;
            }
        }

        return $cost;
    }

    public function getName(): string
    {
        return 'RegretInsertion';
    }
}
