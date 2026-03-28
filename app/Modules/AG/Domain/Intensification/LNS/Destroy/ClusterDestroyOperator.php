<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class ClusterDestroyOperator implements AdaptiveDestroyOperatorInterface, DestroyOperatorInterface
{
    private float $destroyRatio = 0.2;

    public function destroy(Cromossomo $solution): PartialSolution
    {
        $genes = $solution->genes();

        if ($genes === []) {
            return new PartialSolution([], []);
        }

        $targetSize = max(1, (int) floor(count($genes) * $this->destroyRatio));
        $targetTurma = $genes[array_rand($genes)]->turmaId();

        $clusterIndexes = [];

        foreach ($genes as $index => $gene) {
            if ($gene->turmaId() === $targetTurma) {
                $clusterIndexes[] = $index;
            }
        }

        if (count($clusterIndexes) > $targetSize) {
            $clusterIndexes = array_slice($clusterIndexes, 0, $targetSize);
        }

        if (count($clusterIndexes) < $targetSize) {
            $additionalIndexes = array_values(array_diff(array_keys($genes), $clusterIndexes));
            shuffle($additionalIndexes);
            $clusterIndexes = array_merge(
                $clusterIndexes,
                array_slice($additionalIndexes, 0, $targetSize - count($clusterIndexes))
            );
        }

        $indexesToRemove = array_fill_keys($clusterIndexes, true);
        $assigned = [];
        $unassigned = [];

        foreach ($genes as $index => $gene) {
            if (isset($indexesToRemove[$index])) {
                $unassigned[] = $gene;
            } else {
                $assigned[] = $gene;
            }
        }

        return new PartialSolution($assigned, $unassigned);
    }

    public function getName(): string
    {
        return 'ClusterDestroyOperator';
    }

    public function configureDestroyIntensity(float $intensity): void
    {
        $this->destroyRatio = max(0.10, min(0.50, 0.10 + ($intensity * 0.30)));
    }
}
