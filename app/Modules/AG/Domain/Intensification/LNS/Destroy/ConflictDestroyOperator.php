<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

use App\Modules\AG\Domain\Intensification\LNS\Conflict\ConflictDetector;
use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class ConflictDestroyOperator implements AdaptiveDestroyOperatorInterface, DestroyOperatorInterface
{
    private ConflictDetector $detector;

    private float $destroyRatio;

    public function __construct(ConflictDetector $detector, float $destroyRatio = 0.2)
    {
        $this->detector = $detector;
        $this->destroyRatio = $destroyRatio;
    }

    public function getName(): string
    {
        return 'ConflictDestroy';
    }

    public function destroy(Cromossomo $solution): PartialSolution
    {
        $conflicts = $this->detector->detect($solution);

        $genesToRemove = [];

        $genes = $solution->genes();

        $targetSize = max(1, (int) floor(count($genes) * $this->destroyRatio));

        foreach ($conflicts->all() as $conflict) {

            $genesToRemove[$conflict->geneIndex] = true;

            if (count($genesToRemove) >= $targetSize) {
                break;
            }
        }

        if (count($genesToRemove) < $targetSize && $genes !== []) {
            $candidateIndexes = array_keys($genes);
            shuffle($candidateIndexes);

            foreach ($candidateIndexes as $index) {
                $genesToRemove[$index] = true;

                if (count($genesToRemove) >= $targetSize) {
                    break;
                }
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

    public function configureDestroyIntensity(float $intensity): void
    {
        $this->destroyRatio = max(0.12, min(0.65, 0.12 + ($intensity * 0.43)));
    }
}
