<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Intensification\LNS\Conflict\ConflictDetector;

class ConflictDestroyOperator implements DestroyOperatorInterface {
    private ConflictDetector $detector;

    private float $destroyRatio;

    public function __construct(
        ConflictDetector $detector,
        float $destroyRatio = 0.2
    ) {
        $this->detector = $detector;
        $this->destroyRatio = $destroyRatio;
    }

    public function destroy(Cromossomo $solution): PartialSolution {
        $conflicts = $this->detector->detect($solution);

        $genesToRemove = [];

        $genes = $solution->genes();

        $targetSize = (int) floor(
            count($genes) * $this->destroyRatio
        );

        foreach ($conflicts->all() as $conflict) {

            $genesToRemove[$conflict->geneIndex] = true;

            if (count($genesToRemove) >= $targetSize) {
                break;
            }
        }

        $assigned = [];
        $unassigned = [];

        foreach ($genes as $index => $gene) {

            if (isset($genesToRemove[$index])) {
                $unassigned[] = $gene;
            } else {
                $assigned[] = $gene;
            }
        }

        return new PartialSolution($assigned, $unassigned);
    }
}
