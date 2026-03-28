<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Repair;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\ValueObjects\ScheduleData;

class RegretInsertionOperator implements AdaptiveRepairOperatorInterface, RepairOperatorInterface
{
    private float $candidateSampleRatio = 1.0;

    private int $regretDepth = 2;

    public function __construct(private readonly ScheduleData $data) {}

    public function repair(PartialSolution $partial): Cromossomo
    {
        $assigned = $partial->assigned();
        $unassigned = $partial->unassigned();

        $genes = $assigned;

        while (! empty($unassigned)) {

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
        $options = [];

        foreach ($slots as $slot) {
            $candidate = $gene->withDiaPeriodo($slot->day, $slot->lessonNumber);
            $options[] = [
                'cost' => $this->estimateConflictCost($candidate, $currentGenes),
                'candidate' => $candidate,
            ];
        }

        usort($options, static function (array $left, array $right): int {
            return [$left['cost'], $left['candidate']->diaSemana(), $left['candidate']->periodoDia()]
                <=>
                [$right['cost'], $right['candidate']->diaSemana(), $right['candidate']->periodoDia()];
        });

        if ($options === []) {
            return [INF, INF, null];
        }

        $limit = max($this->regretDepth, (int) ceil(count($options) * $this->candidateSampleRatio));
        $trimmedOptions = array_slice($options, 0, min(count($options), $limit));
        $best = $trimmedOptions[0];
        $comparisonIndex = min(count($trimmedOptions) - 1, $this->regretDepth - 1);
        $comparison = $trimmedOptions[$comparisonIndex];

        return [$best['cost'], $comparison['cost'], $best['candidate']];
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

    public function configureRepairIntensity(float $intensity): void
    {
        $this->candidateSampleRatio = max(0.35, min(1.0, 0.35 + ($intensity * 0.65)));
        $this->regretDepth = $intensity >= 0.75 ? 3 : 2;
    }
}
