<?php

namespace App\Modules\AG\Domain\Intensification\LNS\Destroy;

use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

class RandomDestroyOperator implements AdaptiveDestroyOperatorInterface, DestroyOperatorInterface
{
    // 🔧 PRIORIDADE 4: Aumentar destruição para mais diversidade
    private float $destroyRatio = 0.40;  // ← Aumentado de 0.2 para 0.40 (40% dos genes)

    public function destroy(Cromossomo $solution): PartialSolution
    {
        $genes = $solution->genes();

        if ($genes === []) {
            return new PartialSolution([], []);
        }

        $removeCount = max(1, (int) floor(count($genes) * $this->destroyRatio));

        $indexes = array_rand($genes, $removeCount);

        $assigned = [];
        $unassigned = [];

        foreach ($genes as $i => $gene) {

            if (in_array($i, (array) $indexes, true)) {
                $unassigned[] = $gene;
            } else {
                $assigned[] = $gene;
            }
        }

        return new PartialSolution($assigned, $unassigned);
    }

    public function getName(): string
    {
        return 'RandomDestroyOperator';
    }

    public function configureDestroyIntensity(float $intensity): void
    {
        $this->destroyRatio = max(0.10, min(0.55, 0.10 + ($intensity * 0.35)));
    }
}
